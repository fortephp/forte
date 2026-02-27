<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

require_once __DIR__.'/Datasets/OperationContexts.php';

describe('Basic Operations', function (): void {
    describe('remove()', function (): void {
        it('removes node in context: {0}', function (
            string $input,
            string $expected,
            string $nodeType,
            ?string $targetTag,
            ?string $textMatch = null,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($nodeType, $targetTag, $textMatch) extends Visitor
            {
                public function __construct(
                    private readonly string $nodeType,
                    private readonly ?string $targetTag,
                    private readonly ?string $textMatch
                ) {}

                public function enter(NodePath $path): void
                {
                    $matches = match ($this->nodeType) {
                        'element' => $path->isElement() && ($this->targetTag === null || $path->isTag($this->targetTag)),
                        'text' => $path->isText() && ($this->textMatch === null || str_contains($path->node()->getDocumentContent(), $this->textMatch)),
                        'echo' => $path->isEcho(),
                        'comment' => $path->isComment(),
                        'directive' => $path->isDirective(),
                        'directive_block' => $path->isDirectiveBlock(),
                        default => false,
                    };

                    if ($matches) {
                        $path->remove();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('remove contexts');

    });

    describe('replaceWith()', function (): void {
        it('replaces node in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
            string $replacement,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag, $replacement) extends Visitor
            {
                public function __construct(
                    private readonly string $targetTag,
                    private readonly string $replacement
                ) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $replacement = match ($this->replacement) {
                            'text' => 'replaced',
                            'element' => Builder::element('strong')->text('new'),
                            default => $this->replacement,
                        };
                        $path->replaceWith($replacement);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('replaceWith contexts');
    });

    describe('insertBefore()', function (): void {
        it('inserts before node in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag) extends Visitor
            {
                public function __construct(private readonly string $targetTag) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $path->insertBefore('prefix');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('insertBefore contexts');
    });

    describe('insertAfter()', function (): void {
        it('inserts after node in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag) extends Visitor
            {
                public function __construct(private readonly string $targetTag) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $path->insertAfter('suffix');
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('insertAfter contexts');
    });

    describe('wrapWith()', function (): void {
        it('wraps node in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
            string $wrapTag = 'div',
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag, $wrapTag) extends Visitor
            {
                public function __construct(
                    private readonly string $targetTag,
                    private readonly string $wrapTag
                ) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $wrapper = $this->wrapTag === 'div'
                            ? Builder::element('div')->class('wrapper')
                            : Builder::element($this->wrapTag);
                        $path->wrapWith($wrapper);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('wrapWith contexts');
    });

    describe('unwrap()', function (): void {
        it('unwraps node in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag) extends Visitor
            {
                public function __construct(private readonly string $targetTag) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $path->unwrap();
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('unwrap contexts');
    });

    describe('setAttribute()', function (): void {
        it('sets attribute in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
            string $attr,
            string $value,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag, $attr, $value) extends Visitor
            {
                public function __construct(
                    private readonly string $targetTag,
                    private readonly string $attr,
                    private readonly string $value
                ) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $path->setAttribute($this->attr, $this->value);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('setAttribute contexts');
    });

    describe('addClass()', function (): void {
        it('adds class in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
            string $class,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag, $class) extends Visitor
            {
                public function __construct(
                    private readonly string $targetTag,
                    private readonly string $class
                ) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $path->addClass($this->class);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('addClass contexts');
    });

    describe('renameTag()', function (): void {
        it('renames tag in context: {0}', function (
            string $input,
            string $expected,
            string $targetTag,
            string $newTag,
        ): void {
            $doc = $this->parse($input);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($targetTag, $newTag) extends Visitor
            {
                public function __construct(
                    private readonly string $targetTag,
                    private readonly string $newTag
                ) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag($this->targetTag)) {
                        $path->renameTag($this->newTag);
                    }
                }
            });

            expect($rewriter->rewrite($doc)->render())
                ->toBe($expected);
        })->with('renameTag contexts');
    });
});
