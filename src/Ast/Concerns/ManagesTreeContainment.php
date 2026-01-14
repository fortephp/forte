<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Forte\Ast\Node;

trait ManagesTreeContainment
{
    /**
     * Check if this node contains another node as a descendant.
     */
    public function contains(Node $other): bool
    {
        if ($other->index() === $this->index()) {
            return false;
        }

        foreach ($other->ancestors() as $ancestor) {
            if ($ancestor->index() === $this->index() && $ancestor->sharesDocument($this->document)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the node has no children.
     */
    public function isLeaf(): bool
    {
        foreach ($this->children() as $_) {
            return false;
        }

        return true;
    }
}
