<?php

declare(strict_types=1);

namespace Forte\Ast\Document;

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Rewriting\RewriteBuilder;
use Illuminate\Support\Collection;

class Selection
{
    /** @var array<Node> */
    private array $nodes;

    /**
     * @param  array<Node>|Node  $nodes  The node(s) in this selection
     * @param  RewriteBuilder  $builder  The builder to queue operations
     */
    public function __construct(array|Node $nodes, private readonly RewriteBuilder $builder)
    {
        $this->nodes = is_array($nodes) ? $nodes : [$nodes];
    }

    /**
     * @return Collection<int, ElementNode>
     */
    private function elements(): Collection
    {
        /** @var Collection<int, ElementNode> */
        return collect($this->nodes)
            ->filter(fn (Node $node) => $node instanceof ElementNode)
            ->values();
    }

    /**
     * Add a CSS class to all selected Elements.
     */
    public function addClass(string $class): self
    {
        $this->elements()
            ->each(fn (ElementNode $node) => $this->builder->queueAddClass($node, $class));

        return $this;
    }

    /**
     * Remove a CSS class from all selected Elements.
     */
    public function removeClass(string $class): self
    {
        $this->elements()
            ->each(fn (ElementNode $node) => $this->builder->queueRemoveClass($node, $class));

        return $this;
    }

    /**
     * Set an attribute on all selected Elements.
     */
    public function setAttribute(string $name, string $value): self
    {
        $this->elements()
            ->each(fn (ElementNode $node) => $this->builder->queueSetAttribute($node, $name, $value));

        return $this;
    }

    /**
     * Remove an attribute from all selected Elements.
     */
    public function removeAttribute(string $name): self
    {
        $this->elements()
            ->each(fn (ElementNode $node) => $this->builder->queueRemoveAttribute($node, $name));

        return $this;
    }

    /**
     * Remove all selected nodes.
     */
    public function remove(): self
    {
        foreach ($this->nodes as $node) {
            $this->builder->queueRemove($node);
        }

        return $this;
    }

    /**
     * Replace all selected nodes with the given content.
     *
     * @param  string  $content  The replacement HTML/Blade content
     */
    public function replaceWith(string $content): self
    {
        foreach ($this->nodes as $node) {
            $this->builder->queueReplace($node, $content);
        }

        return $this;
    }

    /**
     * Wrap all selected nodes with an element.
     *
     * @param  string  $tagName  Tag name, optionally with class: 'div.wrapper'
     * @param  array<string, string>  $attributes  Additional attributes
     */
    public function wrapWith(string $tagName, array $attributes = []): self
    {
        if (str_contains($tagName, '.')) {
            $parts = explode('.', $tagName, 2);
            $tagName = $parts[0];
            $attributes['class'] = $parts[1];
        }

        foreach ($this->nodes as $node) {
            $this->builder->queueWrap($node, $tagName, $attributes);
        }

        return $this;
    }

    /**
     * Insert content before all selected nodes.
     *
     * @param  string  $content  The content to insert
     */
    public function insertBefore(string $content): self
    {
        foreach ($this->nodes as $node) {
            $this->builder->queueInsertBefore($node, $content);
        }

        return $this;
    }

    /**
     * Insert content after all selected nodes.
     *
     * @param  string  $content  The content to insert
     */
    public function insertAfter(string $content): self
    {
        foreach ($this->nodes as $node) {
            $this->builder->queueInsertAfter($node, $content);
        }

        return $this;
    }

    /**
     * Iterate over all selected nodes.
     *
     * @param  callable(Node, int): void  $callback
     */
    public function each(callable $callback): self
    {
        foreach ($this->nodes as $index => $node) {
            $callback($node, $index);
        }

        return $this;
    }

    /**
     * Filter the selection, returning a new Selection with matching nodes.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function filter(callable $predicate): self
    {
        $filteredNodes = array_values(array_filter($this->nodes, $predicate));

        return new self($filteredNodes, $this->builder);
    }

    /**
     * Get the first selected node.
     */
    public function first(): ?Node
    {
        return $this->nodes[0] ?? null;
    }

    /**
     * Get the last selected node.
     */
    public function last(): ?Node
    {
        return $this->nodes[count($this->nodes) - 1] ?? null;
    }

    /**
     * Get the count of selected nodes.
     */
    public function count(): int
    {
        return count($this->nodes);
    }

    /**
     * Check if the selection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->nodes) === 0;
    }

    /**
     * Check if the selection has nodes.
     */
    public function isNotEmpty(): bool
    {
        return count($this->nodes) > 0;
    }

    /**
     * Get all selected nodes as an array.
     *
     * @return array<Node>
     */
    public function all(): array
    {
        return $this->nodes;
    }
}
