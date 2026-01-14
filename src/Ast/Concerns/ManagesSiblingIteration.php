<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Forte\Ast\Node;

trait ManagesSiblingIteration
{
    /**
     * Get all siblings, excluding the node itself.
     *
     * @return iterable<Node>
     */
    public function siblings(): iterable
    {
        $parent = $this->getParent();
        if ($parent === null) {
            return;
        }

        foreach ($parent->children() as $child) {
            if ($child->index() !== $this->index()) {
                yield $child;
            }
        }
    }

    /**
     *  Get all siblings, excluding the node itself, as an array.
     *
     * @return array<Node>
     */
    public function getSiblings(): array
    {
        return iterator_to_array($this->siblings());
    }

    /**
     * Get siblings before this node.
     *
     * @return iterable<Node>
     */
    public function previousSiblings(): iterable
    {
        $parent = $this->getParent();
        if ($parent === null) {
            return;
        }

        foreach ($parent->children() as $child) {
            if ($child->index() === $this->index()) {
                return;
            }
            yield $child;
        }
    }

    /**
     * Get siblings before this node, as an array.
     *
     * @return array<Node>
     */
    public function getPreviousSiblings(): array
    {
        return iterator_to_array($this->previousSiblings());
    }

    /**
     * Get siblings after this node.
     *
     * @return iterable<Node>
     */
    public function nextSiblings(): iterable
    {
        $parent = $this->getParent();
        if ($parent === null) {
            return;
        }

        $foundSelf = false;
        foreach ($parent->children() as $child) {
            if ($foundSelf) {
                yield $child;
            } elseif ($child->index() === $this->index()) {
                $foundSelf = true;
            }
        }
    }

    /**
     * Get siblings after this node, as an array.
     *
     * @return array<Node>
     */
    public function getNextSiblings(): array
    {
        return iterator_to_array($this->nextSiblings());
    }

    /**
     * Finds the next sibling matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function nextSiblingWhere(callable $predicate): ?Node
    {
        $sibling = $this->nextSibling();
        while ($sibling !== null) {
            if ($predicate($sibling)) {
                return $sibling;
            }

            $sibling = $sibling->nextSibling();
        }

        return null;
    }

    /**
     * Find the previous sibling matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function previousSiblingWhere(callable $predicate): ?Node
    {
        $sibling = $this->previousSibling();
        while ($sibling !== null) {
            if ($predicate($sibling)) {
                return $sibling;
            }

            $sibling = $sibling->previousSibling();
        }

        return null;
    }

    /**
     * Find the next sibling of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function nextSiblingOfType(string $class): ?Node
    {
        /** @var T|null */
        return $this->nextSiblingWhere(fn (Node $node) => $node instanceof $class);
    }

    /**
     * Find the previous sibling of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function previousSiblingOfType(string $class): ?Node
    {
        /** @var T|null */
        return $this->previousSiblingWhere(fn (Node $node) => $node instanceof $class);
    }
}
