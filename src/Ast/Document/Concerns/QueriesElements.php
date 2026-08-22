<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Components\ComponentNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\ElementNode;
use Illuminate\Support\LazyCollection;

trait QueriesElements
{
    /** @var array<string, list<ElementNode>>|null */
    private ?array $elementsByName = null;

    /** @var array<string, ElementNode>|null */
    private ?array $elementsById = null;

    /**
     * Get elements as a lazy, fluent collection, optionally filtered by
     * exact case-insensitive tag name.
     *
     * @param  string|array<string>|null  $names
     * @return LazyCollection<int, ElementNode>
     */
    public function queryElements(string|array|null $names = null): LazyCollection
    {
        if (is_string($names)) {
            $this->buildElementIndexes();

            return LazyCollection::make($this->elementsByName[strtolower($names)] ?? []);
        }

        return $this->queryNodesOfType(
            ElementNode::class,
            fn (ElementNode $n) => ! $n instanceof ComponentNode
                && ($names === null || $n->isTag($names))
        );
    }

    /**
     * @return LazyCollection<int, ElementNode>
     *
     * @internal
     */
    protected function elements(): LazyCollection
    {
        return $this->queryElements();
    }

    /**
     * Get the first ordinary element, optionally filtered by tag name.
     *
     * @param  string|array<string>|null  $names
     */
    public function firstElement(string|array|null $names = null): ?ElementNode
    {
        /** @var ElementNode|null */
        return $this->queryElements($names)->first();
    }

    /**
     * Check whether the document contains an ordinary element with one of the
     * given tag names.
     *
     * @param  string|array<string>  $names
     */
    public function hasElement(string|array $names): bool
    {
        return $this->firstElement($names) !== null;
    }

    /**
     * Group exact tag-name lookups while preserving every requested key.
     *
     * This is useful when a caller needs several independently addressable
     * groups without repeatedly walking the document.
     *
     * @param  array<string>  $names
     * @return array<string, list<ElementNode>>
     */
    public function elementsGroupedByName(array $names): array
    {
        $this->buildElementIndexes();

        $groups = [];
        foreach ($names as $name) {
            $groups[$name] = $this->elementsByName[strtolower($name)] ?? [];
        }

        return $groups;
    }

    /**
     * Find the first ordinary element with an exact static ID.
     *
     * ID lookup is case-sensitive and preserves all source whitespace, matching
     * browser ID/IDREF behavior.
     */
    public function elementById(string $id): ?ElementNode
    {
        $this->buildElementIndexes();

        return $this->elementsById[$id] ?? null;
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
     * Get components as a lazy, fluent collection, optionally filtered by
     * name pattern.
     *
     * @param  string|array<string>|null  $names
     * @return LazyCollection<int, ComponentNode>
     */
    public function queryComponents(string|array|null $names = null): LazyCollection
    {
        $patterns = is_string($names) ? [$names] : $names;

        return $this->queryNodesOfType(
            ComponentNode::class,
            function (ComponentNode $node) use ($patterns): bool {
                if ($patterns === null) {
                    return true;
                }

                return $node->isAny($patterns);
            }
        );
    }

    /**
     * @return LazyCollection<int, ComponentNode>
     *
     * @internal
     */
    protected function components(): LazyCollection
    {
        return $this->queryComponents();
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
        return $this->queryElements()
            ->first(fn (ElementNode $node): bool => $node->is($tag));
    }

    /**
     * Find all elements with the given tag name.
     *
     * @param  string  $tag  Tag name to match
     * @return LazyCollection<int, ElementNode>
     */
    public function findElementsByName(string $tag): LazyCollection
    {
        return $this->queryElements()
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
        return $this->queryComponents($name)->first();
    }

    /**
     * Find all components with the given name.
     *
     * @param  string  $name  Component name to match
     * @return LazyCollection<int, ComponentNode>
     */
    public function findComponentsByName(string $name): LazyCollection
    {
        return $this->queryComponents($name);
    }

    private function buildElementIndexes(): void
    {
        if ($this->elementsByName !== null && $this->elementsById !== null) {
            return;
        }

        $this->elementsByName = [];
        $this->elementsById = [];

        foreach ($this->queryNodesOfType(ElementNode::class) as $element) {
            if ($element instanceof ComponentNode) {
                continue;
            }

            $this->elementsByName[strtolower($element->tagNameText())][] = $element;

            $id = $element->staticAttributeValue('id');
            if ($id !== null && $id !== '' && ! isset($this->elementsById[$id])) {
                $this->elementsById[$id] = $element;
            }
        }
    }
}
