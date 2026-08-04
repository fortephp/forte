<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Parser\Directives\Directives;

/**
 * Directives Laravel compiles that Forte did not know about.
 *
 * An unknown directive is not an error — it degrades to text — but it means the
 * tree carries no block for it, so anything reasoning about structure treats a
 * correctly written `@session` as if it were not a directive at all.
 */
describe('recently added Blade directives', function (): void {
    test('is a known directive', function (string $name): void {
        expect(Directives::withDefaults()->isDirective($name))->toBeTrue();
    })->with([
        'session', 'endsession',
        'context', 'endcontext',
        'hasstack',
        'elsepush', 'elsepushif',
        'bool', 'vite', 'includeisolated',
    ]);

    test('pairs into a block', function (string $input, string $name, string $end): void {
        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();

        expect($block->nameText())->toBe($name)
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe($end)
            ->and($doc->render())->toBe($input);
    })->with([
        'session' => ["@session('status') <p>{{ \$value }}</p> @endsession", 'session', 'endsession'],
        'context' => ["@context('trace_id') <p>{{ \$value }}</p> @endcontext", 'context', 'endcontext'],
        // @hasStack compiles to a bare if, so @endif closes it, like @hasSection.
        'hasStack' => ["@hasStack('scripts') <p>yes</p> @endif", 'hasstack', 'endif'],
    ]);

    test('treats push branches as part of the pushIf block', function (string $input): void {
        $doc = $this->parse($input);

        $blocks = [];
        $doc->getBlockDirectives()->each(function (DirectiveBlockNode $block) use (&$blocks): void {
            $blocks[] = $block->nameText().'/'.($block->endDirective()?->nameText() ?? 'unclosed');
        });

        $loose = [];
        $doc->getDirectives()->each(function ($directive) use (&$loose): void {
            $loose[] = $directive->nameText();
        });

        expect($blocks)->toBe(['pushif/endpushif'])
            ->and($loose)->toBe([])
            ->and($doc->render())->toBe($input);
    })->with([
        "@pushIf(\$a, 'scripts') <p>1</p> @endPushIf",
        "@pushIf(\$a, 'scripts') <p>1</p> @elsePush('scripts') <p>2</p> @endPushIf",
        "@pushIf(\$a, 'scripts') <p>1</p> @elsePushIf(\$b, 'scripts') <p>2</p> @endPushIf",
    ]);

    test('recognises the inline helpers', function (string $input, string $name): void {
        $doc = $this->parse($input);

        $found = [];
        $doc->getDirectives()->each(function ($directive) use (&$found): void {
            $found[] = $directive->nameText();
        });

        expect($found)->toContain($name)
            ->and($doc->render())->toBe($input);
    })->with([
        'bool' => ['<p>@bool($flag)</p>', 'bool'],
        'vite with arguments' => ["@vite(['resources/js/app.js'])", 'vite'],
        'vite without arguments' => ['@vite', 'vite'],
        'includeIsolated' => ["@includeIsolated('partials.nav')", 'includeisolated'],
    ]);

    test('leaves existing directives untouched', function (string $input, string $name, string $end): void {
        $doc = $this->parse($input);

        expect($doc->getChildren()[0]->asDirectiveBlock()->nameText())->toBe($name)
            ->and($doc->getChildren()[0]->asDirectiveBlock()->endDirective()?->nameText())->toBe($end)
            ->and($doc->render())->toBe($input);
    })->with([
        'if' => ['@if($a) x @endif', 'if', 'endif'],
        'push' => ["@push('scripts') <p>x</p> @endpush", 'push', 'endpush'],
        'forelse' => ['@forelse($r as $x) {{ $x }} @empty none @endforelse', 'forelse', 'endforelse'],
        'hasSection' => ["@hasSection('sidebar') <p>yes</p> @endif", 'hassection', 'endif'],
        'section' => ["@section('content') x @endsection", 'section', 'endsection'],
    ]);
});
