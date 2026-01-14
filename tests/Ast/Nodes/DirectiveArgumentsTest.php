<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\TextNode;

describe('Directive Argument Parsing', function (): void {
    test('directive arguments containing parenthesis', function (): void {
        $template = '@php($conditionOne || ($conditionTwo && $conditionThree))';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $directive = $nodes[0]->asDirective();
        expect($nodes)->toHaveCount(1)
            ->and($directive)->toBeInstanceOf(DirectiveNode::class)
            ->and($directive->arguments())->toBe('($conditionOne || ($conditionTwo && $conditionThree))');
    });

    test('directive arguments containing unbalanced parenthesis inside strings', function (): void {
        $template = '@php($conditionOne || ($conditionTwo && $conditionThree) || "(((((" != ")) (( )) )))")';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $directive = $nodes[0]->asDirective();
        expect($nodes)->toHaveCount(1)
            ->and($directive)->toBeInstanceOf(DirectiveNode::class)
            ->and($directive->arguments())->toBe('($conditionOne || ($conditionTwo && $conditionThree) || "(((((" != ")) (( )) )))")');
    });

    test('directive arguments containing php line comments', function (): void {
        $template = <<<'EOT'
@isset(
        $records // @isset())2
        )
// $records is defined and is not null...
@endisset
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $block = $nodes[0]->asDirectiveBlock();
        $issetDirective = $block->childAt(0)->asDirective();
        $endissetDirective = $block->childAt(1)->asDirective();
        $innerText = $issetDirective->firstChild()->asText();

        expect($nodes)->toHaveCount(1)
            ->and($block)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($block->getChildren())->toHaveCount(2)
            ->and($issetDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($issetDirective->nameText())->toBe('isset')
            ->and($issetDirective->arguments())->toBe("(\n        \$records // @isset())2\n        )")
            ->and($issetDirective->getChildren())->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and($innerText->getContent())->toBe("\n// \$records is defined and is not null...\n")
            ->and($endissetDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endissetDirective->nameText())->toBe('endisset');
    });

    it('directive arguments containing multiline php comments', function (): void {
        $template = <<<'EOT'
@isset(
        $records /* @isset())2
        @isset(
            $records /* @isset())2
            )
    // $records is defined and is not null...
    @endisset
    */
        a)b
// $records is defined and is not null...
@endisset
EOT;

        $expectedArgs = <<<'EOT'
(
        $records /* @isset())2
        @isset(
            $records /* @isset())2
            )
    // $records is defined and is not null...
    @endisset
    */
        a)
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $block = $nodes[0]->asDirectiveBlock();
        $issetDirective = $block->childAt(0)->asDirective();
        $endissetDirective = $block->childAt(1)->asDirective();
        $innerText = $issetDirective->firstChild()->asText();

        expect($nodes)->toHaveCount(1)
            ->and($block)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($block->getChildren())->toHaveCount(2)
            ->and($issetDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($issetDirective->nameText())->toBe('isset')
            ->and($issetDirective->arguments())->toBe($expectedArgs)
            ->and($issetDirective->getChildren())->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and($innerText->getContent())->toBe("b\n// \$records is defined and is not null...\n")
            ->and($endissetDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endissetDirective->nameText())->toBe('endisset');
    });

    test('large directive arguments parse efficiently', function (): void {
        $items = array_map(fn ($i) => "'item$i'", range(1, 500));
        $array = '['.implode(', ', $items).']';
        $template = "@foreach($array as \$item){{ \$item }}@endforeach";

        $start = microtime(true);
        $doc = $this->parse($template);
        $result = $doc->render();
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.5)
            ->and($result)->toBe($template);
    });

    test('string ending with backslash in directive', function (): void {
        $template = '@php($x = "test\\")';

        $doc = $this->parse($template);
        $result = $doc->render();
        expect($result)->toBeString();
    });

    test('single space after @if before ( is preserved', function (): void {
        $template = '@if ($this) yes @endif';
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $if = $nodes[0]->asDirectiveBlock()->childAt(0)->asDirective();

        expect($if)->toBeInstanceOf(DirectiveNode::class)
            ->and($if->whitespaceBetweenNameAndArgs())->toBe(' ');
    });

    test('no space after @if has null ws metadata', function (): void {
        $template = '@if($a)@endif';
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $if = $nodes[0]->asDirectiveBlock()->childAt(0)->asDirective();

        expect($if)->toBeInstanceOf(DirectiveNode::class)
            ->and($if->whitespaceBetweenNameAndArgs())->toBeNull();
    });

    test('multiple spaces after @foreach are preserved', function (): void {
        $template = '@foreach  ($items as $i) a @endforeach';
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $foreachDirective = $nodes[0]->asDirectiveBlock()->childAt(0)->asDirective();

        expect($foreachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($foreachDirective->whitespaceBetweenNameAndArgs())->toBe('  ');
    });

    test('standalone directive also captures ws when args exist', function (): void {
        $template = '@props  (1, 2)';
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();

        $propsDirective = $nodes[0]->asDirective();
        expect($nodes)->toHaveCount(1)
            ->and($propsDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($propsDirective->whitespaceBetweenNameAndArgs())->toBe('  ')
            ->and($propsDirective->arguments())->toBe('(1, 2)')
            ->and($propsDirective->nameText())->toBe('props');
    });

    test('single space after @for before ( is preserved', function (): void {
        $template = '@for ($i = 0; $i < 10; $i++) @endfor';
        $doc = $this->parse($template);

        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $for = $nodes[0]->asDirectiveBlock()->childAt(0)->asDirective();

        expect($for)->toBeInstanceOf(DirectiveNode::class)
            ->and($for->nameText())->toBe('for')
            ->and($for->whitespaceBetweenNameAndArgs())->toBe(' ');
    });
});
