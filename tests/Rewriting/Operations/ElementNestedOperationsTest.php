<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Element Nested Operations', function (): void {
    describe('remove()', function (): void {
        it('removes an element nested in another element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div></div>');
        });

        it('removes a text node nested in an element', function (): void {
            $doc = $this->parse('<div>text to remove</div>');

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
                ->toBe('<div></div>');
        });

        it('removes a directive nested in an element', function (): void {
            $doc = $this->parse('<div>@json($data)</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div></div>');
        });

        it('removes a directive block nested in an element', function (): void {
            $doc = $this->parse('<div>@if($show)content@endif</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div></div>');
        });

        it('removes an echo nested in an element', function (): void {
            $doc = $this->parse('<div>{{ $variable }}</div>');

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
                ->toBe('<div></div>');
        });

        it('removes a comment nested in an element', function (): void {
            $doc = $this->parse('<div><!-- comment --></div>');

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
                ->toBe('<div></div>');
        });

        it('removes one of multiple nested siblings', function (): void {
            $doc = $this->parse('<div><span>first</span><p>second</p><em>third</em></div>');

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
                ->toBe('<div><span>first</span><em>third</em></div>');
        });

        it('removes deeply nested element', function (): void {
            $doc = $this->parse('<div><ul><li><span>deep</span></li></ul></div>');

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
                ->toBe('<div><ul><li></li></ul></div>');
        });
    });

    describe('replaceWith()', function (): void {
        it('replaces nested element with text', function (): void {
            $doc = $this->parse('<div><span>old</span></div>');

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
                ->toBe('<div>replaced</div>');
        });

        it('replaces nested element with another element', function (): void {
            $doc = $this->parse('<div><span>old</span></div>');

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
                ->toBe('<div><strong>new</strong></div>');
        });

        it('replaces nested element with multiple nodes', function (): void {
            $doc = $this->parse('<div><span>old</span></div>');

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
                ->toBe('<div>text<em>emphasis</em></div>');
        });

        it('replaces nested text with element', function (): void {
            $doc = $this->parse('<div>plain text</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'plain')) {
                        $path->replaceWith(Builder::element('span')->text('wrapped'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div><span>wrapped</span></div>');
        });

        it('replaces nested directive with PHP tag', function (): void {
            $doc = $this->parse('<div>@json($data)</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective()) {
                        $path->replaceWith(Builder::phpTag(' echo json_encode($data);'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div><?php echo json_encode($data); ?></div>');
        });

        it('replaces nested echo with raw echo', function (): void {
            $doc = $this->parse('<div>{{ $variable }}</div>');

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
                ->toBe('<div>{!! $variable !!}</div>');
        });
    });

    describe('insertBefore()', function (): void {
        it('inserts before nested element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div>prefix<span>content</span></div>');
        });

        it('inserts element before nested text', function (): void {
            $doc = $this->parse('<p>text content</p>');

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
                ->toBe('<p><strong>bold</strong>text content</p>');
        });

        it('inserts before nested directive', function (): void {
            $doc = $this->parse('<div>@include("partial")</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective()) {
                        $path->insertBefore(Builder::comment('loading partial'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div><!-- loading partial -->@include("partial")</div>');
        });

        it('inserts before first nested sibling', function (): void {
            $doc = $this->parse('<div><span>first</span><span>second</span></div>');

            $visited = false;
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visited) extends Visitor
            {
                public function __construct(private &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span') && ! $this->visited) {
                        $this->visited = true;
                        $path->insertBefore('before-first');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div>before-first<span>first</span><span>second</span></div>');
        });
    });

    describe('insertAfter()', function (): void {
        it('inserts after nested element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div><span>content</span>suffix</div>');
        });

        it('inserts element after nested echo', function (): void {
            $doc = $this->parse('<p>{{ $value }}</p>');

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
                ->toBe('<p>{{ $value }}<br></p>');
        });

        it('inserts between nested siblings', function (): void {
            $doc = $this->parse('<ul><li>first</li><li>last</li></ul>');

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
                ->toBe('<ul><li>first</li><li>middle</li><li>last</li></ul>');
        });
    });

    describe('wrapWith()', function (): void {
        it('wraps nested element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div><em><span>content</span></em></div>');
        });

        it('wraps nested text node', function (): void {
            $doc = $this->parse('<p>plain text</p>');

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
                ->toBe('<p><strong>plain text</strong></p>');
        });

        it('wraps nested echo', function (): void {
            $doc = $this->parse('<div>{{ $value }}</div>');

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
                ->toBe('<div><span class="output">{{ $value }}</span></div>');
        });

        it('wraps nested directive block', function (): void {
            $doc = $this->parse('<div>@if($show)content@endif</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $path->wrapWith(
                            Builder::element('span')
                                ->id('conditional')
                        );
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div><span id="conditional">@if($show)content@endif</span></div>');
        });
    });

    describe('unwrap()', function (): void {
        it('unwraps nested element with children', function (): void {
            $doc = $this->parse('<div><span><em>a</em><em>b</em></span></div>');

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
                ->toBe('<div><em>a</em><em>b</em></div>');
        });

        it('unwraps deeply nested element', function (): void {
            $doc = $this->parse('<div><ul><li><span>content</span></li></ul></div>');

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
                ->toBe('<div><ul><span>content</span></ul></div>');
        });
    });

    describe('replaceChildren()', function (): void {
        it('replaces children of nested element', function (): void {
            $doc = $this->parse('<div><span>old content</span></div>');

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
                ->toBe('<div><span>new content</span></div>');
        });

        it('replaces children with multiple nodes in nested element', function (): void {
            $doc = $this->parse('<div><ul><li>old</li></ul></div>');

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
                ->toBe('<div><ul><li>first</li><li>second</li></ul></div>');
        });
    });

    describe('prependChildren()', function (): void {
        it('prepends to nested element children', function (): void {
            $doc = $this->parse('<div><ul><li>existing</li></ul></div>');

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
                ->toBe('<div><ul><li>first</li><li>existing</li></ul></div>');
        });
    });

    describe('appendChild()', function (): void {
        it('appends to nested element children', function (): void {
            $doc = $this->parse('<div><ul><li>existing</li></ul></div>');

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
                ->toBe('<div><ul><li>existing</li><li>last</li></ul></div>');
        });
    });

    describe('setAttribute()', function (): void {
        it('sets attribute on nested element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->setAttribute('id', 'nested');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div><span id="nested">content</span></div>');
        });

        it('overwrites existing attribute on nested element', function (): void {
            $doc = $this->parse('<div><span class="old">content</span></div>');

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
                ->toBe('<div><span class="new">content</span></div>');
        });
    });

    describe('removeAttribute()', function (): void {
        it('removes attribute from nested element', function (): void {
            $doc = $this->parse('<div><span class="remove-me" id="keep">content</span></div>');

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
                ->toBe('<div><span id="keep">content</span></div>');
        });
    });

    describe('addClass() / removeClass()', function (): void {
        it('adds class to nested element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div><span class="added">content</span></div>');
        });

        it('adds class to existing classes on nested element', function (): void {
            $doc = $this->parse('<div><span class="existing">content</span></div>');

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
                ->toBe('<div><span class="existing added">content</span></div>');
        });

        it('removes class from nested element', function (): void {
            $doc = $this->parse('<div><span class="keep remove">content</span></div>');

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
                ->toBe('<div><span class="keep">content</span></div>');
        });

        it('chains addClass and removeClass correctly on nested element', function (): void {
            $doc = $this->parse('<div><span class="old">content</span></div>');

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
                ->toBe('<div><span class="new">content</span></div>');
        });
    });

    describe('renameTag()', function (): void {
        it('renames nested element tag', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div><strong>content</strong></div>');
        });

        it('renames nested tag while preserving attributes', function (): void {
            $doc = $this->parse('<div><span class="styled" id="my-span">content</span></div>');

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
                ->toBe('<div><em class="styled" id="my-span">content</em></div>');
        });
    });

    describe('skipChildren()', function (): void {
        it('skips children of nested element', function (): void {
            $doc = $this->parse('<div><ul><li><span>skip me</span></li></ul></div>');

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
        it('stops traversal at nested level', function (): void {
            $doc = $this->parse('<div><span>first</span><p>stop here</p><em>third</em></div>');

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
        it('surrounds nested element with before/replacement/after', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

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
                ->toBe('<div>BEFORE<em>REPLACED</em>AFTER</div>');
        });
    });

    describe('multi-pass transformations', function (): void {
        it('works correctly across multiple passes on nested elements', function (): void {
            $doc = $this->parse('<div class="container"><span class="old">content</span></div>');

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

            expect($result2->render())->toBe('<div class="container"><strong><em class="new">content</em></strong></div>');
        });
    });
});
