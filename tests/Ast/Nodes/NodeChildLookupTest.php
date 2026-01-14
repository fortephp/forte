<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Node Child Lookup', function (): void {
    describe('childAt()', function (): void {
        it('returns child at valid index', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0]->asElement();

            expect($div->childAt(0)->tagNameText())->toBe('a')
                ->and($div->childAt(1)->tagNameText())->toBe('b')
                ->and($div->childAt(2)->tagNameText())->toBe('c');
        });

        it('returns null for negative index', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];

            expect($div->childAt(-1))->toBeNull();
        });

        it('returns null for out of bounds index', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];

            expect($div->childAt(2))->toBeNull()
                ->and($div->childAt(100))->toBeNull();
        });

        it('returns null for element with no children', function (): void {
            $doc = $this->parse('<div></div>');
            $div = $doc->getChildren()[0];

            expect($div->childAt(0))->toBeNull();
        });

        it('works with mixed node types', function (): void {
            $doc = $this->parse('<p>Text<span></span>More</p>');
            $p = $doc->getChildren()[0];

            expect($p->childAt(0))->toBeInstanceOf(TextNode::class)
                ->and($p->childAt(1))->toBeInstanceOf(ElementNode::class)
                ->and($p->childAt(2))->toBeInstanceOf(TextNode::class);
        });
    });

    describe('firstChildWhere()', function (): void {
        it('finds first child matching predicate', function (): void {
            $doc = $this->parse('<div>Text<a></a><b></b></div>');
            $div = $doc->getChildren()[0];

            $firstElement = $div
                ->firstChildWhere(fn ($n) => $n instanceof ElementNode)
                ->asElement();

            expect($firstElement)->not->toBeNull()
                ->and($firstElement->tagNameText())->toBe('a');
        });

        it('returns null when no child matches', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];

            $text = $div->firstChildWhere(fn ($n) => $n instanceof TextNode);
            expect($text)->toBeNull();
        });

        it('finds first among multiple matches', function (): void {
            $doc = $this->parse('<ul><li>1</li><li>2</li><li>3</li></ul>');
            $ul = $doc->getChildren()[0];

            $firstLi = $ul->firstChildWhere(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'li'
            );

            expect($firstLi)->not->toBeNull();
            $children = $ul->getChildren();
            expect($firstLi)->toBe($children[0]);
        });

        it('supports complex predicates', function (): void {
            $doc = $this->parse('<div><span class="a"></span><span class="b"></span></div>');
            $div = $doc->getChildren()[0];

            $withClassB = $div
                ->firstChildWhere(fn ($n) => $n instanceof ElementNode && $n->getAttribute('class') === 'b')
                ->asElement();

            expect($withClassB)->not->toBeNull()
                ->and($withClassB->getAttribute('class'))->toBe('b');
        });
    });

    describe('lastChildWhere()', function (): void {
        it('finds last child matching predicate', function (): void {
            $doc = $this->parse('<div><a></a><b></b>Text</div>');
            $div = $doc->getChildren()[0];

            $lastElement = $div
                ->lastChildWhere(fn ($n) => $n instanceof ElementNode)
                ->asElement();

            expect($lastElement)->not->toBeNull()
                ->and($lastElement->tagNameText())->toBe('b');
        });

        it('returns null when no child matches', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];

            $text = $div->lastChildWhere(fn ($n) => $n instanceof TextNode);
            expect($text)->toBeNull();
        });

        it('finds last among multiple matches', function (): void {
            $doc = $this->parse('<ul><li>1</li><li>2</li><li>3</li></ul>');
            $ul = $doc->getChildren()[0];

            $lastLi = $ul->lastChildWhere(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'li'
            );

            expect($lastLi)->not->toBeNull();
            $children = $ul->getChildren();
            expect($lastLi)->toBe($children[2]);
        });
    });

    describe('childrenWhere()', function (): void {
        it('returns all children matching predicate', function (): void {
            $doc = $this->parse('<div>Text<a></a>More<b></b>End</div>');
            $div = $doc->getChildren()[0];

            $elements = $div->getChildrenWhere(fn ($n) => $n instanceof ElementNode);

            expect($elements)->toHaveCount(2)
                ->and($elements[0]->tagNameText())->toBe('a')
                ->and($elements[1]->tagNameText())->toBe('b');
        });

        it('returns empty when no children match', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];

            $texts = $div->getChildrenWhere(fn ($n) => $n instanceof TextNode);

            expect($texts)->toHaveCount(0);
        });

        it('preserves order', function (): void {
            $doc = $this->parse('<ul><li>1</li><li>2</li><li>3</li></ul>');
            $ul = $doc->getChildren()[0];

            /** @var ElementNode[] $lis */
            $lis = $ul->getChildrenWhere(fn ($n) => $n instanceof ElementNode);

            expect($lis)->toHaveCount(3)
                ->and(trim((string) $lis[0]->getChildren()[0]->asText()->getContent()))->toBe('1')
                ->and(trim((string) $lis[1]->getChildren()[0]->asText()->getContent()))->toBe('2')
                ->and(trim((string) $lis[2]->getChildren()[0]->asText()->getContent()))->toBe('3');
        });
    });

    describe('firstChildOfType()', function (): void {
        it('finds first child of specific type', function (): void {
            $doc = $this->parse('<div>Text<span></span></div>');
            $div = $doc->getChildren()[0];

            $text = $div->firstChildOfType(TextNode::class);
            $element = $div->firstChildOfType(ElementNode::class);

            expect($text)->toBeInstanceOf(TextNode::class)
                ->and($element)->toBeInstanceOf(ElementNode::class)
                ->and($element->tagNameText())->toBe('span');
        });

        it('returns null when no child of type exists', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];

            $text = $div->firstChildOfType(TextNode::class);
            expect($text)->toBeNull();
        });
    });

    describe('lastChildOfType()', function (): void {
        it('finds last child of specific type', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];

            $lastElement = $div->lastChildOfType(ElementNode::class);

            expect($lastElement)->toBeInstanceOf(ElementNode::class)
                ->and($lastElement->tagNameText())->toBe('c');
        });
    });

    describe('childrenOfType()', function (): void {
        it('returns all children of specific type', function (): void {
            $doc = $this->parse('<div>Text<a></a>More<b></b></div>');
            $div = $doc->getChildren()[0];

            $elements = $div->getChildrenOfType(ElementNode::class);
            $texts = $div->getChildrenOfType(TextNode::class);

            expect($elements)->toHaveCount(2)
                ->and($texts)->toHaveCount(2);
        });
    });

    describe('with Blade directives', function (): void {
        it('finds directive children', function (): void {
            $doc = $this->parse(
                '<div>@if($a) A @endif @if($b) B @endif</div>',
                ParserOptions::defaults()
            );
            $div = $doc->getChildren()[0];

            $directives = $div->getChildrenOfType(DirectiveBlockNode::class);

            expect($directives)->toHaveCount(2);
        });

        it('finds echo nodes', function (): void {
            $doc = $this->parse(
                '<p>{{ $first }} text {{ $second }}</p>',
                ParserOptions::defaults()
            );
            $p = $doc->getChildren()[0];

            $echos = $p->getChildrenOfType(EchoNode::class);
            expect($echos)->toHaveCount(2);
        });
    });

    describe('empty parent', function (): void {
        test('all methods handle empty parent gracefully', function (): void {
            $doc = $this->parse('<div></div>');
            $div = $doc->getChildren()[0];

            expect($div->childAt(0))->toBeNull()
                ->and($div->firstChildWhere(fn () => true))->toBeNull()
                ->and($div->lastChildWhere(fn () => true))->toBeNull()
                ->and($div->getChildrenWhere(fn () => true))->toHaveCount(0)
                ->and($div->firstChildOfType(TextNode::class))->toBeNull()
                ->and($div->lastChildOfType(TextNode::class))->toBeNull()
                ->and($div->getChildrenOfType(TextNode::class))->toHaveCount(0);
        });
    });
});
