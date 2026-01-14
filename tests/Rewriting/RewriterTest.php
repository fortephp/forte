<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Basic Rewriting', function (): void {
    it('returns unchanged document when no visitors', function (): void {
        $doc = $this->parse('<div>Hello</div>');
        $rewriter = new Rewriter;

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>Hello</div>');
    });

    it('removes nodes via visitor', function (): void {
        $doc = $this->parse('<!-- comment --><div>keep</div>');

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

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>keep</div>');
    });

    it('replaces nodes with specs', function (): void {
        $doc = $this->parse('<div>old</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceWith(Builder::element('span')->text('new'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<span>new</span>');
    });

    it('replaces nodes with raw strings', function (): void {
        $doc = $this->parse('<div>old</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceWith('<span>replaced</span>');
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<span>replaced</span>');
    });

    it('inserts before nodes', function (): void {
        $doc = $this->parse('<div>content</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertBefore(Builder::comment('before'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<!-- before --><div>content</div>');
    });

    it('inserts after nodes', function (): void {
        $doc = $this->parse('<div>content</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertAfter(Builder::comment('after'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>content</div><!-- after -->');
    });

    it('inserts before and after nodes', function (): void {
        $doc = $this->parse('<div>content</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertBefore(Builder::comment('before'));
                    $path->insertAfter(Builder::comment('after'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<!-- before --><div>content</div><!-- after -->');
    });

    it('transforms directives', function (): void {
        $doc = $this->parse('@include("header")');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isDirectiveNamed('include')) {
                    $path->replaceWith(Builder::phpTag(' require "header.php";'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<?php require "header.php"; ?>');
    });

    it('traverses nested structures', function (): void {
        $doc = $this->parse('<div><span>text</span></div>');
        $visited = [];

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($visited) extends Visitor
        {
            public function __construct(private array &$visited) {}

            public function enter(NodePath $path): void
            {
                if ($path->isElement()) {
                    $this->visited[] = $path->asElement()->tagNameText();
                }
            }
        });

        $rewriter->rewrite($doc);

        expect($visited)->toBe(['div', 'span']);
    });

    it('provides parent access', function (): void {
        $doc = $this->parse('<div><span>text</span></div>');
        $parentTag = null;

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($parentTag) extends Visitor
        {
            public function __construct(private ?string &$parentTag) {}

            public function enter(NodePath $path): void
            {
                if ($path->isTag('span')) {
                    $parent = $path->parent();
                    if ($parent instanceof ElementNode) {
                        $this->parentTag = $parent->tagNameText();
                    }
                }
            }
        });

        $rewriter->rewrite($doc);

        expect($parentTag)->toBe('div');
    });

    it('skips children when requested', function (): void {
        $doc = $this->parse('<div><span>skip</span></div>');
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

        expect($visited)->toBe(['div']);
    });

    it('stops traversal when requested', function (): void {
        $doc = $this->parse('<div>a</div><span>b</span><p>c</p>');
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

        expect($visited)->toBe(['div', 'span']);
    });

    it('handles multiple visitors', function (): void {
        $doc = $this->parse('<!-- remove --><div>text</div>');

        $visitor1 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isComment()) {
                    $path->remove();
                }
            }
        };

        $visitor2 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertAfter(Builder::comment('added'));
                }
            }
        };

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor1);
        $rewriter->addVisitor($visitor2);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>text</div><!-- added -->');
    });

    it('preserves unchanged nodes', function (): void {
        $doc = $this->parse('<div class="keep">content</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor {});

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div class="keep">content</div>');
    });

    describe('contextual safe spacing', function (): void {
        it('adds space before safe directive when following word character', function (): void {
            $doc = $this->parse('<div>text</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && $path->node()->getDocumentContent() === 'text') {
                        $path->insertAfter(Builder::safeDirective('endif'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div>text @endif</div>');
        });

        it('does not add space when following element closing tag', function (): void {
            $doc = $this->parse('<div><span>inner</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isTag('span')) {
                        $path->insertAfter(Builder::safeDirective('endif'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div><span>inner</span>@endif</div>');
        });

        it('does not add space when directive is first child', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText()) {
                        $path->insertBefore(Builder::safeDirective('if', '($show)'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div>@if($show)content</div>');
        });

        it('adds space when following another safe directive', function (): void {
            $doc = $this->parse('@continue');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveNamed('continue')) {
                        $path->insertAfter(Builder::safeDirective('endforeach'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('@continue @endforeach');
        });

        it('does not add space when following punctuation', function (): void {
            $doc = $this->parse('<div>Hello!</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && str_contains($path->node()->getDocumentContent(), 'Hello')) {
                        $path->insertAfter(Builder::safeDirective('endif'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div>Hello!@endif</div>');
        });

        it('normal directive does not get contextual spacing', function (): void {
            $doc = $this->parse('<div>text</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isText() && $path->node()->getDocumentContent() === 'text') {
                        $path->insertAfter(Builder::directive('endif'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())->toBe('<div>text@endif</div>');
        });
    });
});
