<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Node Tree Containment', function (): void {
    describe('contains()', function (): void {
        test('parent contains child', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->firstChild();

            expect($div->contains($span))->toBeTrue();
        });

        test('grandparent contains grandchild', function (): void {
            $doc = $this->parse('<div><p><span>Text</span></p></div>');
            $div = $doc->getChildren()[0];
            $p = $div->firstChild();
            $span = $p->firstChild();
            $text = $span->firstChild();

            expect($div->contains($p))->toBeTrue()
                ->and($div->contains($span))->toBeTrue()
                ->and($div->contains($text))->toBeTrue()
                ->and($p->contains($span))->toBeTrue()
                ->and($p->contains($text))->toBeTrue();
        });

        test('sibling does not contain sibling', function (): void {
            $doc = $this->parse('<div><a></a><b></b></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();

            expect($children[0]->contains($children[1]))->toBeFalse()
                ->and($children[1]->contains($children[0]))->toBeFalse();
        });

        test('child does not contain parent', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->firstChild();

            expect($span->contains($div))->toBeFalse();
        });

        test('node does not contain itself', function (): void {
            $doc = $this->parse('<div></div>');
            $div = $doc->getChildren()[0];

            expect($div->contains($div))->toBeFalse();
        });

        test('works with deeply nested structures', function (): void {
            $template = '<a><b><c><d><e>Deep</e></d></c></b></a>';
            $doc = $this->parse($template);

            $a = $doc->getChildren()[0];
            $b = $a->firstChild();
            $c = $b->firstChild();
            $d = $c->firstChild();
            $e = $d->firstChild();
            $text = $e->firstChild();

            expect($a->contains($b))->toBeTrue()
                ->and($a->contains($c))->toBeTrue()
                ->and($a->contains($d))->toBeTrue()
                ->and($a->contains($e))->toBeTrue()
                ->and($a->contains($text))->toBeTrue()
                ->and($c->contains($d))->toBeTrue()
                ->and($c->contains($e))->toBeTrue()
                ->and($c->contains($text))->toBeTrue()
                ->and($c->contains($a))->toBeFalse()
                ->and($c->contains($b))->toBeFalse();
        });

        test('text nodes are contained by their parent elements', function (): void {
            $doc = $this->parse('<p>Hello World</p>');
            $p = $doc->getChildren()[0];
            $text = $p->firstChild();

            expect($p->contains($text))->toBeTrue()
                ->and($text->contains($p))->toBeFalse();
        });

        test('works with parallel branches', function (): void {
            $doc = $this->parse('<div><p><a></a></p><span><b></b></span></div>');
            $div = $doc->getChildren()[0];
            $children = $div->getChildren();
            $p = $children[0];
            $span = $children[1];
            $a = $p->firstChild();
            $b = $span->firstChild();

            expect($div->contains($a))->toBeTrue()
                ->and($div->contains($b))->toBeTrue()
                ->and($p->contains($b))->toBeFalse()
                ->and($span->contains($a))->toBeFalse()
                ->and($a->contains($b))->toBeFalse()
                ->and($b->contains($a))->toBeFalse();
        });

        test('contains with nodes from different documents', function (): void {
            $doc1 = $this->parse('<div><span></span></div>');
            $doc2 = $this->parse('<div><span></span></div>');

            $div1 = $doc1->firstChild();
            $span2 = $doc2->firstChild()->firstChild();

            expect($div1->contains($span2))->toBeFalse();
        });
    });

    describe('isLeaf()', function (): void {
        test('returns true for text node', function (): void {
            $doc = $this->parse('<p>Text</p>');
            $p = $doc->getChildren()[0];
            $text = $p->firstChild();

            expect($text)->toBeInstanceOf(TextNode::class)
                ->and($text->isLeaf())->toBeTrue();
        });

        test('returns true for empty element', function (): void {
            $doc = $this->parse('<div></div>');
            $div = $doc->getChildren()[0];

            expect($div->isLeaf())->toBeTrue();
        });

        test('returns true for void element', function (): void {
            $doc = $this->parse('<br>');
            $br = $doc->getChildren()[0];

            expect($br->isLeaf())->toBeTrue();
        });

        test('returns false for element with children', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];

            expect($div->isLeaf())->toBeFalse();
        });

        test('returns false for element with text content', function (): void {
            $doc = $this->parse('<p>Content</p>');
            $p = $doc->getChildren()[0];

            expect($p->isLeaf())->toBeFalse();
        });

        test('parent is not leaf, but its children might be', function (): void {
            $doc = $this->parse('<div><span></span></div>');
            $div = $doc->getChildren()[0];
            $span = $div->firstChild();

            expect($div->isLeaf())->toBeFalse()
                ->and($span->isLeaf())->toBeTrue();
        });

        test('deeply nested isLeaf checks', function (): void {
            $template = '<a><b><c><d><e></e></d></c></b></a>';
            $doc = $this->parse($template);

            $a = $doc->getChildren()[0];
            $b = $a->firstChild();
            $c = $b->firstChild();
            $d = $c->firstChild();
            $e = $d->firstChild();

            expect($a->isLeaf())->toBeFalse()
                ->and($b->isLeaf())->toBeFalse()
                ->and($c->isLeaf())->toBeFalse()
                ->and($d->isLeaf())->toBeFalse()
                ->and($e->isLeaf())->toBeTrue();
        });
    });

    describe('with Blade directives', function (): void {
        test('contains works with directive blocks', function (): void {
            $doc = $this->parse(
                '<div>@if($show) <span>Content</span> @endif</div>',
                ParserOptions::defaults()
            );
            $div = $doc->getChildren()[0];

            $ifBlock = $div->firstChildOfType(DirectiveBlockNode::class);
            expect($ifBlock)->not->toBeNull();

            $span = $ifBlock->nodes()->whereElementIs('span')->first() ?? $doc->findElementByName('span');
            expect($span)->not->toBeNull()
                ->and($div->contains($ifBlock))->toBeTrue()
                ->and($div->contains($span))->toBeTrue()
                ->and($ifBlock->contains($span))->toBeTrue()
                ->and($span->contains($div))->toBeFalse();
        });

        test('isLeaf works with directive content', function (): void {
            $doc = $this->parse(
                '@yield("content")',
                ParserOptions::defaults()
            );

            $yield = $doc->getChildren()[0];

            expect($yield->isLeaf())->toBeTrue();
        });
    });
});
