<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Root Level Operations', function (): void {
    describe('remove()', function (): void {
        it('removes a root element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('');
        });

        it('removes a root text node', function (): void {
            $doc = $this->parse('hello world');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText()) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('');
        });

        it('removes a root directive', function (): void {
            $doc = $this->parse('@json($data)');

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
                ->toBe('');
        });

        it('removes a root directive block', function (): void {
            $doc = $this->parse('@if($show)content@endif');

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
                ->toBe('');
        });

        it('removes a root echo', function (): void {
            $doc = $this->parse('{{ $variable }}');

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
                ->toBe('');
        });

        it('removes a root comment', function (): void {
            $doc = $this->parse('<!-- comment -->');

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
                ->toBe('');
        });

        it('removes one of multiple root siblings', function (): void {
            $doc = $this->parse('<div>first</div><span>second</span><p>third</p>');

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
                ->toBe('<div>first</div><p>third</p>');
        });
    });

    describe('replaceWith()', function (): void {
        it('replaces a root element with text', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->replaceWith('replaced');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('replaced');
        });

        it('replaces a root element with another element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->replaceWith(Builder::element('span')->text('new'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<span>new</span>');
        });

        it('replaces a root element with multiple nodes', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->replaceWith([
                            Builder::text('before'),
                            Builder::element('span')->text('middle'),
                            Builder::text('after'),
                        ]);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('before<span>middle</span>after');
        });

        it('replaces a root directive with PHP tag', function (): void {
            $doc = $this->parse('@json($data)');

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
                ->toBe('<?php echo json_encode($data); ?>');
        });

        it('replaces a root echo with raw echo', function (): void {
            $doc = $this->parse('{{ $variable }}');

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
                ->toBe('{!! $variable !!}');
        });
    });

    describe('insertBefore()', function (): void {
        it('inserts before a root element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->insertBefore('prefix');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('prefix<div>content</div>');
        });

        it('inserts element before a root text', function (): void {
            $doc = $this->parse('text content');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText()) {
                        $path->insertBefore(Builder::element('hr')->void());
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<hr>text content');
        });

        it('inserts before a root directive', function (): void {
            $doc = $this->parse('@include("partial")');

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
                ->toBe('<!-- loading partial -->@include("partial")');
        });
    });

    describe('insertAfter()', function (): void {
        it('inserts after a root element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->insertAfter('suffix');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div>content</div>suffix');
        });

        it('inserts element after a root echo', function (): void {
            $doc = $this->parse('{{ $value }}');

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
                ->toBe('{{ $value }}<br>');
        });
    });

    describe('wrapWith()', function (): void {
        it('wraps a root element', function (): void {
            $doc = $this->parse('<span>content</span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->wrapWith(Builder::element('div')->class('wrapper'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="wrapper"><span>content</span></div>');
        });

        it('wraps a root text node', function (): void {
            $doc = $this->parse('plain text');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText()) {
                        $path->wrapWith(Builder::element('p'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<p>plain text</p>');
        });

        it('wraps a root directive block', function (): void {
            $doc = $this->parse('@if($show)content@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $path->wrapWith(Builder::element('div')->id('conditional'));
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div id="conditional">@if($show)content@endif</div>');
        });
    });

    describe('unwrap()', function (): void {
        it('unwraps a root element with children', function (): void {
            $doc = $this->parse('<div><span>a</span><span>b</span></div>');

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
                ->toBe('<span>a</span><span>b</span>');
        });

        it('unwraps a root directive block', function (): void {
            $doc = $this->parse('@if($show)<p>content</p>@endif');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $path->unwrap();
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('<p>content</p>');
        });
    });

    describe('replaceChildren()', function (): void {
        it('replaces children of a root element', function (): void {
            $doc = $this->parse('<div>old content</div>');

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
                ->toBe('<div>new content</div>');
        });

        it('replaces children with multiple nodes', function (): void {
            $doc = $this->parse('<ul><li>old</li></ul>');

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
                ->toBe('<ul><li>first</li><li>second</li></ul>');
        });
    });

    describe('prependChildren()', function (): void {
        it('prepends to root element children', function (): void {
            $doc = $this->parse('<div>existing</div>');

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
                ->toBe('<div>prepended existing</div>');
        });

        it('prepends element to root element children', function (): void {
            $doc = $this->parse('<ul><li>existing</li></ul>');

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
                ->toBe('<ul><li>first</li><li>existing</li></ul>');
        });
    });

    describe('appendChild()', function (): void {
        it('appends to root element children', function (): void {
            $doc = $this->parse('<div>existing</div>');

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
                ->toBe('<div>existing appended</div>');
        });
    });

    describe('setAttribute()', function (): void {
        it('sets attribute on root element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->setAttribute('id', 'main');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div id="main">content</div>');
        });

        it('overwrites existing attribute', function (): void {
            $doc = $this->parse('<div class="old">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->setAttribute('class', 'new');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="new">content</div>');
        });
    });

    describe('removeAttribute()', function (): void {
        it('removes attribute from root element', function (): void {
            $doc = $this->parse('<div class="remove-me" id="keep">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->removeAttribute('class');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div id="keep">content</div>');
        });
    });

    describe('addClass() / removeClass()', function (): void {
        it('adds class to root element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('added');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="added">content</div>');
        });

        it('adds class to existing classes', function (): void {
            $doc = $this->parse('<div class="existing">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('added');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="existing added">content</div>');
        });

        it('removes class from root element', function (): void {
            $doc = $this->parse('<div class="keep remove">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->removeClass('remove');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="keep">content</div>');
        });

        it('chains addClass and removeClass correctly', function (): void {
            $doc = $this->parse('<div class="old">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->removeClass('old');
                        $path->addClass('new');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<div class="new">content</div>');
        });
    });

    describe('renameTag()', function (): void {
        it('renames root element tag', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->renameTag('section');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<section>content</section>');
        });

        it('renames tag while preserving attributes', function (): void {
            $doc = $this->parse('<div class="container" id="main">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->renameTag('article');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('<article class="container" id="main">content</article>');
        });
    });

    describe('skipChildren()', function (): void {
        it('skips children of root element', function (): void {
            $doc = $this->parse('<div><span>child</span></div>');

            $visited = [];
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visited) extends Visitor
            {
                public function __construct(private array &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->visited[] = $path->asElement()->tagNameText();
                        if ($path->isTag('div')) {
                            $path->skipChildren();
                        }
                    }
                }
            });

            $rewriter->rewrite($doc);
            expect($visited)->toBe(['div']); // span was skipped
        });
    });

    describe('stopTraversal()', function (): void {
        it('stops traversal at root level', function (): void {
            $doc = $this->parse('<div>first</div><span>second</span><p>third</p>');

            $visited = [];
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visited) extends Visitor
            {
                public function __construct(private array &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->visited[] = $path->asElement()->tagNameText();
                        if ($path->isTag('span')) {
                            $path->stopTraversal();
                        }
                    }
                }
            });

            $rewriter->rewrite($doc);
            expect($visited)->toBe(['div', 'span']); // p was not visited
        });
    });

    describe('surroundWith()', function (): void {
        it('surrounds root element with before/replacement/after', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->surroundWith(
                            'BEFORE',
                            Builder::element('span')->text('REPLACED'),
                            'AFTER'
                        );
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe('BEFORE<span>REPLACED</span>AFTER');
        });
    });
});
