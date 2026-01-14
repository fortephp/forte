<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Node Navigation', function (): void {
    describe('getParent()', function (): void {
        test('root level nodes return null for parent', function (): void {
            $doc = $this->parse('<div>Hello</div>');
            $nodes = $doc->getChildren();

            expect($nodes[0]->getParent())->toBeNull();
        });

        test('child nodes return correct parent', function (): void {
            $doc = $this->parse('<div><span>Hello</span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->getChildren()[0];

            expect($span)->toBeInstanceOf(ElementNode::class)
                ->and($span->getParent())->toBe($div);
        });

        test('deeply nested nodes return correct parent', function (): void {
            $doc = $this->parse('<div><p><span>Hello</span></p></div>');
            $div = $doc->getChildren()[0];
            $p = $div->getChildren()[0];
            $span = $p->getChildren()[0];
            $text = $span->getChildren()[0];

            expect($text->getParent())->toBe($span)
                ->and($span->getParent())->toBe($p)
                ->and($p->getParent())->toBe($div);
        });
    });

    describe('nextSibling()', function (): void {
        it('returns null for last sibling', function (): void {
            $doc = $this->parse('<div>Hello</div>');
            $nodes = $doc->getChildren();
            $div = $nodes[0];

            expect($div->nextSibling())->toBeNull();
        });

        it('returns next sibling element', function (): void {
            $doc = $this->parse('<div></div><span></span>');
            $nodes = $doc->getChildren();
            $div = $nodes[0];
            $span = $nodes[1];

            expect($div->nextSibling())->toBe($span)
                ->and($span->nextSibling())->toBeNull();
        });

        it('returns next sibling text node', function (): void {
            $doc = $this->parse('<div></div>Hello');
            $nodes = $doc->getChildren();

            expect($nodes[0]->nextSibling())->toBeInstanceOf(TextNode::class)
                ->and($nodes[0]->nextSibling()->getContent())->toBe('Hello');
        });

        it('works with mixed content', function (): void {
            $doc = $this->parse('<p>One<span>Two</span>Three</p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            expect($children[0])->toBeInstanceOf(TextNode::class)
                ->and($children[0]->nextSibling())->toBe($children[1])
                ->and($children[1]->nextSibling())->toBe($children[2])
                ->and($children[2]->nextSibling())->toBeNull();
        });
    });

    describe('previousSibling()', function (): void {
        it('returns null for first sibling', function (): void {
            $doc = $this->parse('<div>Hello</div>');
            $nodes = $doc->getChildren();

            expect($nodes[0]->previousSibling())
                ->toBeNull();
        });

        it('returns previous sibling element', function (): void {
            $doc = $this->parse('<div></div><span></span>');
            $nodes = $doc->getChildren();
            $div = $nodes[0];
            $span = $nodes[1];

            expect($span->previousSibling())->toBe($div)
                ->and($div->previousSibling())->toBeNull();
        });

        it('returns previous sibling text node', function (): void {
            $doc = $this->parse('Hello<div></div>');
            $nodes = $doc->getChildren();

            expect($nodes[1]->previousSibling())->toBeInstanceOf(TextNode::class)
                ->and($nodes[1]->previousSibling()->asText()->getContent())->toBe('Hello');
        });

        it('works with mixed content', function (): void {
            $doc = $this->parse('<p>One<span>Two</span>Three</p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            expect($children[2]->previousSibling())->toBe($children[1])
                ->and($children[1]->previousSibling())->toBe($children[0])
                ->and($children[0]->previousSibling())->toBeNull();
        });
    });

    describe('sibling chain navigation', function (): void {
        it('can traverse entire sibling chain forward', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $current = $children[0]->asElement();
            $visited = [$current->tagNameText()];

            while ($next = $current->nextSibling()) {
                $visited[] = $next->asElement()->tagNameText();
                $current = $next;
            }

            expect($visited)->toBe(['a', 'b', 'c']);
        });

        it('can traverse entire sibling chain backward', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            $current = $children[2]->asElement();
            $visited = [$current->tagNameText()];

            while ($prev = $current->previousSibling()) {
                $visited[] = $prev->asElement()->tagNameText();
                $current = $prev;
            }

            expect($visited)->toBe(['c', 'b', 'a']);
        });
    });

    describe('with Blade directives', function (): void {
        test('directives have correct siblings', function (): void {
            $doc = $this->parse(
                'Before @if($cond) Middle @endif After',
                ParserOptions::defaults()
            );
            $nodes = $doc->getChildren();

            expect($nodes)->toHaveCount(3)
                ->and($nodes[0])->toBeInstanceOf(TextNode::class)
                ->and($nodes[0]->asText()->getContent())->toBe('Before ')
                ->and($nodes[0]->nextSibling())->toBe($nodes[1])
                ->and($nodes[1]->nextSibling())->toBe($nodes[2])
                ->and($nodes[2]->nextSibling())->toBeNull()
                ->and($nodes[2]->previousSibling())->toBe($nodes[1])
                ->and($nodes[1]->previousSibling())->toBe($nodes[0])
                ->and($nodes[0]->previousSibling())->toBeNull();
        });
    });
});
