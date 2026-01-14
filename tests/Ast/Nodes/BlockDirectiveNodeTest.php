<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\TextNode;

describe('Block Directive Nodes', function (): void {
    test('section directive structure', function (): void {
        $template = "@section('content') Hello @endsection";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->arguments())->toBe("('content')")
            ->and($block->startDirective())->not->toBeNull()
            ->and($block->endDirective())->not->toBeNull();

        $children = $block->getChildren();
        expect($children)->toHaveCount(2);

        $start = $children[0]->asDirective();
        expect($start)->toBeInstanceOf(DirectiveNode::class)
            ->and($start->nameText())->toBe('section');

        $inner = $start->getChildren();
        $innerText = $inner[0]->asText();
        expect($inner)->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and($innerText->getContent())->toBe(' Hello ');

        $end = $children[1]->asDirective();
        expect($end)->toBeInstanceOf(DirectiveNode::class)
            ->and($end->nameText())->toBe('endsection');
    });

    test('if block directive structure', function (): void {
        $template = '@if ($condition) content @endif';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('if')
            ->and($block->isIf())->toBeTrue()
            ->and($block->arguments())->toBe('($condition)')
            ->and($block->startDirective())->not->toBeNull()
            ->and($block->endDirective())->not->toBeNull();

        $children = $block->getChildren();
        expect($children)->toHaveCount(2);

        $start = $children[0]->asDirective();
        expect($start)->toBeInstanceOf(DirectiveNode::class)
            ->and($start->nameText())->toBe('if');

        $inner = $start->getChildren();
        $innerText = $inner[0]->asText();
        expect($inner)->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and($innerText->getContent())->toBe(' content ');
    });

    test('foreach block directive structure', function (): void {
        $template = '@foreach ($items as $item) {{ $item }} @endforeach';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('foreach')
            ->and($block->isForeach())->toBeTrue()
            ->and($block->arguments())->toBe('($items as $item)')
            ->and($block->startDirective())->not->toBeNull()
            ->and($block->endDirective())->not->toBeNull();
    });

    test('forelse block directive with empty', function (): void {
        $template = '@forelse ($items as $item) {{ $item }} @empty No items @endforelse';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('forelse')
            ->and($block->isForelse())->toBeTrue()
            ->and($block->hasIntermediates())->toBeTrue();

        $children = $block->getChildren();
        expect($children)->toHaveCount(3);

        $forelseDirective = $children[0]->asDirective();
        $emptyDirective = $children[1]->asDirective();
        $endforelseDirective = $children[2]->asDirective();

        expect($forelseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($forelseDirective->nameText())->toBe('forelse')
            ->and($emptyDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($emptyDirective->nameText())->toBe('empty')
            ->and($endforelseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endforelseDirective->nameText())->toBe('endforelse');
    });

    test('section with show terminator', function (): void {
        $template = "@section('sidebar') content @show";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->isSection())->toBeTrue()
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('show');
    });

    test('section with stop terminator', function (): void {
        $template = "@section('scripts') content @stop";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('stop');
    });

    test('block directive render round-trips', function (): void {
        $template = "@section('content') Hello @endsection";

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    test('nested block directives', function (): void {
        $template = '@if ($a) @if ($b) inner @endif @endif';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $outer = $nodes[0]->asDirectiveBlock();
        expect($outer->nameText())->toBe('if');

        $startDirective = $outer->childAt(0)->asDirective();
        $innerIf = $startDirective->firstChildOfType(DirectiveBlockNode::class)->asDirectiveBlock();

        expect($innerIf)->not->toBeNull()
            ->and($innerIf->nameText())->toBe('if');
    });

    test('block directive with complex inner content', function (): void {
        $template = <<<'BLADE'
@section('content')
    <div class="container">
        {{ $title }}
        @if ($show)
            <p>{{ $message }}</p>
        @endif
    </div>
@endsection
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->render())->toBe($template);
    });

    test('block directive with else intermediate', function (): void {
        $template = '@if ($condition) yes @else no @endif';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $block = $nodes[0]->asDirectiveBlock();
        expect($block)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($block->hasIntermediates())->toBeTrue();

        $children = $block->getChildren();
        expect($children)->toHaveCount(3);

        $ifDirective = $children[0]->asDirective();
        $elseDirective = $children[1]->asDirective();
        $endifDirective = $children[2]->asDirective();

        expect($ifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($ifDirective->nameText())->toBe('if')
            ->and($elseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseDirective->nameText())->toBe('else')
            ->and($endifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endifDirective->nameText())->toBe('endif');
    });

    test('block directive with elseif and else', function (): void {
        $template = '@if ($a) one @elseif ($b) two @else three @endif';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $block = $nodes[0]->asDirectiveBlock();
        $children = $block->getChildren();

        $ifDirective = $children[0]->asDirective();
        $elseifDirective = $children[1]->asDirective();
        $elseDirective = $children[2]->asDirective();
        $endifDirective = $children[3]->asDirective();

        expect($children)->toHaveCount(4)
            ->and($ifDirective->nameText())->toBe('if')
            ->and($elseifDirective->nameText())->toBe('elseif')
            ->and($elseifDirective->arguments())->toBe('($b)')
            ->and($elseDirective->nameText())->toBe('else')
            ->and($endifDirective->nameText())->toBe('endif');
    });

    test('block directive closingName', function (): void {
        $template = '@if ($x) content @endif';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->endDirective()->nameText())->toBe('endif');
    });

    test('unclosed section is not a block', function (): void {
        $template = '@section("content") Hello';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $sectionDirective = $nodes[0]->asDirective();
        $textNode = $nodes[1]->asText();

        expect($nodes)->toHaveCount(2)
            ->and($sectionDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($sectionDirective->nameText())->toBe('section')
            ->and($textNode)->toBeInstanceOf(TextNode::class)
            ->and($textNode->getContent())->toBe(' Hello');
    });

    test('block directive original case preserved', function (): void {
        $template = '@IF ($x) content @ENDIF';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $block = $nodes[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('if')
            ->and($block->name())->toBe('IF')
            ->and($block->render())->toBe($template);
    });
});
