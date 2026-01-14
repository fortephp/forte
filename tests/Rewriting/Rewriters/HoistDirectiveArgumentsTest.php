<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\HoistDirectiveArgumentsRewriter;
use Forte\Rewriting\Rewriter;

describe('Hoist Directive Rewriters', function (): void {
    it('hoists @json first argument into temp variable', function (): void {
        $doc = $this->parse('@json($data)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toMatch('/^<\?php \$__tmpVar\w+ = \$data; \?>/')
            ->and($output)->toContain('@json($__tmpVar')
            ->and($output)->toMatch('/<\?php unset\(\$__tmpVar\w+\); \?>$/');
    });

    it('preserves additional @json arguments', function (): void {
        $doc = $this->parse('@json($data, JSON_PRETTY_PRINT)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)
            ->toMatch('/@json\(\$__tmpVar\w+, JSON_PRETTY_PRINT\)/');
    });

    it('handles @json with flags and depth', function (): void {
        $doc = $this->parse('@json($data, JSON_PRETTY_PRINT, 512)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)
            ->toMatch('/@json\(\$__tmpVar\w+, JSON_PRETTY_PRINT, 512\)/');
    });

    it('does not rewrite @json without arguments', function (): void {
        $doc = $this->parse('@json');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('@json');
    });

    it('leaves other directives unchanged', function (): void {
        $doc = $this->parse('@if($condition) content @endif');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('@if($condition) content @endif');
    });

    it('works with custom directive list', function (): void {
        $doc = $this->parse('@customDirective($expression)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter(['customDirective']));

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toMatch('/^<\?php \$__tmpVar\w+ = \$expression; \?>/')
            ->and($output)->toContain('@customDirective($__tmpVar')
            ->and($output)->toMatch('/<\?php unset\(\$__tmpVar\w+\); \?>$/');
    });

    it('ignores directives not in custom list', function (): void {
        $doc = $this->parse('@json($data)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter(['customDirective']));

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('@json($data)');
    });

    it('handles multiple @json directives', function (): void {
        $doc = $this->parse('@json($a) @json($b)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect(substr_count($output, '<'.'?php '))->toBe(4)
            ->and(substr_count($output, '$__tmpVar'))->toBeGreaterThanOrEqual(4)
            ->and(substr_count($output, '@json'))->toBe(2);
    });

    it('works with @json in nested structure', function (): void {
        $doc = $this->parse('<script>let data = @json($items)</script>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new HoistDirectiveArgumentsRewriter);

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toContain('<script>')
            ->and($output)->toContain('</script>')
            ->and($output)->toMatch('/<\?php \$__tmpVar\w+ = \$items; \?>/');
    });
});
