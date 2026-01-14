<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Directive Nested Operations', function (): void {
    describe('remove()', function (): void {
        it('removes an element nested in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)@endif');
        });

        it('removes a text node nested in element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>text to remove</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'remove')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span></span>@endif');
        });

        it('removes a directive nested in @if', function (): void {
            $doc = $this->parse('@if($show)@json($data)@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective() && $path->isDirectiveNamed('json')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)@endif');
        });

        it('removes a directive in element in @if', function (): void {
            $doc = $this->parse('@if($outer)<div>@json($data)</div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective() && $path->isDirectiveNamed('json')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($outer)<div></div>@endif');
        });

        it('removes an echo nested in @if', function (): void {
            $doc = $this->parse('@if($show){{ $variable }}@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isEcho()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)@endif');
        });

        it('removes a comment nested in @if', function (): void {
            $doc = $this->parse('@if($show)<!-- comment -->@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isComment()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)@endif');
        });

        it('removes one of multiple siblings in @if', function (): void {
            $doc = $this->parse('@if($show)<span>first</span><p>second</p><em>third</em>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('p')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span>first</span><em>third</em>@endif');
        });
    });

    describe('replaceWith()', function (): void {
        it('replaces element in @if with text', function (): void {
            $doc = $this->parse('@if($show)<span>old</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->replaceWith('replaced');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)replaced@endif');
        });

        it('replaces element in @if with another element', function (): void {
            $doc = $this->parse('@if($show)<span>old</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->replaceWith(Builder::element('strong')->text('new'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<strong>new</strong>@endif');
        });

        it('replaces element in @if with multiple nodes', function (): void {
            $doc = $this->parse('@if($show)<span>old</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->replaceWith([
                            Builder::text('text'),
                            Builder::element('em')->text('emphasis'),
                        ]);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)text<em>emphasis</em>@endif');
        });

        it('replaces directive in @if with PHP tag', function (): void {
            $doc = $this->parse('@if($show)@json($data)@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective() && $path->isDirectiveNamed('json')) {
                        $path->replaceWith(Builder::phpTag(' echo json_encode($data);'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<?php echo json_encode($data); ?>@endif');
        });

        it('replaces echo in @if with raw echo', function (): void {
            $doc = $this->parse('@if($show){{ $variable }}@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isEcho()) {
                        $path->replaceWith(Builder::rawEcho('$variable'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show){!! $variable !!}@endif');
        });

        it('replaces block directive with element', function (): void {
            $doc = $this->parse('@if($outer)@foreach($items as $item)<li>{{ $item }}</li>@endforeach@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock() && $path->isDirectiveNamed('foreach')) {
                        $path->replaceWith(Builder::element('li')->text('static item'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($outer)<li>static item</li>@endif');
        });
    });

    describe('insertBefore()', function (): void {
        it('inserts before element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->insertBefore('prefix');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)prefix<span>content</span>@endif');
        });

        it('inserts element before text in @if', function (): void {
            $doc = $this->parse('@if($show)text content@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'text')) {
                        $path->insertBefore(Builder::element('strong')->text('bold'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<strong>bold</strong>text content@endif');
        });

        it('inserts before directive in @if', function (): void {
            $doc = $this->parse('@if($show)@include("partial")@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective() && $path->isDirectiveNamed('include')) {
                        $path->insertBefore(Builder::comment('loading partial'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<!-- loading partial -->@include("partial")@endif');
        });
    });

    describe('insertAfter()', function (): void {
        it('inserts after element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->insertAfter('suffix');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span>content</span>suffix@endif');
        });

        it('inserts element after echo in @if', function (): void {
            $doc = $this->parse('@if($show){{ $value }}@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isEcho()) {
                        $path->insertAfter(Builder::element('br')->void());
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show){{ $value }}<br>@endif');
        });
    });

    describe('wrapWith()', function (): void {
        it('wraps element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->wrapWith(Builder::element('div')->class('wrapper'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div class="wrapper"><span>content</span></div>@endif');
        });

        it('wraps text node in element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>plain text</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'plain')) {
                        $path->wrapWith(Builder::element('em'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span><em>plain text</em></span>@endif');
        });

        it('wraps echo in @if', function (): void {
            $doc = $this->parse('@if($show){{ $value }}@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isEcho()) {
                        $path->wrapWith(Builder::element('span')->class('output'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="output">{{ $value }}</span>@endif');
        });

        it('wraps directive in element in @if', function (): void {
            $doc = $this->parse('@if($outer)<div>@json($data)</div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective() && $path->isDirectiveNamed('json')) {
                        $path->wrapWith(Builder::element('span')->class('json-output'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($outer)<div><span class="json-output">@json($data)</span></div>@endif');
        });
    });

    describe('unwrap()', function (): void {
        it('unwraps element with children in @if', function (): void {
            $doc = $this->parse('@if($show)<div><em>a</em><em>b</em></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->unwrap();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<em>a</em><em>b</em>@endif');
        });

        it('unwraps nested directive block in @if', function (): void {
            $doc = $this->parse('@if($outer)@foreach($items as $item)<li>item</li>@endforeach@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock() && $path->isDirectiveNamed('foreach')) {
                        $path->unwrap();
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toContain('<li>item</li>');
        });
    });

    describe('replaceChildren()', function (): void {
        it('replaces children of element in @if', function (): void {
            $doc = $this->parse('@if($show)<div>old content</div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->replaceChildren('new content');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div>new content</div>@endif');
        });

        it('replaces children with multiple nodes in @if', function (): void {
            $doc = $this->parse('@if($show)<ul><li>old</li></ul>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('ul')) {
                        $path->replaceChildren([
                            Builder::element('li')->text('first'),
                            Builder::element('li')->text('second'),
                        ]);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<ul><li>first</li><li>second</li></ul>@endif');
        });
    });

    describe('prependChildren()', function (): void {
        it('prepends to element children in @if', function (): void {
            $doc = $this->parse('@if($show)<div>existing</div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->prependChildren('prepended ');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div>prepended existing</div>@endif');
        });
    });

    describe('appendChild()', function (): void {
        it('appends to element children in @if', function (): void {
            $doc = $this->parse('@if($show)<div>existing</div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->appendChild(' appended');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div>existing appended</div>@endif');
        });
    });

    describe('setAttribute()', function (): void {
        it('sets attribute on element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->setAttribute('id', 'conditional');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span id="conditional">content</span>@endif');
        });

        it('overwrites existing attribute on element in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="old">content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->setAttribute('class', 'new');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="new">content</span>@endif');
        });
    });

    describe('removeAttribute()', function (): void {
        it('removes attribute from element in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="remove" id="keep">content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->removeAttribute('class');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span id="keep">content</span>@endif');
        });
    });

    describe('addClass() / removeClass()', function (): void {
        it('adds class to element in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->addClass('added');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="added">content</span>@endif');
        });

        it('adds class to existing classes on element in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="existing">content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->addClass('added');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="existing added">content</span>@endif');
        });

        it('removes class from element in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="keep remove">content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->removeClass('remove');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="keep">content</span>@endif');
        });

        it('chains addClass and removeClass correctly on element in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="old">content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->removeClass('old');
                        $path->addClass('new');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="new">content</span>@endif');
        });
    });

    describe('renameTag()', function (): void {
        it('renames element tag in @if', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->renameTag('strong');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<strong>content</strong>@endif');
        });

        it('renames tag while preserving attributes in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="styled" id="my-span">content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->renameTag('em');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<em class="styled" id="my-span">content</em>@endif');
        });
    });

    describe('skipChildren()', function (): void {
        it('skips children of element in @if', function (): void {
            $doc = $this->parse('@if($show)<ul><li><span>skip me</span></li></ul>@endif');

            $visited = [];
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visited) extends Visitor
            {
                public function __construct(private array &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->visited[] = $path->asElement()->tagNameText();
                        if ($path->isTag('li')) {
                            $path->skipChildren();
                        }
                    }
                }
            });

            $rewriter->rewrite($doc);
            expect($visited)->toBe(['ul', 'li']);
        });
    });

    describe('stopTraversal()', function (): void {
        it('stops traversal within @if', function (): void {
            $doc = $this->parse('@if($show)<span>first</span><p>stop</p><em>third</em>@endif');

            $visited = [];
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visited) extends Visitor
            {
                public function __construct(private array &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->visited[] = $path->asElement()->tagNameText();
                        if ($path->isTag('p')) {
                            $path->stopTraversal();
                        }
                    }
                }
            });

            $rewriter->rewrite($doc);
            expect($visited)->toBe(['span', 'p']);
        });
    });

    describe('surroundWith()', function (): void {
        it('surrounds element in @if with before/replacement/after', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->surroundWith(
                            'BEFORE',
                            Builder::element('em')->text('REPLACED'),
                            'AFTER'
                        );
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)BEFORE<em>REPLACED</em>AFTER@endif');
        });
    });

    describe('multi-pass transformations', function (): void {
        it('works correctly across multiple passes on elements in @if', function (): void {
            $doc = $this->parse('@if($show)<span class="old">content</span>@endif');

            // Pass 1: rename span to em and change class
            $rewriter1 = new Rewriter;
            $rewriter1->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->renameTag('em');
                        $path->removeClass('old');
                        $path->addClass('new');
                    }
                }
            });
            $result1 = $rewriter1->rewrite($doc);

            // Pass 2: wrap the em in a strong
            $rewriter2 = new Rewriter;
            $rewriter2->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('em')) {
                        $path->wrapWith(Builder::element('strong'));
                    }
                }
            });
            $result2 = $rewriter2->rewrite($result1);

            expect($result2->render())
                ->toBe('@if($show)<strong><em class="new">content</em></strong>@endif');
        });
    });

    describe('operations on @if block itself', function (): void {
        it('wraps entire @if block', function (): void {
            $doc = $this->parse('@if($show)<span>content</span>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock() && $path->isDirectiveNamed('if')) {
                        $path->wrapWith(Builder::element('div')->class('conditional'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="conditional">@if($show)<span>content</span>@endif</div>');
        });

        it('inserts before @if block', function (): void {
            $doc = $this->parse('@if($show)content@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock() && $path->isDirectiveNamed('if')) {
                        $path->insertBefore(Builder::comment('conditional block'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<!-- conditional block -->@if($show)content@endif');
        });

        it('inserts after @if block', function (): void {
            $doc = $this->parse('@if($show)content@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock() && $path->isDirectiveNamed('if')) {
                        $path->insertAfter(Builder::comment('end conditional'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)content@endif<!-- end conditional -->');
        });
    });

    describe('text nodes directly in paired directives', function (): void {
        it('removes text node directly in @if', function (): void {
            $doc = $this->parse('@if($show) text to remove @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'remove')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)@endif');
        });

        it('replaces text node directly in @if with string', function (): void {
            $doc = $this->parse('@if($show) old text @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'old')) {
                        $path->replaceWith('new text');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)new text@endif');
        });

        it('replaces text node directly in @if with element', function (): void {
            $doc = $this->parse('@if($show) plain text @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'plain')) {
                        $path->replaceWith(Builder::element('strong')->text('bold text'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<strong>bold text</strong>@endif');
        });

        it('wraps text node directly in @if', function (): void {
            $doc = $this->parse('@if($show) wrap me @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'wrap')) {
                        $path->wrapWith(Builder::element('span')->class('wrapped'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<span class="wrapped"> wrap me </span>@endif');
        });

        it('removes text node directly in @foreach', function (): void {
            $doc = $this->parse('@foreach($items as $item) text to remove @endforeach');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'remove')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@foreach($items as $item)@endforeach');
        });

        it('removes text node directly in @unless', function (): void {
            $doc = $this->parse('@unless($hidden) text to remove @endunless');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'remove')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@unless($hidden)@endunless');
        });

        it('removes whitespace-only text node directly in @if', function (): void {
            $doc = $this->parse('@if($show)   @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && $path->node()->asText()->isWhitespace()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)@endif');
        });

        it('removes text from @else branch', function (): void {
            $doc = $this->parse('@if($show) keep this @else remove this @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'remove')) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show) keep this @else@endif');
        });

        it('replaces text in @elseif branch', function (): void {
            $doc = $this->parse('@if($a) first @elseif($b) old text @else third @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'old')) {
                        $path->replaceWith('new text');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($a) first @elseif($b)new text@else third @endif');
        });

        it('wraps text in multiple branches independently', function (): void {
            $doc = $this->parse('@if($a) wrap A @else wrap B @endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'wrap')) {
                        $path->wrapWith(Builder::element('em'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($a)<em> wrap A </em>@else<em> wrap B </em>@endif');
        });
    });
});
