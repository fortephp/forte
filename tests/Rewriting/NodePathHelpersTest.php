<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('NodePath::surroundWith', function (): void {
    it('combines insertBefore, replaceWith, and insertAfter', function (): void {
        $doc = $this->parse('@json($data)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isDirective() && $path->asDirective()->nameText() === 'json') {
                    $path->surroundWith(
                        Builder::phpTag(' $tmp = $data;'),
                        Builder::directive('json', '($tmp)'),
                        Builder::phpTag(' unset($tmp);')
                    );
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        // Note: PhpTagBuilder adds space before closing tag
        expect($output)->toBe('<?php $tmp = $data; ?>@json($tmp)<?php unset($tmp); ?>');
    });

    it('works with multiple nodes to insert', function (): void {
        $doc = $this->parse('{{ $value }}');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isEcho()) {
                    $path->surroundWith(
                        [Builder::text('<!--start-->'), Builder::phpTag(' /* before */ ')],
                        Builder::raw('{!! $value !!}'),
                        [Builder::phpTag(' /* after */ '), Builder::text('<!--end-->')]
                    );
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toContain('<!--start-->');
        expect($output)->toContain('{!! $value !!}');
        expect($output)->toContain('<!--end-->');
    });

    it('works in deeply nested structures', function (): void {
        $doc = $this->parse('<div><span>@json($items)</span></div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isDirective() && $path->asDirective()->nameText() === 'json') {
                    $path->surroundWith(
                        Builder::text('['),
                        Builder::directive('json', '($items)'),
                        Builder::text(']')
                    );
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toContain('[@json($items)]');
        expect($output)->toContain('<div>');
        expect($output)->toContain('<span>');
    });
});

describe('NodePath attribute helpers', function (): void {
    describe('addClass', function (): void {
        it('adds a class to element without class attribute', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('container');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toContain('class="container"');
        });

        it('adds a class to element with existing class attribute', function (): void {
            $doc = $this->parse('<div class="existing">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('new-class');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('existing');
            expect($output)->toContain('new-class');
        });

        it('does not duplicate existing class', function (): void {
            $doc = $this->parse('<div class="existing">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('existing');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect(substr_count($output, 'existing'))->toBe(1);
        });
    });

    describe('removeClass', function (): void {
        it('removes a class from element', function (): void {
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

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('keep')
                ->and($output)->not->toContain('remove');
        });

        it('removes class attribute when last class is removed', function (): void {
            $doc = $this->parse('<div class="only">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->removeClass('only');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->not->toContain('class=');
        });

        it('does nothing when class does not exist', function (): void {
            $doc = $this->parse('<div class="existing">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->removeClass('nonexistent');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('class="existing"');
        });
    });

    describe('toggleClass', function (): void {
        it('adds class when not present', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->toggleClass('toggled');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toContain('toggled');
        });

        it('removes class when present', function (): void {
            $doc = $this->parse('<div class="toggled other">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->toggleClass('toggled');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->not->toContain('toggled')
                ->and($output)->toContain('other');
        });

        it('force adds with true', function (): void {
            $doc = $this->parse('<div class="existing">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->toggleClass('existing', force: true);
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toContain('existing');
        });

        it('force removes with false', function (): void {
            $doc = $this->parse('<div class="existing">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->toggleClass('existing', force: false);
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->not->toContain('existing');
        });
    });

    describe('hasAttribute', function (): void {
        it('returns true when attribute exists', function (): void {
            $doc = $this->parse('<div id="main">content</div>');
            $hasIt = false;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($hasIt) extends Visitor
            {
                public function __construct(private bool &$hasIt) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->hasIt = $path->hasAttribute('id');
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($hasIt)->toBeTrue();
        });

        it('returns false when attribute does not exist', function (): void {
            $doc = $this->parse('<div>content</div>');
            $hasIt = true;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($hasIt) extends Visitor
            {
                public function __construct(private bool &$hasIt) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->hasIt = $path->hasAttribute('id');
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($hasIt)->toBeFalse();
        });
    });

    describe('getAttribute', function (): void {
        it('returns attribute value', function (): void {
            $doc = $this->parse('<div id="main">content</div>');
            $value = null;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($value) extends Visitor
            {
                public function __construct(private ?string &$value) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->value = $path->getAttribute('id');
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($value)->toBe('main');
        });

        it('returns null for missing attribute', function (): void {
            $doc = $this->parse('<div>content</div>');
            $value = 'not-null';

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($value) extends Visitor
            {
                public function __construct(private ?string &$value) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->value = $path->getAttribute('id');
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($value)->toBeNull();
        });
    });

    describe('hasClass', function (): void {
        it('returns true when class exists', function (): void {
            $doc = $this->parse('<div class="foo bar">content</div>');
            $hasIt = false;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($hasIt) extends Visitor
            {
                public function __construct(private bool &$hasIt) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->hasIt = $path->hasClass('bar');
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($hasIt)->toBeTrue();
        });

        it('returns false when class does not exist', function (): void {
            $doc = $this->parse('<div class="foo bar">content</div>');
            $hasIt = true;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($hasIt) extends Visitor
            {
                public function __construct(private bool &$hasIt) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->hasIt = $path->hasClass('baz');
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($hasIt)->toBeFalse();
        });
    });

    describe('getClasses', function (): void {
        it('returns array of classes', function (): void {
            $doc = $this->parse('<div class="foo bar baz">content</div>');
            $classes = [];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($classes) extends Visitor
            {
                public function __construct(private array &$classes) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->classes = $path->getClasses();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($classes)->toBe(['foo', 'bar', 'baz']);
        });

        it('returns empty array when no class attribute', function (): void {
            $doc = $this->parse('<div>content</div>');
            $classes = ['not-empty'];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($classes) extends Visitor
            {
                public function __construct(private array &$classes) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->classes = $path->getClasses();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($classes)->toBe([]);
        });
    });
});
