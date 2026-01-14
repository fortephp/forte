<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Node;

trait ManagesNodeMetadata
{
    /**
     * Set metadata on a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $key  The metadata key
     * @param  mixed  $value  The metadata value
     */
    public function setNodeData(int $nodeIndex, string $key, mixed $value): void
    {
        if (! isset($this->nodeMetadata[$nodeIndex])) {
            $this->nodeMetadata[$nodeIndex] = [];
        }

        $this->nodeMetadata[$nodeIndex][$key] = $value;
    }

    /**
     * Get metadata from a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $key  The metadata key
     * @param  mixed  $default  Default value if the key doesn't exist
     */
    public function getNodeData(int $nodeIndex, string $key, mixed $default = null): mixed
    {
        return $this->nodeMetadata[$nodeIndex][$key] ?? $default;
    }

    /**
     * Check if a node has metadata for a given key.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $key  The metadata key
     */
    public function hasNodeData(int $nodeIndex, string $key): bool
    {
        return isset($this->nodeMetadata[$nodeIndex][$key]);
    }

    /**
     * Remove metadata from a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $key  The metadata key to remove
     */
    public function removeNodeData(int $nodeIndex, string $key): void
    {
        unset($this->nodeMetadata[$nodeIndex][$key]);
    }

    /**
     * Get all metadata for a node.
     *
     * @param  int  $nodeIndex  The node index
     * @return array<string, mixed>
     */
    public function getAllNodeData(int $nodeIndex): array
    {
        return $this->nodeMetadata[$nodeIndex] ?? [];
    }

    /**
     * Add a tag to a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $tag  The tag to add
     */
    public function tagNode(int $nodeIndex, string $tag): void
    {
        if (! isset($this->nodeTags[$nodeIndex])) {
            $this->nodeTags[$nodeIndex] = [];
        }

        $this->nodeTags[$nodeIndex][$tag] = true;
    }

    /**
     * Remove a tag from a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $tag  The tag to remove
     */
    public function untagNode(int $nodeIndex, string $tag): void
    {
        unset($this->nodeTags[$nodeIndex][$tag]);
    }

    /**
     * Check if a node has a specific tag.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $tag  The tag to check
     */
    public function nodeHasTag(int $nodeIndex, string $tag): bool
    {
        return isset($this->nodeTags[$nodeIndex][$tag]);
    }

    /**
     * Get all tags for a node.
     *
     * @param  int  $nodeIndex  The node index
     * @return array<string>
     */
    public function getNodeTags(int $nodeIndex): array
    {
        return array_keys($this->nodeTags[$nodeIndex] ?? []);
    }

    /**
     * Find all nodes with a specific tag.
     *
     * @param  string  $tag  The tag to search for
     * @return array<Node>
     */
    public function findNodesByTag(string $tag): array
    {
        $results = [];
        foreach ($this->nodeTags as $nodeIndex => $tags) {
            if (isset($tags[$tag])) {
                $results[] = $this->getNode($nodeIndex);
            }
        }

        return $results;
    }
}
