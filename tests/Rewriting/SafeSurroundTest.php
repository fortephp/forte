<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('NodePath::safeSurround', function (): void {
    describe('non-root elements', function (): void {
        it('inserts start and end outside non-root element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('span')) {
                        $path->safeSurround(
                            Builder::comment(' start '),
                            Builder::comment(' end ')
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div><!-- start --><span>content</span><!-- end --></div>');
        });

        it('inserts outside when multiple root elements exist', function (): void {
            $doc = $this->parse('<div>first</div><div>second</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                private bool $done = false;

                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div') && ! $this->done) {
                        $path->safeSurround(
                            Builder::comment(' start '),
                            Builder::comment(' end ')
                        );
                        $this->done = true;
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<!-- start --><div>first</div><!-- end --><div>second</div>');
        });

        it('uses start as end when end is not provided', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('span')) {
                        $path->safeSurround(Builder::comment(' marker '));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div><!-- marker --><span>content</span><!-- marker --></div>');
        });
    });

    describe('sole root element', function (): void {
        it('inserts start and end inside sole root element', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $path->safeSurround(
                            Builder::comment(' start '),
                            Builder::comment(' end ')
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div><!-- start -->content<!-- end --></div>');
        });

        it('inserts inside with nested children', function (): void {
            $doc = $this->parse('<div><span>child</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div') && $path->isRoot()) {
                        $path->safeSurround(
                            Builder::comment(' component: start '),
                            Builder::comment(' component: end ')
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div><!-- component: start --><span>child</span><!-- component: end --></div>');
        });

        it('treats element with only whitespace siblings as sole root', function (): void {
            $doc = $this->parse("\n<div>content</div>\n");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $path->safeSurround(
                            Builder::comment(' start '),
                            Builder::comment(' end ')
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe("\n<div><!-- start -->content<!-- end --></div>\n");
        });

        it('preserves attributes on sole root element', function (): void {
            $doc = $this->parse('<div class="container" id="main">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $path->safeSurround(
                            Builder::comment(' wrapped '),
                            Builder::comment(' /wrapped ')
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div class="container" id="main"><!-- wrapped -->content<!-- /wrapped --></div>');
        });
    });

    describe('isSoleRootElement', function (): void {
        it('returns true for single root element', function (): void {
            $doc = $this->parse('<div>content</div>');
            $isSoleRoot = null;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($isSoleRoot) extends Visitor
            {
                public function __construct(private ?bool &$result) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $this->result = $path->isSoleRootElement();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($isSoleRoot)->toBe(true);
        });

        it('returns false for element with sibling elements', function (): void {
            $doc = $this->parse('<div>a</div><span>b</span>');
            $isSoleRoot = null;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($isSoleRoot) extends Visitor
            {
                public function __construct(private ?bool &$result) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $this->result = $path->isSoleRootElement();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($isSoleRoot)->toBe(false);
        });

        it('returns false for nested element', function (): void {
            $doc = $this->parse('<div><span>content</span></div>');
            $isSoleRoot = null;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($isSoleRoot) extends Visitor
            {
                public function __construct(private ?bool &$result) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isTag('span')) {
                        $this->result = $path->isSoleRootElement();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($isSoleRoot)->toBe(false);
        });

        it('returns false for non-element nodes', function (): void {
            $doc = $this->parse('text only');
            $isSoleRoot = null;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($isSoleRoot) extends Visitor
            {
                public function __construct(private ?bool &$result) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isText()) {
                        $this->result = $path->isSoleRootElement();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($isSoleRoot)->toBe(false);
        });

        it('returns true when only text siblings exist', function (): void {
            $doc = $this->parse("before\n<div>content</div>\nafter");
            $isSoleRoot = null;

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($isSoleRoot) extends Visitor
            {
                public function __construct(private ?bool &$result) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $this->result = $path->isSoleRootElement();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($isSoleRoot)->toBe(true);
        });
    });

    describe('use cases', function (): void {
        it('adds tracing comments for debugging', function (): void {
            $doc = $this->parse('<x-component>content</x-component>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    $elem = $path->asElement();
                    if ($elem && str_starts_with($elem->tagNameText(), 'x-')) {
                        $name = $elem->tagNameText();
                        $path->safeSurround(
                            Builder::comment(" {$name}: start "),
                            Builder::comment(" {$name}: end ")
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<x-component><!-- x-component: start -->content<!-- x-component: end --></x-component>');
        });

        it('preserves Livewire single root requirement', function (): void {
            $doc = $this->parse('<div wire:id="abc">@livewire content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    $elem = $path->asElement();
                    if ($elem && $elem->hasAttribute('wire:id')) {
                        $path->safeSurround(
                            Builder::bladeComment(' livewire: start '),
                            Builder::bladeComment(' livewire: end ')
                        );
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div wire:id="abc">{{-- livewire: start --}}@livewire content{{-- livewire: end --}}</div>');
        });
    });
});
