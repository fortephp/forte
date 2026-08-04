<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Parser\Directives\Directives;

/**
 * Directives that accept more than one closing directive.
 *
 * `@push` closes with either `@endpush` or `@endpushOnce`, `@prepend` with
 * `@endprepend` or `@endprependOnce`, and `@component` with `@endcomponent` or
 * `@endcomponentClass`. These once failed to pair at all: the tokens were still
 * produced, so rendering round-tripped perfectly and nothing looked wrong, but
 * no DirectiveBlock was assembled and both halves surfaced as loose directives.
 */
describe('directives with multiple terminators', function (): void {
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
        // Directive names normalize to lower case; render() keeps source casing.
    })->with([
        'push' => ["@push('scripts') body @endpush", 'push', 'endpush'],
        'push closed by endpushOnce' => ["@push('scripts') body @endpushOnce", 'push', 'endpushonce'],
        'pushOnce' => ["@pushOnce('scripts') body @endpushOnce", 'pushonce', 'endpushonce'],
        'pushOnce closed by endpush' => ["@pushOnce('scripts') body @endpush", 'pushonce', 'endpush'],
        'prepend' => ["@prepend('head') body @endprepend", 'prepend', 'endprepend'],
        'prepend closed by endprependOnce' => ["@prepend('head') body @endprependOnce", 'prepend', 'endprependonce'],
        'prependOnce' => ["@prependOnce('head') body @endprependOnce", 'prependonce', 'endprependonce'],
        'component' => ["@component('mail::message') body @endcomponent", 'component', 'endcomponent'],
        'component closed by endcomponentClass' => ["@component('a') body @endcomponentClass", 'component', 'endcomponentclass'],
    ]);

    test('leaves no loose directives behind', function (string $input): void {
        $doc = $this->parse($input);

        $loose = [];
        $doc->getDirectives()->each(function ($directive) use (&$loose): void {
            $loose[] = $directive->nameText();
        });

        expect($loose)->toBe([]);
    })->with([
        "@push('scripts') body @endpush",
        "@prepend('head') body @endprepend",
        "@component('mail::message') body @endcomponent",
    ]);

    test('nests inside another block', function (): void {
        $input = "@once @push('scripts') body @endpush @endonce";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('once')
            ->and($doc->render())->toBe($input);

        $pushBlocks = [];
        $doc->getBlockDirectives()->each(function (DirectiveBlockNode $block) use (&$pushBlocks): void {
            if ($block->nameText() === 'push') {
                $pushBlocks[] = $block;
            }
        });

        expect($pushBlocks)->toHaveCount(1)
            ->and($pushBlocks[0]->endDirective())->not->toBeNull();
    });

    test('still reports an unclosed opener', function (): void {
        $doc = $this->parse("@push('scripts') body");

        $blocks = [];
        $doc->getBlockDirectives()->each(function (DirectiveBlockNode $block) use (&$blocks): void {
            $blocks[] = $block;
        });

        // Either left loose or opened without a terminator; what must not happen
        // is a block that claims to be closed.
        foreach ($blocks as $block) {
            expect($block->endDirective())->toBeNull();
        }

        expect($doc->render())->toBe("@push('scripts') body");
    });

    test('two sibling pushes each pair independently', function (): void {
        $input = "@push('a') 1 @endpush @push('b') 2 @endpush";

        $doc = $this->parse($input);

        $blocks = [];
        $doc->getBlockDirectives()->each(function (DirectiveBlockNode $block) use (&$blocks): void {
            $blocks[] = $block->nameText();
        });

        expect($blocks)->toBe(['push', 'push'])
            ->and($doc->render())->toBe($input);
    });
});

/**
 * Nobody should write most of these, but the parser has to survive them without
 * losing content or mispairing the blocks it does build.
 */
