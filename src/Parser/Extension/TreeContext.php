<?php

declare(strict_types=1);

namespace Forte\Parser\Extension;

use Forte\Parser\TreeBuilder;

readonly class TreeContext
{
    public function __construct(private TreeBuilder $builder) {}

    /**
     * Get current position in token stream.
     */
    public function position(): int
    {
        return $this->builder->position();
    }

    /**
     * Get total token count.
     */
    public function tokenCount(): int
    {
        return $this->builder->getTokenCount();
    }

    /**
     * Get the current token, or null if at the end.
     *
     * @return array{type: int, start: int, end: int}|null
     */
    public function currentToken(): ?array
    {
        return $this->builder->getCurrentToken();
    }

    /**
     * Peek at the token at an offset from the current position.
     *
     * @param  int  $offset  Offset from current position (0 = current)
     * @return array{type: int, start: int, end: int}|null
     */
    public function peekToken(int $offset = 0): ?array
    {
        return $this->builder->peekToken($offset);
    }

    /**
     * Get the source string.
     */
    public function source(): string
    {
        return $this->builder->source();
    }

    /**
     * Get token text from source.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public function tokenText(array $token): string
    {
        return substr($this->source(), $token['start'], $token['end'] - $token['start']);
    }

    /**
     * Create a new node and add it to the tree.
     *
     * @param  int  $kind  Node kind (NodeKind constant or extension kind)
     * @param  int  $tokenStart  Starting token index
     * @param  int  $tokenCount  Number of tokens this node spans
     * @param  int  $data  Optional node data (defaults to 0)
     * @return int The index of the created node
     */
    public function addNode(int $kind, int $tokenStart, int $tokenCount, int $data = 0): int
    {
        return $this->builder->createExtensionNode($kind, $tokenStart, $tokenCount, $data);
    }

    /**
     * Add a node as a child of the current parent.
     *
     * @param  int  $nodeIndex  The node index to add as a child
     */
    public function addChild(int $nodeIndex): void
    {
        $this->builder->addChildNode($nodeIndex);
    }

    /**
     * Push a node onto the open element stack.
     *
     * Use this for paired constructs that can contain children.
     *
     * @param  int  $nodeIndex  The node index to push
     */
    public function pushElement(int $nodeIndex): void
    {
        $this->builder->pushOpenElement($nodeIndex);
    }

    /**
     * Pop the current element from the stack.
     */
    public function popElement(): ?int
    {
        return $this->builder->popOpenElement();
    }

    /**
     * Get the current parent node index.
     */
    public function currentParent(): int
    {
        return $this->builder->getCurrentParent();
    }

    /**
     * Set metadata on a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $key  Metadata key
     * @param  mixed  $value  Metadata value
     */
    public function setNodeMeta(int $nodeIndex, string $key, mixed $value): void
    {
        $this->builder->setNodeMetadata($nodeIndex, $key, $value);
    }

    /**
     * Get metadata from a node.
     *
     * @param  int  $nodeIndex  The node index
     * @param  string  $key  Metadata key
     */
    public function getNodeMeta(int $nodeIndex, string $key): mixed
    {
        return $this->builder->getNodeMetadata($nodeIndex, $key);
    }

    /**
     * Advance position by a number of tokens.
     */
    public function advance(int $tokens = 1): void
    {
        $this->builder->advancePosition($tokens);
    }
}
