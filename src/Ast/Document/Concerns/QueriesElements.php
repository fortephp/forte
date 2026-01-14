<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Components\ComponentNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\ElementNode;
use Illuminate\Support\LazyCollection;

trait QueriesElements
{
    /**
     * @return LazyCollection<int, ElementNode>
     *
     * @internal
     */
    protected function elements(): LazyCollection
    {
        return $this->queryNodesOfType(
            ElementNode::class,
            fn (ElementNode $n) => ! $n instanceof ComponentNode
        );
    }

    /**
     * Get all elements.
     *
     * @return NodeCollection<int, ElementNode>
     */
    public function getElements(): NodeCollection
    {
        return NodeCollection::make($this->elements());
    }

    /**
     * @return LazyCollection<int, ComponentNode>
     *
     * @internal
     */
    protected function components(): LazyCollection
    {
        return $this->queryNodesOfType(ComponentNode::class);
    }

    /**
     * Get all components.
     *
     * @return NodeCollection<int, ComponentNode>
     */
    public function getComponents(): NodeCollection
    {
        return NodeCollection::make($this->components());
    }

    /**
     * Find the first element with the given tag name.
     *
     * @param  string  $tag  Tag name to match
     */
    public function findElementByName(string $tag): ?ElementNode
    {
        /** @var ElementNode|null */
        return $this->elements()
            ->filter(fn (ElementNode $n) => $n->is($tag))
            ->first();
    }

    /**
     * Find all elements with the given tag name.
     *
     * @param  string  $tag  Tag name to match
     * @return LazyCollection<int, ElementNode>
     */
    public function findElementsByName(string $tag): LazyCollection
    {
        return $this->elements()
            ->filter(fn (ElementNode $n) => $n->is($tag));
    }

    /**
     * Find the first component with the given name.
     *
     * @param  string  $name  Component name to match
     */
    public function findComponentByName(string $name): ?ComponentNode
    {
        /** @var ComponentNode|null */
        return $this->components()
            ->filter(fn (ComponentNode $n) => $n->is($name))
            ->first();
    }

    /**
     * Find all components with the given name.
     *
     * @param  string  $name  Component name to match
     * @return LazyCollection<int, ComponentNode>
     */
    public function findComponentsByName(string $name): LazyCollection
    {
        return $this->components()
            ->filter(fn (ComponentNode $n) => $n->is($name));
    }
}
