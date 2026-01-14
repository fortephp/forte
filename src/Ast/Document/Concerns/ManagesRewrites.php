<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\RewriteBuilder;
use Forte\Rewriting\Rewriter;

trait ManagesRewrites
{
    /**
     * Rewrite this document using a callback.
     *
     * @param  callable(NodePath): void  $callback
     */
    public function rewriteWith(callable $callback): self
    {
        $rewriter = new Rewriter;
        $rewriter->addVisitor(new CallbackVisitor($callback));

        return $rewriter->rewrite($this);
    }

    /**
     * Apply one or more AST rewriters to this document.
     *
     * @param  AstRewriter  ...$rewriters  Rewriters to apply, in order
     */
    public function apply(AstRewriter ...$rewriters): self
    {
        $doc = $this;

        foreach ($rewriters as $rewriter) {
            $doc = $rewriter->rewrite($doc);
        }

        return $doc;
    }

    /**
     * Creates a new rewriter for the document.
     *
     * @param  callable(RewriteBuilder): void  $callback
     */
    public function rewrite(callable $callback): self
    {
        $builder = new RewriteBuilder($this);
        $callback($builder);

        return $builder->apply();
    }
}
