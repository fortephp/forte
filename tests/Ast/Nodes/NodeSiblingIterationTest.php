<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Node Sibling Iteration', function (): void {
    describe('siblings()', function (): void {
        it('returns all siblings excluding self', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $siblings = $children[1]->getSiblings();

            expect($siblings)->toHaveCount(2)
                ->and($siblings[0])->toBe($children[0])
                ->and($siblings[1])->toBe($children[2]);
        });

        it('returns empty for only child', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->getChildren()[0];

            $siblings = $span->getSiblings();
            expect($siblings)->toHaveCount(0);
        });

        it('returns empty for root node with no parent', function (): void {
            $doc = $this->parse('<div></div>');
            $div = $doc->getChildren()[0];

            $siblings = $div->getSiblings();
            expect($siblings)
                ->toHaveCount(0);
        });

        it('preserves document order', function (): void {
            $doc = $this->parse('<p><a></a><b></b><c></c><d></d><e></e></p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            $siblings = $children[2]->getSiblings();

            expect($siblings)->toHaveCount(4)
                ->and($siblings[0]->tagNameText())->toBe('a')
                ->and($siblings[1]->tagNameText())->toBe('b')
                ->and($siblings[2]->tagNameText())->toBe('d')
                ->and($siblings[3]->tagNameText())->toBe('e');
        });
    });

    describe('previousSiblings()', function (): void {
        it('returns siblings before self in document order', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $prev = $children[2]->getPreviousSiblings();

            expect($prev)->toHaveCount(2)
                ->and($prev[0]->tagNameText())->toBe('a')
                ->and($prev[1]->tagNameText())->toBe('b');
        });

        it('returns empty for first child', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $prev = $children[0]->getPreviousSiblings();
            expect($prev)->toHaveCount(0);
        });

        it('returns all siblings for last child', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $prev = $children[2]->getPreviousSiblings();
            expect($prev)->toHaveCount(2);
        });
    });

    describe('nextSiblings()', function (): void {
        it('returns siblings after self in document order', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $next = $children[0]->getNextSiblings();

            expect($next)->toHaveCount(2)
                ->and($next[0]->tagNameText())->toBe('b')
                ->and($next[1]->tagNameText())->toBe('c');
        });

        it('returns empty for last child', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $next = $children[1]->getNextSiblings();
            expect($next)->toHaveCount(0);
        });

        it('returns all siblings for first child', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $next = $children[0]->getNextSiblings();
            expect($next)->toHaveCount(2);
        });
    });

    describe('nextSiblingWhere()', function (): void {
        it('finds next sibling matching predicate', function (): void {
            $doc = $this->parse('<p>Text<a></a>More<b></b></p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            $nextElement = $children[0]
                ->nextSiblingWhere(fn ($n) => $n instanceof ElementNode)
                ->asElement();

            expect($nextElement)->not->toBeNull()
                ->and($nextElement->tagNameText())->toBe('a');
        });

        it('skips non-matching siblings', function (): void {
            $doc = $this->parse('<p><span></span><div></div><article></article></p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            $article = $children[0]->nextSiblingWhere(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'article'
            )->asElement();

            expect($article)->not->toBeNull()
                ->and($article->tagNameText())->toBe('article');
        });

        it('returns null when no match found', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->nextSiblingWhere(fn ($n) => $n instanceof TextNode))->toBeNull();
        });
    });

    describe('previousSiblingWhere()', function (): void {
        it('finds previous sibling matching predicate', function (): void {
            $doc = $this->parse('<p><a></a>Text<b></b>More</p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            $prevElement = $children[3]
                ->previousSiblingWhere(fn ($n) => $n instanceof ElementNode)->asElement();

            expect($prevElement)->not->toBeNull()
                ->and($prevElement->tagNameText())->toBe('b');
        });

        it('returns null when no match found', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[1]->previousSiblingWhere(fn ($n) => $n instanceof TextNode))
                ->toBeNull();
        });
    });

    describe('nextSiblingOfType()', function (): void {
        it('finds next sibling of specific type', function (): void {
            $doc = $this->parse('<p>Text<span></span></p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            $span = $children[0]
                ->nextSiblingOfType(ElementNode::class)
                ->asElement();

            expect($span)->not->toBeNull()
                ->and($span)->toBeInstanceOf(ElementNode::class)
                ->and($span->tagNameText())->toBe('span');
        });

        it('returns null when no sibling of type exists', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->nextSiblingOfType(TextNode::class))
                ->toBeNull();
        });
    });

    describe('previousSiblingOfType()', function (): void {
        it('finds previous sibling of specific type', function (): void {
            $doc = $this->parse('<p><span></span>Text</p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            $span = $children[1]
                ->previousSiblingOfType(ElementNode::class)
                ->asElement();

            expect($span)->not->toBeNull()
                ->and($span)->toBeInstanceOf(ElementNode::class)
                ->and($span->tagNameText())->toBe('span');
        });
    });

    describe('with Blade directives', function (): void {
        it('can find directive siblings', function (): void {
            $doc = $this->parse(
                '<div>@if($a) A @endif @foreach($b as $c) B @endforeach</div>',
                ParserOptions::defaults()
            );
            $div = $doc->getChildren()[0];

            $firstDirective = $div->firstChildOfType(DirectiveBlockNode::class);
            expect($firstDirective)->not->toBeNull();

            $nextDirective = $firstDirective->nextSiblingOfType(DirectiveBlockNode::class);
            expect($nextDirective)->not->toBeNull()
                ->and($nextDirective)->toBeInstanceOf(DirectiveBlockNode::class);
        });
    });
});