describe('mixed and nested terminator usage', function (): void {
    /**
     * @return array<string> Block names paired with the terminator that closed them
     */
    function blockPairs(string $input): array
    {
        $pairs = [];

        test()->parse($input)->getBlockDirectives()->each(function (DirectiveBlockNode $block) use (&$pairs): void {
            $pairs[] = $block->nameText().'/'.($block->endDirective()?->nameText() ?? 'unclosed');
        });

        return $pairs;
    }

    /**
     * @return array<string>
     */
    function looseDirectives(string $input): array
    {
        $loose = [];

        test()->parse($input)->getDirectives()->each(function ($directive) use (&$loose): void {
            $loose[] = $directive->nameText();
        });

        return $loose;
    }

    test('pairs nested blocks whichever terminator variant closes them', function (string $input, array $expected): void {
        expect(blockPairs($input))->toBe($expected)
            ->and(looseDirectives($input))->toBe([]);
    })->with([
        'inner endpush, outer endpushOnce' => [
            "@push('a') @push('b') x @endpush @endpushOnce",
            ['push/endpushonce', 'push/endpush'],
        ],
        'inner endpushOnce, outer endpush' => [
            "@push('a') @push('b') x @endpushOnce @endpush",
            ['push/endpush', 'push/endpushonce'],
        ],
        'pushOnce nested in push' => [
            "@push('a') @pushOnce('b') x @endpushOnce @endpush",
            ['push/endpush', 'pushonce/endpushonce'],
        ],
        'push nested in pushOnce' => [
            "@pushOnce('a') @push('b') x @endpush @endpushOnce",
            ['pushonce/endpushonce', 'push/endpush'],
        ],
        'three deep' => [
            "@push('a') @push('b') @push('c') x @endpush @endpush @endpush",
            ['push/endpush', 'push/endpush', 'push/endpush'],
        ],
        'siblings using different variants' => [
            "@push('a') 1 @endpush @push('b') 2 @endpushOnce",
            ['push/endpush', 'push/endpushonce'],
        ],
    ]);

    test('pairs blocks nested across directive families', function (string $input, array $expected): void {
        expect(blockPairs($input))->toBe($expected)
            ->and(looseDirectives($input))->toBe([]);
    })->with([
        'prepend in push' => ["@push('a') @prepend('b') x @endprepend @endpush", ['push/endpush', 'prepend/endprepend']],
        'push in prepend' => ["@prepend('a') @push('b') x @endpush @endprepend", ['prepend/endprepend', 'push/endpush']],
        'push in component' => ["@component('c') @push('a') x @endpush @endcomponent", ['component/endcomponent', 'push/endpush']],
        'component in push' => ["@push('a') @component('c') x @endcomponentClass @endpush", ['push/endpush', 'component/endcomponentclass']],
        'push in if' => ['@if($a) @push("s") x @endpush @endif', ['if/endif', 'push/endpush']],
        'if in push' => ['@push("s") @if($a) x @endif @endpush', ['push/endpush', 'if/endif']],
        'push in once' => ['@once @push("s") x @endpush @endonce', ['once/endonce', 'push/endpush']],
        'once in push' => ['@push("s") @once x @endonce @endpush', ['push/endpush', 'once/endonce']],
    ]);

    test('leaves an unmatched opener unclosed rather than stealing a terminator', function (string $input, array $blocks, array $loose): void {
        expect(blockPairs($input))->toBe($blocks)
            ->and(looseDirectives($input))->toBe($loose);
    })->with([
        // The inner push claims the only terminator; the outer stays open.
        'two openers, one terminator' => [
            "@push('a') @push('b') x @endpush",
            ['push/unclosed', 'push/endpush'],
            [],
        ],
        'one opener, two terminators' => [
            "@push('a') x @endpush @endpush",
            ['push/endpush'],
            ['endpush'],
        ],
        // endprepend does not close a push, and vice versa.
        'push closed by the prepend terminator' => [
            "@push('a') x @endprepend",
            ['push/unclosed'],
            ['endprepend'],
        ],
        'prepend closed by the push terminator' => [
            "@prepend('a') x @endpush",
            ['prepend/unclosed'],
            ['endpush'],
        ],
        // A push opened inside @if cannot be closed outside it.
        'terminator outside the enclosing block' => [
            '@if($a) @push("s") x @endif @endpush',
            ['if/endif', 'push/unclosed'],
            ['endpush'],
        ],
    ]);

    test('round-trips and stays free of parse errors', function (string $input): void {
        $doc = $this->parse($input);

        expect($doc->render())->toBe($input)
            ->and($doc->hasErrors())->toBeFalse();
    })->with([
        "@push('a') @push('b') x @endpush @endpushOnce",
        "@push('a') @pushOnce('b') x @endpushOnce @endpush",
        "@push('a') @prepend('b') x @endprepend @endpush",
        "@component('c') @push('a') x @endpush @endcomponent",
        "@push('a') @push('b') x @endpush",
        "@push('a') x @endpush @endpush",
        "@push('a') x @endprepend",
        '@if($a) @push("s") x @endif @endpush',
        '@once @push("s") x @endpush @endonce',
        "@push('a') @push('b') @push('c') x @endpush @endpush @endpush",
    ]);
});

describe('primary terminator resolution', function (): void {
    test('names the first end-style terminator', function (string $directive, string $expected): void {
        expect(Directives::withDefaults()->getTerminator($directive))->toBe($expected);
    })->with([
        // Closers listed first; the primary is the first, not the last.
        'push' => ['push', 'endpush'],
        'prepend' => ['prepend', 'endprepend'],
        'component' => ['component', 'endcomponent'],
        // Branch keyword listed first; the primary is still the closer.
        'if' => ['if', 'endif'],
        'auth' => ['auth', 'endauth'],
        'once' => ['once', 'endonce'],
        // Section-style lists resolve as they always did.
        'section' => ['section', 'endsection'],
        // Single-terminator directives are unaffected.
        'foreach' => ['foreach', 'endforeach'],
        'forelse' => ['forelse', 'endforelse'],
    ]);

    test('registers every stack terminator', function (string $terminator): void {
        expect(Directives::withDefaults()->isDirective($terminator))->toBeTrue();
    })->with(['endpush', 'endpushonce', 'endprepend', 'endprependonce']);
});
