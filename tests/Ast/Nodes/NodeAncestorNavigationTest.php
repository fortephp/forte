<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Parser\ParserOptions;

describe('Node Ancestor Navigation', function (): void {
    describe('ancestors()', function (): void {
        it('returns all ancestors from parent to root', function (): void {
            $doc = $this->parse('<div><p><span>Text</span></p></div>');
            $div = $doc->firstChild();
            $p = $div->firstChild();
            $span = $p->firstChild();
            $text = $span->firstChild();

            $ancestors = $text->getAncestors();

            expect($ancestors)->toHaveCount(3)
                ->and($ancestors[0])->toBe($span)
                ->and($ancestors[1])->toBe($p)
                ->and($ancestors[2])->toBe($div);
        });

        it('returns empty for root-level nodes', function (): void {
            $doc = $this->parse('<div>Hello</div>');
            $div = $doc->firstChild();

            $ancestors = $div->getAncestors();
            expect($ancestors)->toHaveCount(0);
        });

        it('yields ancestors in order from nearest to farthest', function (): void {
            $template = <<<'HTML'
<html>
    <body>
        <main>
            <article>
                <p>Content</p>
            </article>
        </main>
    </body>
</html>
HTML;
            $doc = $this->parse($template);

            $html = $doc->firstChild();
            $body = $html->firstChildOfType(ElementNode::class);
            $main = $body->firstChildOfType(ElementNode::class);
            $article = $main->firstChildOfType(ElementNode::class);
            $p = $article->firstChildOfType(ElementNode::class);

            $ancestorNames = array_map(
                fn ($a) => $a->tagNameText(),
                array_filter($p->getAncestors(), fn ($a) => $a instanceof ElementNode)
            );

            expect($ancestorNames)->toBe(['article', 'main', 'body', 'html']);
        });
    });

    describe('closest()', function (): void {
        it('finds nearest ancestor matching predicate', function (): void {
            $doc = $this->parse('<form><div><input></div></form>');
            $form = $doc->firstChild();
            $div = $form->firstChild();
            $input = $div->firstChild();

            $closestForm = $input->closest(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'form'
            );

            expect($closestForm)->toBe($form);
        });

        it('returns nearest matching when multiple match', function (): void {
            $doc = $this->parse('<div class="outer"><div class="inner"><span></span></div></div>');
            $outerDiv = $doc->firstChild();
            $innerDiv = $outerDiv->firstChild();
            $span = $innerDiv->firstChild();

            $closestDiv = $span->closest(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'div'
            );

            expect($closestDiv)->toBe($innerDiv);
        });

        it('returns null when no ancestor matches', function (): void {
            $doc = $this->parse('<div><span>Text</span></div>');
            $div = $doc->firstChild();
            $span = $div->firstChild();
            $text = $span->firstChild();

            $form = $text->closest(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'form'
            );

            expect($form)->toBeNull();
        });

        it('does not match self', function (): void {
            $doc = $this->parse('<form></form>');
            $form = $doc->firstChild();

            $closestForm = $form->closest(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'form'
            );

            expect($closestForm)->toBeNull();
        });
    });

    describe('closestOfType()', function (): void {
        it('finds nearest ancestor of specific type', function (): void {
            $doc = $this->parse('<div>Text inside</div>');
            $div = $doc->firstChild();
            $text = $div->firstChild();

            $element = $text->closestOfType(ElementNode::class);

            expect($element)->toBe($div);
        });

        it('returns null when no ancestor of type exists', function (): void {
            $doc = $this->parse('<div></div>');
            $div = $doc->firstChild();

            $parent = $div->closestOfType(ElementNode::class);
            expect($parent)->toBeNull();
        });
    });

    describe('hasAncestorWhere()', function (): void {
        it('returns true when matching ancestor exists', function (): void {
            $doc = $this->parse('<pre><code>Code here</code></pre>');
            $pre = $doc->firstChild();
            $code = $pre->firstChild();
            $text = $code->firstChild();

            $hasPre = $text->hasAncestorWhere(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'pre'
            );

            expect($hasPre)->toBeTrue();
        });

        it('returns false when no matching ancestor exists', function (): void {
            $doc = $this->parse('<div><span>Text</span></div>');
            $div = $doc->firstChild();
            $span = $div->firstChild();
            $text = $span->firstChild();

            $hasForm = $text->hasAncestorWhere(
                fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'form'
            );

            expect($hasForm)->toBeFalse();
        });
    });

    describe('depth()', function (): void {
        it('returns 0 for root-level nodes', function (): void {
            $doc = $this->parse('<div>Hello</div>');
            $div = $doc->firstChild();

            expect($div->depth())->toBe(0);
        });

        it('returns correct depth for nested nodes', function (): void {
            $doc = $this->parse('<div><p><span>Text</span></p></div>');
            $div = $doc->firstChild();
            $p = $div->firstChild();
            $span = $p->firstChild();
            $text = $span->firstChild();

            expect($div->depth())->toBe(0)
                ->and($p->depth())->toBe(1)
                ->and($span->depth())->toBe(2)
                ->and($text->depth())->toBe(3);
        });

        it('counts all nesting levels', function (): void {
            $template = '<a><b><c><d><e>Deep</e></d></c></b></a>';
            $doc = $this->parse($template);

            $a = $doc->firstChild();
            $b = $a->firstChild();
            $c = $b->firstChild();
            $d = $c->firstChild();
            $e = $d->firstChild();
            $text = $e->firstChild();

            expect($a->depth())->toBe(0)
                ->and($b->depth())->toBe(1)
                ->and($c->depth())->toBe(2)
                ->and($d->depth())->toBe(3)
                ->and($e->depth())->toBe(4)
                ->and($text->depth())->toBe(5);
        });
    });

    describe('with Blade directives', function (): void {
        test('ancestors work with directive blocks', function (): void {
            $doc = $this->parse(
                '<div>@if($show) <span>Content</span> @endif</div>',
                ParserOptions::defaults()
            );
            $div = $doc->firstChild();

            $ifBlock = $div->firstChildOfType(DirectiveBlockNode::class);

            expect($ifBlock)->not->toBeNull();

            $span = $ifBlock->firstChildWhere(
                fn ($d) => $d instanceof ElementNode && $d->tagNameText() === 'span'
            ) ?? $doc->findElementByName('span');

            expect($span)->not->toBeNull();

            $ancestors = $span->getAncestors();
            expect($ancestors)->toContain($ifBlock)
                ->and($ancestors)->toContain($div);
        });

        test('closest works to find containing directive', function (): void {
            $doc = $this->parse(
                '@foreach($items as $item) <li>{{ $item }}</li> @endforeach',
                ParserOptions::defaults()
            );
            $foreach = $doc->firstChild();

            $li = $doc->findElementByName('li');

            expect($li)->not->toBeNull();

            $closestDirective = $li->closestOfType(DirectiveBlockNode::class);
            expect($closestDirective)->toBe($foreach);
        });
    });
});
