<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

trait ManagesSiblingPredicates
{
    /**
     * Check if this is the first child of its parent.
     */
    public function isFirstChild(): bool
    {
        return $this->previousSibling() === null;
    }

    /**
     * Check if this is the last child of its parent.
     */
    public function isLastChild(): bool
    {
        return $this->nextSibling() === null;
    }

    /**
     * Check if this is the only child of its parent.
     */
    public function isOnlyChild(): bool
    {
        return $this->isFirstChild() && $this->isLastChild();
    }

    /**
     * Check if this node has a previous sibling.
     */
    public function hasPreviousSibling(): bool
    {
        return $this->previousSibling() !== null;
    }

    /**
     * Check if this node has a next sibling.
     */
    public function hasNextSibling(): bool
    {
        return $this->nextSibling() !== null;
    }
}
