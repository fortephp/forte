<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Document::rewrite()', function (): void {
    it('removes comments using inline callback', function (): void {
        $doc = $this->parse('<!-- comment --><div>keep</div>');

        $result = $doc->rewriteWith(function (NodePath $path): void {
            if ($path->isComment()) {
                $path->remove();
            }
        });

        expect($result->render())->toBe('<div>keep</div>')
            ->and($doc->render())->toBe('<!-- comment --><div>keep</div>');
    });

    it('replaces elements using inline callback', function (): void {
        $doc = $this->parse('<div>old</div>');

        $result = $doc->rewriteWith(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->replaceWith(Builder::element('span')->text('new'));
            }
        });

        expect($result->render())->toBe('<span>new</span>');
    });

    it('adds classes using inline callback', function (): void {
        $doc = $this->parse('<div>content</div>');

        $result = $doc->rewriteWith(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->addClass('active');
            }
        });

        expect($result->render())->toBe('<div class="active">content</div>');
    });

    it('inserts before and after using inline callback', function (): void {
        $doc = $this->parse('<div>content</div>');

        $result = $doc->rewriteWith(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->insertBefore(Builder::comment('before'));
                $path->insertAfter(Builder::comment('after'));
            }
        });

        expect($result->render())->toBe('<!-- before --><div>content</div><!-- after -->');
    });

    it('renames tags using inline callback', function (): void {
        $doc = $this->parse('<div>content</div>');

        $result = $doc->rewriteWith(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->renameTag('section');
            }
        });

        expect($result->render())->toBe('<section>content</section>');
    });

    it('chains multiple rewriters', function (): void {
        $doc = $this->parse('<!-- remove --><div>content</div>');

        $result = $doc
            ->rewriteWith(fn (NodePath $path) => $path->isComment() && $path->remove())
            ->rewriteWith(fn (NodePath $path) => $path->isTag('div') && $path->addClass('active'));

        expect($result->render())->toBe('<div class="active">content</div>');
    });
});

describe('Document::apply()', function (): void {
    it('applies a single rewriter', function (): void {
        $doc = $this->parse('<!-- comment --><div>keep</div>');

        $rewriter = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
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

                return $rewriter->rewrite($doc);
            }
        };

        $result = $doc->apply($rewriter);

        expect($result->render())->toBe('<div>keep</div>')
            ->and($doc->render())->toBe('<!-- comment --><div>keep</div>');
    });

    it('chains multiple rewriters', function (): void {
        $doc = $this->parse('<!-- remove --><div>content</div>');

        $removeComments = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
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

                return $rewriter->rewrite($doc);
            }
        };

        $addClass = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                $rewriter = new Rewriter;
                $rewriter->addVisitor(new class extends Visitor
                {
                    public function enter(NodePath $path): void
                    {
                        if ($path->isTag('div')) {
                            $path->addClass('active');
                        }
                    }
                });

                return $rewriter->rewrite($doc);
            }
        };

        $result = $doc
            ->apply($removeComments)
            ->apply($addClass);

        expect($result->render())->toBe('<div class="active">content</div>');
    });

    it('applies multiple rewriters in single call', function (): void {
        $doc = $this->parse('<!-- remove --><div>content</div>');

        $removeComments = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                return $doc->rewriteWith(fn (NodePath $p) => $p->isComment() && $p->remove());
            }
        };

        $addClass = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                return $doc->rewriteWith(fn (NodePath $p) => $p->isTag('div') && $p->addClass('active'));
            }
        };

        $result = $doc->apply($removeComments, $addClass);

        expect($result->render())->toBe('<div class="active">content</div>');
    });

    it('returns same document when no rewriters provided', function (): void {
        $doc = $this->parse('<div>content</div>');

        $result = $doc->apply();

        expect($result)->toBe($doc);
    });
});
