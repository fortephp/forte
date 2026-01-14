<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Node;

trait ManagesChildren
{
    /**
     * Get the root-level children of the document.
     *
     * @return iterable<Node>
     */
    public function children(): iterable
    {
        foreach ($this->rootChildren as $childIdx) {
            yield $this->getNode($childIdx);
        }
    }

    /**
     * Get the root nodes.
     *
     * @return NodeCollection<int, Node>
     */
    public function getRootNodes(): NodeCollection
    {
        return NodeCollection::make($this->getChildren());
    }

    /**
     * Get the root-level children.
     *
     * @return array<int, Node>
     */
    public function getChildren(): array
    {
        if ($this->childrenCache === null) {
            $this->childrenCache = array_values(iterator_to_array($this->children()));
        }

        return $this->childrenCache;
    }

    /**
     * Get a root child by its index position.
     *
     * @param  int  $index  Zero-based index into root children
     */
    public function getChildAt(int $index): ?Node
    {
        if ($index < 0 || $index >= count($this->rootChildren)) {
            return null;
        }

        return $this->getNode($this->rootChildren[$index]);
    }

    /**
     * Get the first root-level child, or null if empty.
     */
    public function firstChild(): ?Node
    {
        return $this->getChildAt(0);
    }

    /**
     * Get the last root-level child, or null if empty.
     */
    public function lastChild(): ?Node
    {
        return $this->getChildAt(count($this->rootChildren) - 1);
    }

    /**
     * Get the first root-level child matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function firstChildWhere(callable $predicate): ?Node
    {
        foreach ($this->children() as $child) {
            if ($predicate($child)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Get the first root-level child of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function firstChildOfType(string $class): ?Node
    {
        foreach ($this->children() as $child) {
            if ($child instanceof $class) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Get all root-level children of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return iterable<T>
     */
    public function childrenOfType(string $class): iterable
    {
        foreach ($this->children() as $child) {
            if ($child instanceof $class) {
                yield $child;
            }
        }
    }

    /**
     * Get all root-level children of a specific type, as an array.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return array<T>
     */
    public function getChildrenOfType(string $class): array
    {
        return iterator_to_array($this->childrenOfType($class));
    }

    /**
     * Get the number of root-level children.
     */
    public function childCount(): int
    {
        return count($this->rootChildren);
    }
}
