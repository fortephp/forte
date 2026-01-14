<?php

declare(strict_types=1);

use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Node Sibling Predicates', function (): void {
    describe('isFirstChild()', function (): void {
        it('returns true for first child of parent', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->isFirstChild())->toBeTrue()
                ->and($children[1]->isFirstChild())->toBeFalse()
                ->and($children[2]->isFirstChild())->toBeFalse();
        });

        it('returns true for only child', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->firstChild();

            expect($span->isFirstChild())->toBeTrue();
        });

        it('returns true for root-level node with no previous sibling', function (): void {
            $doc = $this->parse('<div></div><span></span>');
            $nodes = $doc->getChildren();

            expect($nodes[0]->isFirstChild())->toBeTrue()
                ->and($nodes[1]->isFirstChild())->toBeFalse();
        });

        it('works with mixed node types', function (): void {
            $doc = $this->parse('<p>Text<span></span></p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            expect($children[0])->toBeInstanceOf(TextNode::class)
                ->and($children[0]->isFirstChild())->toBeTrue()
                ->and($children[1]->isFirstChild())->toBeFalse();
        });
    });

    describe('isLastChild()', function (): void {
        it('returns true for last child of parent', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->isLastChild())->toBeFalse()
                ->and($children[1]->isLastChild())->toBeFalse()
                ->and($children[2]->isLastChild())->toBeTrue();
        });

        it('returns true for only child', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->firstChild();

            expect($span->isLastChild())->toBeTrue();
        });

        it('returns true for root-level node with no next sibling', function (): void {
            $doc = $this->parse('<div></div><span></span>');
            $nodes = $doc->getChildren();

            expect($nodes[0]->isLastChild())->toBeFalse()
                ->and($nodes[1]->isLastChild())->toBeTrue();
        });

        it('works with trailing text', function (): void {
            $doc = $this->parse('<p><span></span>Trailing</p>');
            $p = $doc->getChildren()[0];
            $children = $p->getChildren();

            expect($children[0]->isLastChild())->toBeFalse()
                ->and($children[1])->toBeInstanceOf(TextNode::class)
                ->and($children[1]->isLastChild())->toBeTrue();
        });
    });

    describe('isOnlyChild()', function (): void {
        it('returns true when node is the only child', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->firstChild();

            expect($span->isOnlyChild())->toBeTrue();
        });

        it('returns false when there are siblings', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->isOnlyChild())->toBeFalse()
                ->and($children[1]->isOnlyChild())->toBeFalse();
        });

        it('returns true for single root element', function (): void {
            $doc = $this->parse('<div></div>');
            $nodes = $doc->getChildren();

            expect($nodes[0]->isOnlyChild())->toBeTrue();
        });

        it('returns false for multiple root elements', function (): void {
            $doc = $this->parse('<div></div><span></span>');
            $nodes = $doc->getChildren();

            expect($nodes[0]->isOnlyChild())->toBeFalse()
                ->and($nodes[1]->isOnlyChild())->toBeFalse();
        });
    });

    describe('hasPreviousSibling()', function (): void {
        it('returns false for first child', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->hasPreviousSibling())
                ->toBeFalse();
        });

        it('returns true for non-first children', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[1]->hasPreviousSibling())->toBeTrue()
                ->and($children[2]->hasPreviousSibling())->toBeTrue();
        });
    });

    describe('hasNextSibling()', function (): void {
        it('returns false for last child', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[1]->hasNextSibling())
                ->toBeFalse();
        });

        it('returns true for non-last children', function (): void {
            $doc = $this->parse('<div><a></a><b></b><c></c></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->hasNextSibling())->toBeTrue()
                ->and($children[1]->hasNextSibling())->toBeTrue();
        });
    });

    describe('with Blade directives', function (): void {
        it('predicates work with directive blocks', function (): void {
            $doc = $this->parse(
                '@if($a) Content @endif @foreach($b as $c) Item @endforeach',
                ParserOptions::defaults()
            );
            $nodes = $doc->getChildren();

            expect($nodes[0]->isFirstChild())->toBeTrue()
                ->and($nodes[0]->isLastChild())->toBeFalse()
                ->and(end($nodes)->isLastChild())->toBeTrue();
        });
    });

    describe('deeply nested structures', function (): void {
        test('predicates work at any nesting level', function (): void {
            $template = <<<'HTML'
<div>
    <ul>
        <li>First</li>
        <li>Second</li>
        <li>Third</li>
    </ul>
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            $ul = $div->nodes()->whereElementIs('ul')->first();
            expect($ul)->not->toBeNull();

            $lis = $ul->nodes()->whereElementIs('li')->all();

            expect($lis)->toHaveCount(3)
                ->and($lis[0]->isFirstChild())->toBeFalse()
                ->and($lis[2]->isLastChild())->toBeFalse();
        });
    });
});
