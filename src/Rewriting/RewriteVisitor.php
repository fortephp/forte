<?php

declare(strict_types=1);

namespace Forte\Rewriting;

interface RewriteVisitor
{
    /**
     * Called when entering a node, before visiting children.
     */
    public function enter(NodePath $path): void;

    /**
     * Called when leaving a node, after visiting children.
     */
    public function leave(NodePath $path): void;
}
