<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\ElementNode;
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
     * Get all ancestors as a fluent node collection.
     *
     * @return NodeCollection<int, Node>
     */
    public function ancestorNodes(): NodeCollection
    {
        return new NodeCollection($this->getAncestors());
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
     * Check if any ancestor is an instance of the given class.
     *
     * @param  class-string<Node>  $class
     */
    public function hasAncestorOfType(string $class): bool
    {
        return $this->closestOfType($class) !== null;
    }

    /**
     * Find the nearest ancestor element, optionally restricted by tag name.
     *
     * @param  string|array<string>|null  $names
     */
    public function closestElement(string|array|null $names = null): ?ElementNode
    {
        /** @var ElementNode|null */
        return $this->closest(fn (Node $node): bool => $node instanceof ElementNode
            && ($names === null || $node->isTag($names)));
    }

    /**
     * Check whether an ancestor element has one of the given tag names.
     *
     * @param  string|array<string>  $names
     */
    public function hasAncestorElement(string|array $names): bool
    {
        return $this->closestElement($names) !== null;
    }

    /**
     * Find the nearest paired directive ancestor, optionally by name.
     *
     * @param  string|array<string>|null  $names
     */
    public function closestDirectiveBlock(string|array|null $names = null): ?DirectiveBlockNode
    {
        $names = is_string($names) ? [$names] : $names;

        /** @var DirectiveBlockNode|null */
        return $this->closest(function (Node $node) use ($names): bool {
            if (! $node instanceof DirectiveBlockNode) {
                return false;
            }

            if ($names === null) {
                return true;
            }

            foreach ($names as $name) {
                if ($node->isDirectiveNamed($name)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Check whether a paired directive ancestor has one of the given names.
     *
     * @param  string|array<string>  $names
     */
    public function hasAncestorDirective(string|array $names): bool
    {
        return $this->closestDirectiveBlock($names) !== null;
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
