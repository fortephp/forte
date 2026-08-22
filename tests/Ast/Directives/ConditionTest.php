<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Conditional Directive Parsing', function (): void {
    it('parses simple if/endif condition', function (): void {
        $source = '@if ($count === 1) One record @endif';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $ifBlock = $children[0]->asDirectiveBlock();

        expect($ifBlock->nameText())->toBe('if')
            ->and($ifBlock->arguments())->toBe('($count === 1)');

        $ifDirective = $ifBlock->childAt(0)->asDirective();
        $endifDirective = $ifBlock->childAt(1)->asDirective();

        expect($ifBlock->getChildren())->toHaveCount(2)
            ->and($ifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($ifDirective->nameText())->toBe('if')
            ->and($ifDirective->getChildren())->toHaveCount(1)
            ->and($ifDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and($ifDirective->firstChild()->asText()->getDocumentContent())->toBe(' One record ')
            ->and($endifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endifDirective->nameText())->toBe('endif')
            ->and($endifDirective->getChildren())->toHaveCount(0)
            ->and($doc->render())->toBe($source);

    });

    it('parses if/elseif/else/endif condition', function (): void {
        $source = '@if ($count === 1) One @elseif ($count > 1) Many @else None @endif';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('if');

        /** @var DirectiveNode[] $directives */
        $directives = $children[0]->nodes()->directives()->all();

        expect(count($directives))->toBe(4)
            ->and($directives[0]->nameText())->toBe('if')
            ->and($directives[1]->nameText())->toBe('elseif')
            ->and($directives[2]->nameText())->toBe('else')
            ->and($directives[3]->nameText())->toBe('endif')
            ->and($directives[0]->getChildren())->not->toBeEmpty()
            ->and($directives[1]->getChildren())->not->toBeEmpty()
            ->and($directives[2]->getChildren())->not->toBeEmpty()
            ->and($directives[3]->getChildren())->toBeEmpty()
            ->and($doc->render())->toBe($source);
    });

    it('handles mixed condition terminators', function (): void {
        $source = '@if ($this) Content @endunless';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('if');

        /** @var DirectiveNode[] $directives */
        $directives = $children[0]->nodes()->directives()->all();
        expect($directives)->toHaveCount(2)
            ->and($directives[0]->nameText())->toBe('if')
            ->and($directives[1]->nameText())->toBe('endunless')
            ->and($doc->render())->toBe($source);
    });

    it('handles mixed condition branches', function (): void {
        $source = '@if ($this) One @elsecan ("edit") Two @else Three @endif';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        /** @var DirectiveNode[] $directives */
        $directives = $children[0]->nodes()->directives()->all();
        expect($directives)->toHaveCount(4)
            ->and($doc->render())->toBe($source);
    });

    it('handles nested conditions', function (): void {
        $source = '@if ($outer) Outer @if ($inner) Inner @endif Back @endif';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('if');

        $outerBlockChildren = $children[0]->getChildren();
        $outerIfDirective = $outerBlockChildren[0]->asDirective();
        expect($outerIfDirective)
            ->toBeInstanceOf(DirectiveNode::class);

        $innerBlocks = $outerIfDirective->getChildrenOfType(DirectiveBlockNode::class);
        expect($innerBlocks)->toHaveCount(1);

        $innerBlock = $innerBlocks[0]->asDirectiveBlock();
        expect($innerBlock->nameText())->toBe('if')
            ->and($innerBlock->arguments())->toBe('($inner)')
            ->and($doc->render())->toBe($source);
    });

    it('handles condition with elements', function (): void {
        $source = '@if ($show) <div> Content </div> @endif';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockChildren = $children[0]->getChildren();
        $ifDirective = $blockChildren[0];
        expect($ifDirective)->toBeInstanceOf(DirectiveNode::class);

        $elements = $ifDirective->getChildrenOfType(ElementNode::class);
        expect($elements)->toHaveCount(1);

        $div = $elements[0]->asElement();
        expect($div->tagNameText())->toBe('div')
            ->and($doc->render())->toBe($source);

    });

    it('auto-closes elements between branches', function (): void {
        $source = '@if ($a) <div> One @else Two </div> @endif';
        $doc = $this->parse($source);

        expect($doc->render())->toBe($source);

        $children = $doc->getChildren();
        expect($children)->toHaveCount(1)
            ->and($children[0])
            ->toBeInstanceOf(DirectiveBlockNode::class);
    });

    it('handles unless condition', function (): void {
        $source = '@unless ($isAdmin) Not admin @endunless';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('unless');

        /** @var DirectiveNode[] $directives */
        $directives = $children[0]->nodes()->directives()->all();
        expect(count($directives))->toBe(2)
            ->and($directives[0]->nameText())->toBe('unless')
            ->and($directives[1]->nameText())->toBe('endunless')
            ->and($doc->render())->toBe($source);
    });

    it('parses complex nested conditions with multiple branches', function (): void {
        $source = <<<'BLADE'
@if ($level === 1)
    Level 1
    @if ($nested)
        Nested
    @else
        Not nested
    @endif
@elseif ($level === 2)
    Level 2
@else
    Default
@endif
BLADE;

        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('if');

        $outerIfDirective = $children[0]->asDirectiveBlock()->childAt(0)->asDirective();

        $nestedBlocks = $outerIfDirective->getChildrenOfType(DirectiveBlockNode::class);
        expect($nestedBlocks)->toHaveCount(1)
            ->and($doc->render())->toBe($source);
    });

    it('handles multiple separate conditions', function (): void {
        $source = '@if ($a) A @endif Text @if ($b) B @endif';
        $doc = $this->parse($source);

        /** @var DirectiveBlockNode[] $ifBlocks */
        $ifBlocks = $doc->getChildrenOfType(DirectiveBlockNode::class);
        expect($ifBlocks)->toHaveCount(2)
            ->and($ifBlocks[0]->nameText())->toBe('if')
            ->and($ifBlocks[0]->arguments())->toBe('($a)')
            ->and($ifBlocks[1]->nameText())->toBe('if')
            ->and($ifBlocks[1]->arguments())->toBe('($b)');

        $textNodes = $doc->getChildrenOfType(TextNode::class);
        expect(count($textNodes))->toBeGreaterThanOrEqual(1)
            ->and($doc->render())->toBe($source);
    });

    it('parses the parenthesized empty directive as a condition', function (): void {
        $source = '@empty($records) Empty @else Not empty @endempty';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('empty')
            ->and($children[0]->asDirectiveBlock()->arguments())->toBe('($records)');

        $directives = $children[0]->nodes()->directives()->all();

        expect($directives)->toHaveCount(3)
            ->and($directives[0]->nameText())->toBe('empty')
            ->and($directives[1]->nameText())->toBe('else')
            ->and($directives[2]->nameText())->toBe('endempty')
            ->and($doc->render())->toBe($source);
    });

    it('distinguishes parenthesized empty conditions from forelse empty branches', function (): void {
        $source = <<<'BLADE'
@forelse($records as $record)
    @empty($record->name)
        Missing name
    @else
        {{ $record->name }}
    @endempty
@empty
    No records
@endforelse
BLADE;
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('forelse');

        $forelse = $children[0]->asDirectiveBlock();
        $forelseDirectives = $forelse->nodes()->directives()->values();
        $nestedEmptyBlocks = $forelse->allOfType(DirectiveBlockNode::class)
            ->filter(fn (DirectiveBlockNode $block) => $block->nameText() === 'empty')
            ->values();

        expect($forelseDirectives)->toHaveCount(3)
            ->and($forelseDirectives[0]->nameText())->toBe('forelse')
            ->and($forelseDirectives[1]->nameText())->toBe('empty')
            ->and($forelseDirectives[1]->arguments())->toBeNull()
            ->and($forelseDirectives[2]->nameText())->toBe('endforelse')
            ->and($nestedEmptyBlocks)->toHaveCount(1)
            ->and($nestedEmptyBlocks[0]->nameText())->toBe('empty')
            ->and($nestedEmptyBlocks[0]->arguments())->toBe('($record->name)')
            ->and($doc->render())->toBe($source);
    });

    it('retains an unterminated parenthesized empty as the forelse branch for recovery', function (): void {
        $source = '@forelse($records as $record) {{ $record }} @empty($records) None @endforelse';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('forelse');

        $directives = $children[0]->nodes()->directives()->values();

        expect($directives)->toHaveCount(3)
            ->and($directives[0]->nameText())->toBe('forelse')
            ->and($directives[1]->nameText())->toBe('empty')
            ->and($directives[1]->arguments())->toBe('($records)')
            ->and($directives[1]->isIntermediate())->toBeTrue()
            ->and($directives[2]->nameText())->toBe('endforelse')
            ->and($doc->render())->toBe($source);
    });

    it('keeps nested empty branches inside their containing condition', function (): void {
        $source = <<<'BLADE'
@if ($show)
    Visible
@else
    @empty($first)
        First is empty
    @else
        First is present
    @endempty

    @empty($second)
        Second is empty
    @else
        Second is present
    @endempty
@endif
BLADE;
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('if');

        $outerBlock = $children[0]->asDirectiveBlock();
        $outerDirectives = $outerBlock->nodes()->directives()->values();
        $emptyBlocks = $outerBlock->allOfType(DirectiveBlockNode::class)
            ->filter(fn (DirectiveBlockNode $block) => $block->nameText() === 'empty')
            ->values();

        expect($outerDirectives)->toHaveCount(3)
            ->and($outerDirectives[0]->nameText())->toBe('if')
            ->and($outerDirectives[1]->nameText())->toBe('else')
            ->and($outerDirectives[2]->nameText())->toBe('endif')
            ->and($emptyBlocks)->toHaveCount(2)
            ->and($emptyBlocks[0]->nameText())->toBe('empty')
            ->and($emptyBlocks[1]->nameText())->toBe('empty')
            ->and($doc->render())->toBe($source);
    });
});
