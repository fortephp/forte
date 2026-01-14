<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Forte\Ast\Node;

trait ManagesAncestorNavigation
{
    /**
     * Get all ancestors from parent up to, but not including, the document root.
     *
     * @return iterable<Node>
     */
    public function ancestors(): iterable
    {
        $parent = $this->getParent();
        while ($parent !== null) {
            yield $parent;
            $parent = $parent->getParent();
        }
    }

    /**
     * Get all ancestors as an array.
     *
     * @return Node[]
     */
    public function getAncestors(): array
    {
        return iterator_to_array($this->ancestors(), false);
    }

    /**
     * Find the closest ancestor matching a predicate.
     *
     * Searches from parent upward, returns first match or null.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function closest(callable $predicate): ?Node
    {
        foreach ($this->ancestors() as $ancestor) {
            if ($predicate($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Find the closest ancestor of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function closestOfType(string $class): ?Node
    {
        /** @var T|null */
        return $this->closest(fn (Node $node) => $node instanceof $class);
    }

    /**
     * Check if any ancestor matches a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function hasAncestorWhere(callable $predicate): bool
    {
        return $this->closest($predicate) !== null;
    }

    /**
     * Get the depth of this node in the tree.
     */
    public function depth(): int
    {
        $depth = 0;
        foreach ($this->ancestors() as $_) {
            $depth++;
        }

        return $depth;
    }
}
