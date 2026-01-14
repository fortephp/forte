<?php

declare(strict_types=1);

namespace Forte\Parser;

use RuntimeException;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class NodeKindRegistry
{
    /**
     * @var array<int, ExtensionNodeKind>
     */
    private array $kinds = [];

    /**
     * @var array<string, int>
     */
    private array $lookup = [];

    /**
     * Next available kind ID.
     */
    private int $nextId = NodeKind::EXTENSION_BASE;

    /**
     * Register a new extension node kind.
     *
     * @param  string  $namespace  Extension namespace (e.g., 'myext')
     * @param  string  $name  Kind name (e.g., 'Shortcode')
     * @param  class-string|null  $nodeClass  Optional custom Node subclass
     * @param  string|null  $label  Optional human-readable label
     * @param  string|null  $domElement  Optional DOM element name for XPath (e.g., 'marker' → <forte:marker>)
     * @param  string|null  $category  Optional category (e.g., 'Attribute' for attribute extension kinds)
     *
     * @throws RuntimeException
     */
    public function register(
        string $namespace,
        string $name,
        ?string $nodeClass = null,
        ?string $label = null,
        ?string $domElement = null,
        ?string $category = null
    ): int {
        $key = "{$namespace}::{$name}";

        if (isset($this->lookup[$key])) {
            return $this->lookup[$key];
        }

        $id = $this->nextId++;

        $this->kinds[$id] = new ExtensionNodeKind(
            id: $id,
            namespace: $namespace,
            name: $name,
            label: $label ?? $name,
            nodeClass: $nodeClass,
            domElement: $domElement,
            category: $category,
        );

        $this->lookup[$key] = $id;

        return $id;
    }

    /**
     * Get the human-readable name for an extension kind.
     */
    public function name(int $id): string
    {
        return $this->kinds[$id]->label ?? "Extension({$id})";
    }

    /**
     * Get full metadata for an extension kind.
     */
    public function get(int $id): ?ExtensionNodeKind
    {
        return $this->kinds[$id] ?? null;
    }

    /**
     * Get the custom Node class for an extension kind.
     *
     * @return class-string|null
     */
    public function getNodeClass(int $id): ?string
    {
        $kind = $this->kinds[$id] ?? null;

        if ($kind === null) {
            return null;
        }

        return $kind->nodeClass;
    }

    /**
     * Get the DOM element name for an extension kind.
     */
    public function getDomElement(int $id): ?string
    {
        $kind = $this->kinds[$id] ?? null;

        return $kind?->domElement;
    }

    /**
     * Check if a kind ID is registered.
     */
    public function has(int $id): bool
    {
        return isset($this->kinds[$id]);
    }

    /**
     * Get all registered extension kinds.
     *
     * @return array<int, ExtensionNodeKind>
     */
    public function all(): array
    {
        return $this->kinds;
    }

    /**
     * Get kind ID by namespace and name.
     */
    public function getId(string $namespace, string $name): ?int
    {
        return $this->lookup["{$namespace}::{$name}"] ?? null;
    }

    /**
     * Get kind ID by full key (e.g., "hashtag::Hashtag").
     */
    public function getIdByKey(string $key): ?int
    {
        return $this->lookup[$key] ?? null;
    }

    /**
     * Check if a node matches a string identifier.
     *
     * @phpstan-param  FlatNode  $node
     */
    public function matches(array $node, string $key): bool
    {
        $id = $this->lookup[$key] ?? null;

        return $id !== null && $node['kind'] === $id;
    }

    /**
     * Check if a kind ID is an attribute extension kind.
     */
    public function isAttributeKind(int $id): bool
    {
        $kind = $this->kinds[$id] ?? null;

        return $kind !== null && $kind->category === 'Attribute';
    }
}
