<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Rewriting\Builders\NodeBuilder;
use Forte\Rewriting\Builders\RawBuilder;

/**
 * @internal
 */
class RewriteContext
{
    /** @var array<Operation> Queued operations keyed by node index */
    private array $operations = [];

    /** @var array<int, bool> Nodes marked to skip children */
    private array $skipChildren = [];

    private bool $stopRequested = false;

    public function queueRemove(Node $node): void
    {
        $this->operations[$node->index()] = new Operation(
            OperationType::Remove,
            $node
        );
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $replacement
     */
    public function queueReplace(Node $node, NodeBuilder|array|string $replacement): void
    {
        $existing = $this->operations[$node->index()] ?? null;
        $op = new Operation(
            OperationType::Replace,
            $node,
            replacement: $this->normalizeSpecs($replacement)
        );

        // Preserve insertBefore/insertAfter from an existing operation
        if ($existing !== null) {
            $op->insertBefore = $existing->insertBefore;
            $op->insertAfter = $existing->insertAfter;
        }

        $this->operations[$node->index()] = $op;
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $nodes
     */
    public function queueInsertBefore(Node $node, NodeBuilder|array|string $nodes): void
    {
        $op = $this->operations[$node->index()] ?? new Operation(OperationType::Keep, $node);
        $op->insertBefore = array_merge($op->insertBefore, $this->normalizeSpecs($nodes));
        $this->operations[$node->index()] = $op;
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $nodes
     */
    public function queueInsertAfter(Node $node, NodeBuilder|array|string $nodes): void
    {
        $op = $this->operations[$node->index()] ?? new Operation(OperationType::Keep, $node);
        $op->insertAfter = array_merge($op->insertAfter, $this->normalizeSpecs($nodes));
        $this->operations[$node->index()] = $op;
    }

    public function queueWrap(Node $node, NodeBuilder $wrapper): void
    {
        $this->operations[$node->index()] = new Operation(
            OperationType::Wrap,
            $node,
            wrapper: $wrapper
        );
    }

    public function queueUnwrap(Node $node): void
    {
        $this->operations[$node->index()] = new Operation(
            OperationType::Unwrap,
            $node
        );
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $children
     */
    public function queueReplaceChildren(Node $node, NodeBuilder|array|string $children): void
    {
        $this->operations[$node->index()] = new Operation(
            OperationType::ReplaceChildren,
            $node,
            replacement: $this->normalizeSpecs($children)
        );
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $children
     */
    public function queuePrependChildren(Node $node, NodeBuilder|array|string $children): void
    {
        $op = $this->operations[$node->index()] ?? new Operation(OperationType::Keep, $node);
        $op->prependChildren = array_merge($this->normalizeSpecs($children), $op->prependChildren);
        $this->operations[$node->index()] = $op;
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $children
     */
    public function queueAppendChildren(Node $node, NodeBuilder|array|string $children): void
    {
        $op = $this->operations[$node->index()] ?? new Operation(OperationType::Keep, $node);
        $op->appendChildren = array_merge($op->appendChildren, $this->normalizeSpecs($children));
        $this->operations[$node->index()] = $op;
    }

    public function queueSetAttribute(ElementNode $element, string $name, string $value): void
    {
        $op = $this->operations[$element->index()] ?? new Operation(OperationType::Keep, $element);
        $op->attributeChanges[$name] = $value;
        $this->operations[$element->index()] = $op;
    }

    public function queueRemoveAttribute(ElementNode $element, string $name): void
    {
        $op = $this->operations[$element->index()] ?? new Operation(OperationType::Keep, $element);
        $op->attributeChanges[$name] = null;
        $this->operations[$element->index()] = $op;
    }

    public function queueRenameTag(ElementNode $element, string $newTagName): void
    {
        $op = $this->operations[$element->index()] ?? new Operation(OperationType::Keep, $element);
        $op->newTagName = $newTagName;
        $this->operations[$element->index()] = $op;
    }

    /**
     * Get the effective attribute value for an element, considering pending changes.
     *
     * Returns the pending value if queued, otherwise the original element's value.
     * Returns null if the attribute doesn't exist or is marked for removal.
     */
    public function getEffectiveAttribute(ElementNode $element, string $name): ?string
    {
        $op = $this->operations[$element->index()] ?? null;

        if ($op !== null && array_key_exists($name, $op->attributeChanges)) {
            // Return the pending value (null if marked for removal)
            return $op->attributeChanges[$name];
        }

        // There are no pending changes, return the original.
        return $element->getAttribute($name);
    }

    /**
     * Check if an attribute has been explicitly marked for removal.
     *
     * This allows distinguishing between "attribute was removed" and
     * "attribute never existed" when getEffectiveAttribute returns null.
     */
    public function isAttributeMarkedForRemoval(ElementNode $element, string $name): bool
    {
        $op = $this->operations[$element->index()] ?? null;

        if ($op === null) {
            return false;
        }

        return array_key_exists($name, $op->attributeChanges) && $op->attributeChanges[$name] === null;
    }

    public function markSkipChildren(Node $node): void
    {
        $this->skipChildren[$node->index()] = true;
    }

    public function shouldSkipChildren(Node $node): bool
    {
        return $this->skipChildren[$node->index()] ?? false;
    }

    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }

    /**
     * Get the operation for a node, if any.
     */
    public function getOperation(Node $node): ?Operation
    {
        return $this->operations[$node->index()] ?? null;
    }

    /**
     * Get all queued operations.
     *
     * @return array<Operation>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function hasOperations(): bool
    {
        return ! empty($this->operations);
    }

    /**
     * Check if a specific node has an operation.
     */
    public function hasOperation(Node $node): bool
    {
        return isset($this->operations[$node->index()]);
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $input
     * @return array<NodeBuilder>
     */
    private function normalizeSpecs(NodeBuilder|array|string $input): array
    {
        if (is_string($input)) {
            return [new RawBuilder($input)];
        }

        if ($input instanceof NodeBuilder) {
            return [$input];
        }

        return array_map(
            fn ($item) => $item instanceof NodeBuilder ? $item : new RawBuilder((string) $item), // @phpstan-ignore instanceof.alwaysTrue
            $input
        );
    }
}
