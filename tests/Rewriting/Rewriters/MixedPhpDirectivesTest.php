<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\MixedPhpDirectivesRewriter;
use Forte\Rewriting\Rewriter;

describe('Mixed PHP Rewriter', function (): void {
    it('transforms @php(expression) to PHP tag', function (): void {
        $doc = $this->parse('@php($x = 1)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<?php $x = 1; ?>');
    });

    it('adds semicolon if missing', function (): void {
        $doc = $this->parse('@php($counter++)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<?php $counter++; ?>');
    });

    it('preserves semicolon if present', function (): void {
        $doc = $this->parse('@php($x = 1;)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<?php $x = 1; ?>');
    });

    it('does not rewrite block @php directive', function (): void {
        $doc = $this->parse('@php
$x = 1;
@endphp');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('@php
$x = 1;
@endphp');
    });

    it('handles complex expressions', function (): void {
        $doc = $this->parse('@php($result = array_map(fn($x) => $x * 2, $items))');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<?php $result = array_map(fn($x) => $x * 2, $items); ?>');
    });

    it('handles multiple @php directives', function (): void {
        $doc = $this->parse('@php($a = 1) @php($b = 2)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<?php $a = 1; ?> <?php $b = 2; ?>');
    });

    it('leaves other directives unchanged', function (): void {
        $doc = $this->parse('@if($x) content @endif');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('@if($x) content @endif');
    });

    it('works with surrounding content', function (): void {
        $doc = $this->parse('<div>@php($x = 1) text</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new MixedPhpDirectivesRewriter);

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div><?php $x = 1; ?> text</div>');
    });
});
