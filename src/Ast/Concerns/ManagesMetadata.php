<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

trait ManagesMetadata
{
    /**
     * Set metadata on this node.
     *
     * @param  string  $key  The metadata key
     * @param  mixed  $value  The metadata value
     * @return $this
     */
    public function setData(string $key, mixed $value): static
    {
        $this->document->setNodeData($this->index, $key, $value);

        return $this;
    }

    /**
     * Get metadata from this node.
     *
     * @param  string  $key  The metadata key
     * @param  mixed  $default  Default value if the key doesn't exist
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->document->getNodeData($this->index, $key, $default);
    }

    /**
     * Check if this node has metadata for a given key.
     *
     * @param  string  $key  The metadata key
     */
    public function hasData(string $key): bool
    {
        return $this->document->hasNodeData($this->index, $key);
    }

    /**
     * Remove metadata from this node.
     *
     * @param  string  $key  The metadata key to remove
     * @return $this
     */
    public function removeData(string $key): static
    {
        $this->document->removeNodeData($this->index, $key);

        return $this;
    }

    /**
     * Get all metadata for this node.
     *
     * @return array<string, mixed>
     */
    public function getAllData(): array
    {
        return $this->document->getAllNodeData($this->index);
    }

    /**
     * Add a tag to this node.
     *
     * @param  string  $tag  The tag to add
     * @return $this
     */
    public function tag(string $tag): static
    {
        $this->document->tagNode($this->index, $tag);

        return $this;
    }

    /**
     * Remove a tag from this node.
     *
     * @param  string  $tag  The tag to remove
     * @return $this
     */
    public function untag(string $tag): static
    {
        $this->document->untagNode($this->index, $tag);

        return $this;
    }

    /**
     * Check if this node has a specific tag.
     *
     * @param  string  $tag  The tag to check
     */
    public function hasTag(string $tag): bool
    {
        return $this->document->nodeHasTag($this->index, $tag);
    }

    /**
     * Get all tags for this node.
     *
     * @return array<string> List of tags
     */
    public function getTags(): array
    {
        return $this->document->getNodeTags($this->index);
    }
}
