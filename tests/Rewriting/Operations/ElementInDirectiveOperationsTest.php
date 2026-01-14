<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Element In Directive Operations', function (): void {
    describe('remove()', function (): void {
        it('removes an element nested in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

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
                ->toBe('@if($show)<div></div>@endif');
        });

        it('removes a text node nested in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div>text to remove</div>@endif');

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
                ->toBe('@if($show)<div></div>@endif');
        });

        it('removes a directive nested in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div>@json($data)</div>@endif');

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
                ->toBe('@if($show)<div></div>@endif');
        });

        it('removes a simple directive in element in @if', function (): void {
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

        it('removes an echo nested in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div>{{ $variable }}</div>@endif');

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
                ->toBe('@if($show)<div></div>@endif');
        });

        it('removes a comment nested in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><!-- comment --></div>@endif');

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
                ->toBe('@if($show)<div></div>@endif');
        });

        it('removes one of multiple siblings in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>first</span><p>second</p><em>third</em></div>@endif');

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
                ->toBe('@if($show)<div><span>first</span><em>third</em></div>@endif');
        });

        it('removes deeply nested element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><ul><li><span>deep</span></li></ul></div>@endif');

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
                ->toBe('@if($show)<div><ul><li></li></ul></div>@endif');
        });
    });

    describe('replaceWith()', function (): void {
        it('replaces element in element in @if with text', function (): void {
            $doc = $this->parse('@if($show)<div><span>old</span></div>@endif');

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
                ->toBe('@if($show)<div>replaced</div>@endif');
        });

        it('replaces element in element in @if with another element', function (): void {
            $doc = $this->parse('@if($show)<div><span>old</span></div>@endif');

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
                ->toBe('@if($show)<div><strong>new</strong></div>@endif');
        });

        it('replaces element in element in @if with multiple nodes', function (): void {
            $doc = $this->parse('@if($show)<div><span>old</span></div>@endif');

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
                ->toBe('@if($show)<div>text<em>emphasis</em></div>@endif');
        });

        it('replaces directive in element in @if with PHP tag', function (): void {
            $doc = $this->parse('@if($show)<div>@json($data)</div>@endif');

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
                ->toBe('@if($show)<div><?php echo json_encode($data); ?></div>@endif');
        });

        it('replaces echo in element in @if with raw echo', function (): void {
            $doc = $this->parse('@if($show)<div>{{ $variable }}</div>@endif');

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
                ->toBe('@if($show)<div>{!! $variable !!}</div>@endif');
        });
    });

    describe('insertBefore()', function (): void {
        it('inserts before element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

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
                ->toBe('@if($show)<div>prefix<span>content</span></div>@endif');
        });

        it('inserts element before text in element in @if', function (): void {
            $doc = $this->parse('@if($show)<p>text content</p>@endif');

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
                ->toBe('@if($show)<p><strong>bold</strong>text content</p>@endif');
        });

        it('inserts before directive in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div>@include("partial")</div>@endif');

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
                ->toBe('@if($show)<div><!-- loading partial -->@include("partial")</div>@endif');
        });
    });

    describe('insertAfter()', function (): void {
        it('inserts after element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

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
                ->toBe('@if($show)<div><span>content</span>suffix</div>@endif');
        });

        it('inserts element after echo in element in @if', function (): void {
            $doc = $this->parse('@if($show)<p>{{ $value }}</p>@endif');

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
                ->toBe('@if($show)<p>{{ $value }}<br></p>@endif');
        });

        it('inserts between siblings in element in @if', function (): void {
            $doc = $this->parse('@if($show)<ul><li>first</li><li>last</li></ul>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                private int $count = 0;

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('li')) {
                        $this->count++;
                        if ($this->count === 1) {
                            $path->insertAfter(Builder::element('li')->text('middle'));
                        }
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<ul><li>first</li><li>middle</li><li>last</li></ul>@endif');
        });
    });

    describe('wrapWith()', function (): void {
        it('wraps element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->wrapWith(Builder::element('em'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><em><span>content</span></em></div>@endif');
        });

        it('wraps text node in element in @if', function (): void {
            $doc = $this->parse('@if($show)<p>plain text</p>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'plain')) {
                        $path->wrapWith(Builder::element('strong'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<p><strong>plain text</strong></p>@endif');
        });

        it('wraps echo in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div>{{ $value }}</div>@endif');

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
                ->toBe('@if($show)<div><span class="output">{{ $value }}</span></div>@endif');
        });

        it('wraps simple directive in element in @if', function (): void {
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
        it('unwraps element with children in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span><em>a</em><em>b</em></span></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->unwrap();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><em>a</em><em>b</em></div>@endif');
        });

        it('unwraps deeply nested element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><ul><li><span>content</span></li></ul></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('li')) {
                        $path->unwrap();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><ul><span>content</span></ul></div>@endif');
        });
    });

    describe('replaceChildren()', function (): void {
        it('replaces children of element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>old content</span></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->replaceChildren('new content');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><span>new content</span></div>@endif');
        });

        it('replaces children with multiple nodes in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><ul><li>old</li></ul></div>@endif');

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
                ->toBe('@if($show)<div><ul><li>first</li><li>second</li></ul></div>@endif');
        });
    });

    describe('prependChildren()', function (): void {
        it('prepends to element children in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><ul><li>existing</li></ul></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('ul')) {
                        $path->prependChildren(Builder::element('li')->text('first'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><ul><li>first</li><li>existing</li></ul></div>@endif');
        });
    });

    describe('appendChild()', function (): void {
        it('appends to element children in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><ul><li>existing</li></ul></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('ul')) {
                        $path->appendChild(Builder::element('li')->text('last'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><ul><li>existing</li><li>last</li></ul></div>@endif');
        });
    });

    describe('setAttribute()', function (): void {
        it('sets attribute on element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->setAttribute('id', 'deep-nested');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><span id="deep-nested">content</span></div>@endif');
        });

        it('overwrites existing attribute on element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span class="old">content</span></div>@endif');

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
                ->toBe('@if($show)<div><span class="new">content</span></div>@endif');
        });
    });

    describe('removeAttribute()', function (): void {
        it('removes attribute from element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span class="remove" id="keep">content</span></div>@endif');

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
                ->toBe('@if($show)<div><span id="keep">content</span></div>@endif');
        });
    });

    describe('addClass() / removeClass()', function (): void {
        it('adds class to element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

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
                ->toBe('@if($show)<div><span class="added">content</span></div>@endif');
        });

        it('adds class to existing classes on element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span class="existing">content</span></div>@endif');

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
                ->toBe('@if($show)<div><span class="existing added">content</span></div>@endif');
        });

        it('removes class from element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span class="keep remove">content</span></div>@endif');

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
                ->toBe('@if($show)<div><span class="keep">content</span></div>@endif');
        });

        it('chains addClass and removeClass correctly on element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span class="old">content</span></div>@endif');

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
                ->toBe('@if($show)<div><span class="new">content</span></div>@endif');
        });
    });

    describe('renameTag()', function (): void {
        it('renames element tag in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

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
                ->toBe('@if($show)<div><strong>content</strong></div>@endif');
        });

        it('renames tag while preserving attributes in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span class="styled" id="my-span">content</span></div>@endif');

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
                ->toBe('@if($show)<div><em class="styled" id="my-span">content</em></div>@endif');
        });
    });

    describe('skipChildren()', function (): void {
        it('skips children of element in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div><ul><li><span>skip me</span></li></ul></div>@endif');

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
            expect($visited)->toBe(['div', 'ul', 'li']); // span was skipped
        });
    });

    describe('stopTraversal()', function (): void {
        it('stops traversal at deeply nested level in @if', function (): void {
            $doc = $this->parse('@if($show)<div><span>first</span><p>stop</p><em>third</em></div>@endif');

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
            expect($visited)->toBe(['div', 'span', 'p']); // em was not visited
        });
    });

    describe('surroundWith()', function (): void {
        it('surrounds element in element in @if with before/replacement/after', function (): void {
            $doc = $this->parse('@if($show)<div><span>content</span></div>@endif');

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
                ->toBe('@if($show)<div>BEFORE<em>REPLACED</em>AFTER</div>@endif');
        });
    });

    describe('multi-pass transformations', function (): void {
        it('works correctly across multiple passes in element in @if', function (): void {
            $doc = $this->parse('@if($show)<div class="container"><span class="old">content</span></div>@endif');

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

            expect($result2->render())->toBe('@if($show)<div class="container"><strong><em class="new">content</em></strong></div>@endif');
        });

        it('handles three-level deep transformation across passes', function (): void {
            $doc = $this->parse('@if($show)<article><section><p class="text">content</p></section></article>@endif');

            // Pass 1: rename p to div
            $rewriter1 = new Rewriter;
            $rewriter1->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('p')) {
                        $path->renameTag('div');
                    }
                }
            });
            $result1 = $rewriter1->rewrite($doc);

            // Pass 2: add class to section
            $rewriter2 = new Rewriter;
            $rewriter2->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('section')) {
                        $path->addClass('highlighted');
                    }
                }
            });
            $result2 = $rewriter2->rewrite($result1);

            // Pass 3: wrap article children
            $rewriter3 = new Rewriter;
            $rewriter3->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('section')) {
                        $path->wrapWith(Builder::element('main'));
                    }
                }
            });

            $result3 = $rewriter3->rewrite($result2);

            expect($result3->render())
                ->toBe('@if($show)<article><main><section class="highlighted"><div class="text">content</div></section></main></article>@endif');
        });
    });

    describe('combined operations on multiple levels', function (): void {
        it('transforms parent and child elements simultaneously', function (): void {
            $doc = $this->parse('@if($show)<div class="parent"><span class="child">content</span></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->addClass('modified-parent');
                    }
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->renameTag('strong');
                        $path->removeClass('child');
                        $path->addClass('modified-child');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div class="parent modified-parent"><strong class="modified-child">content</strong></div>@endif');
        });

        it('handles remove and insert operations at different levels', function (): void {
            $doc = $this->parse('@if($show)<div><span>keep</span><p>remove</p><em>keep</em></div>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->prependChildren(Builder::element('strong')->text('prepended'));
                    }
                    if ($path->isElement() && $path->isTag('p')) {
                        $path->remove();
                    }
                    if ($path->isElement() && $path->isTag('em')) {
                        $path->insertAfter(Builder::element('small')->text('appended'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('@if($show)<div><strong>prepended</strong><span>keep</span><em>keep</em><small>appended</small></div>@endif');
        });
    });
});
