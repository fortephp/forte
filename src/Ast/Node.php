<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\CastsTypes;
use Forte\Ast\Concerns\ManagesAncestorNavigation;
use Forte\Ast\Concerns\ManagesChildrenLookups;
use Forte\Ast\Concerns\ManagesLines;
use Forte\Ast\Concerns\ManagesMetadata;
use Forte\Ast\Concerns\ManagesSiblingIteration;
use Forte\Ast\Concerns\ManagesSiblingPredicates;
use Forte\Ast\Concerns\ManagesTreeContainment;
use Forte\Ast\Document\Document;
use Forte\Ast\Document\NodeCollection;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use JsonSerializable;
use Stringable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
abstract class Node implements JsonSerializable, Stringable
{
    use CastsTypes,
        ManagesAncestorNavigation,
        ManagesChildrenLookups,
        ManagesLines,
        ManagesMetadata,
        ManagesSiblingIteration,
        ManagesSiblingPredicates,
        ManagesTreeContainment;

    private const NONE = -1;

    private const INTERNAL_KINDS = [
        NodeKind::ElementName,
        NodeKind::ClosingElementName,
        NodeKind::Attribute,
        NodeKind::JsxAttribute,
        NodeKind::AttributeName,
        NodeKind::AttributeValue,
        NodeKind::AttributeWhitespace,
    ];

    public function __construct(protected Document $document, protected int $index) {}

    /**
     * Get the node's index in the document.
     */
    public function index(): int
    {
        return $this->index;
    }

    /**
     * Get the document this node belongs to.
     */
    public function getDocument(): Document
    {
        return $this->document;
    }

    public function sharesDocument(Document $document): bool
    {
        return $this->document === $document;
    }

    /**
     * Get the node kind.
     */
    public function kind(): int
    {
        return $this->flat()['kind'];
    }

    /**
     * Check if this node is of a specific kind.
     *
     * Accepts either an integer kind ID or a string key (e.g., "hashtag::Hashtag" or "core::Element").
     *
     * @param  string|int  $kind  Kind ID or string key
     */
    public function isKind(string|int $kind): bool
    {
        return NodeKind::is($this->flat(), $kind);
    }

    /**
     * Get the start offset in the source.
     *
     * Returns -1 for synthetic nodes.
     */
    public function startOffset(): int
    {
        $flat = $this->flat();
        $tokenStart = $flat['tokenStart'];

        if ($tokenStart < 0) {
            return -1;
        }

        return $this->document->getToken($tokenStart)['start'];
    }

    /**
     * Get the end offset in the source.
     *
     * Returns -1 for synthetic nodes or nodes with no tokens.
     */
    public function endOffset(): int
    {
        $flat = $this->flat();
        $tokenStart = $flat['tokenStart'];
        $tokenCount = $flat['tokenCount'];

        // Synthetic nodes have tokenStart = -1
        if ($tokenStart < 0) {
            return -1;
        }

        if ($tokenCount <= 0) {
            return -1;
        }

        $tokenEnd = $tokenStart + $tokenCount - 1;

        return $this->document->getToken($tokenEnd)['end'];
    }

    /**
     * Check if this node is synthetic (created by a rewriter).
     */
    public function isSynthetic(): bool
    {
        return $this->flat()['tokenStart'] < 0;
    }

    /**
     * Get the 1-indexed start line number.
     */
    public function startLine(): int
    {
        return $this->document->getLineForOffset($this->startOffset());
    }

    /**
     * Get the 1-indexed end line number.
     */
    public function endLine(): int
    {
        $endOffset = $this->endOffset();

        return $this->document->getLineForOffset($endOffset > 0 ? $endOffset - 1 : 0);
    }

    /**
     * Get the 1-indexed start column number.
     */
    public function startColumn(): int
    {
        return $this->document->getColumnForOffset($this->startOffset());
    }

    /**
     * Get the 1-indexed end column number.
     */
    public function endColumn(): int
    {
        $endOffset = $this->endOffset();

        return $this->document->getColumnForOffset($endOffset > 0 ? $endOffset - 1 : 0);
    }

    /**
     * Get the document content for this node.
     */
    public function getDocumentContent(): string
    {
        $syntheticContent = $this->document->getSyntheticContent($this->index);

        if ($syntheticContent !== null) {
            return $syntheticContent;
        }

        return $this->document->getSourceSlice($this->startOffset(), $this->endOffset());
    }

    /**
     * Render this node back to string.
     */
    public function render(): string
    {
        // Check if a node is marked as needing composition.
        $meta = $this->document->getSyntheticMeta($this->index);
        if ($meta !== null && ($meta['needsComposition'] ?? false)) {
            return $this->renderComposed();
        }

        // Synthetic node - return stored content.
        if ($this->isSynthetic()) {
            return $this->document->getSyntheticContent($this->index) ?? '';
        }

        // Check if any descendant is synthetic - if so, we need to compose the content.
        if ($this->hasSyntheticDescendant()) {
            return $this->renderComposed();
        }

        // No synthetic descendants - we have hit the happy path!
        return $this->getDocumentContent();
    }

    /**
     * Check if any descendant node is synthetic.
     */
    protected function hasSyntheticDescendant(): bool
    {
        foreach ($this->allFlatChildren() as $childIdx) {
            if ($this->document->isSyntheticNode($childIdx)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all flat child indices recursively.
     *
     * Includes internal nodes.
     *
     * @return iterable<int>
     */
    private function allFlatChildren(): iterable
    {
        $flat = $this->flat();
        $childIdx = $flat['firstChild'] ?? self::NONE;

        while ($childIdx !== self::NONE) {
            yield $childIdx;

            $childNode = $this->document->getNode($childIdx);
            yield from $childNode->allFlatChildren();

            $childFlat = $this->document->getFlatNode($childIdx);
            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }
    }

    protected function renderComposed(): string
    {
        $parts = [];
        $flat = $this->flat();
        $childIdx = $flat['firstChild'] ?? self::NONE;

        while ($childIdx !== self::NONE) {
            $childNode = $this->document->getNode($childIdx);
            $parts[] = $childNode->render();

            $childFlat = $this->document->getFlatNode($childIdx);
            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }

        return implode('', $parts);
    }

    /**
     * Get child nodes.
     *
     * @return iterable<Node>
     */
    public function children(): iterable
    {
        $flat = $this->flat();
        $childIdx = $flat['firstChild'] ?? self::NONE;

        while ($childIdx !== self::NONE) {
            $childFlat = $this->document->getFlatNode($childIdx);
            $kind = $childFlat['kind'];

            if (! in_array($kind, self::INTERNAL_KINDS, true)) {
                yield $this->document->getNode($childIdx);
            }

            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }
    }

    /**
     * Get children.
     *
     * @return array<Node>
     */
    public function getChildren(): array
    {
        return iterator_to_array($this->children());
    }

    /**
     * Render only the children content, excluding the node's own tags.
     */
    public function renderChildrenOnly(): string
    {
        $parts = [];
        foreach ($this->children() as $child) {
            $parts[] = $child->render();
        }

        return implode('', $parts);
    }

    /**
     * @return NodeCollection<int, Node>
     */
    public function nodes(): NodeCollection
    {
        return new NodeCollection($this->getChildren());
    }

    /**
     * Check if this node has children.
     */
    public function hasChildren(): bool
    {
        $flat = $this->flat();

        return ($flat['firstChild'] ?? self::NONE) !== self::NONE;
    }

    /**
     * Check if this is a root-level node.
     */
    public function isRoot(): bool
    {
        $flat = $this->flat();
        $parent = $flat['parent'] ?? self::NONE;

        return $parent === 0;
    }

    /**
     * Get the first child, or null if none.
     */
    public function firstChild(): ?Node
    {
        foreach ($this->children() as $child) {
            return $child;
        }

        return null;
    }

    /**
     * Get the last child, or null if none.
     */
    public function lastChild(): ?Node
    {
        $last = null;
        foreach ($this->children() as $child) {
            $last = $child;
        }

        return $last;
    }

    /**
     * Get all descendents.
     *
     * @return iterable<Node>
     */
    public function descendants(): iterable
    {
        foreach ($this->children() as $child) {
            yield $child;
            yield from $child->descendants();
        }
    }

    /**
     * Get all descendents as an array.
     *
     * @return array<Node>
     */
    public function getDescendants(): array
    {
        return iterator_to_array($this->descendants());
    }

    /**
     * Walk this node and all descendants.
     *
     * @param  callable(Node): void  $callback
     */
    public function walk(callable $callback): void
    {
        $callback($this);
        foreach ($this->children() as $child) {
            $child->walk($callback);
        }
    }

    /**
     * @internal
     *
     * @return FlatNode
     */
    protected function flat(): array
    {
        return $this->document->getFlatNode($this->index);
    }

    /**
     * Get the flat node data for this node.
     *
     * @return FlatNode
     */
    public function getFlatNode(): array
    {
        return $this->flat();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Get the parent node, or null if this is a root node.
     */
    public function getParent(): ?Node
    {
        $flat = $this->flat();
        $parentIdx = $flat['parent'] ?? self::NONE;

        if ($parentIdx === self::NONE || $parentIdx === 0) {
            return null;
        }

        return $this->document->getNode($parentIdx);
    }

    /**
     * Get the next sibling node, or null if this is the last child.
     *
     * Skips internal structural nodes (attributes, element names, etc.).
     */
    public function nextSibling(): ?Node
    {
        $flat = $this->flat();
        $nextIdx = $flat['nextSibling'] ?? self::NONE;

        while ($nextIdx !== self::NONE) {
            $nextFlat = $this->document->getFlatNode($nextIdx);
            $kind = $nextFlat['kind'];

            // Skip internal structural nodes
            if (! in_array($kind, self::INTERNAL_KINDS, true)) {
                return $this->document->getNode($nextIdx);
            }

            $nextIdx = $nextFlat['nextSibling'] ?? self::NONE;
        }

        return null;
    }

    /**
     * Get the previous sibling node, or null if this is the first child.
     *
     * Skips internal structural nodes (attributes, element names, etc.).
     */
    public function previousSibling(): ?Node
    {
        $flat = $this->flat();
        $parentIdx = $flat['parent'] ?? self::NONE;

        if ($parentIdx === self::NONE) {
            return null;
        }

        // Get parent's children and find the one before this node
        $parentFlat = $this->document->getFlatNode($parentIdx);
        $childIdx = $parentFlat['firstChild'] ?? self::NONE;
        $previousIdx = self::NONE;

        while ($childIdx !== self::NONE && $childIdx !== $this->index) {
            $childFlat = $this->document->getFlatNode($childIdx);
            $kind = $childFlat['kind'];

            // Track non-internal nodes as potential previous siblings
            if (! in_array($kind, self::INTERNAL_KINDS, true)) {
                $previousIdx = $childIdx;
            }

            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }

        if ($previousIdx === self::NONE) {
            return null;
        }

        return $this->document->getNode($previousIdx);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $startOffset = $this->startOffset();
        $endOffset = $this->endOffset();

        return [
            'kind' => $this->kind(),
            'kind_name' => NodeKind::name($this->kind()),
            'start' => [
                'offset' => $startOffset,
                'line' => $startOffset >= 0 ? $this->startLine() : -1,
                'column' => $startOffset >= 0 ? $this->startColumn() : -1,
            ],
            'end' => [
                'offset' => $endOffset,
                'line' => $endOffset >= 0 ? $this->endLine() : -1,
                'column' => $endOffset >= 0 ? $this->endColumn() : -1,
            ],
            'is_synthetic' => $this->isSynthetic(),
            'content' => $this->getDocumentContent(),
            'children' => array_map(
                fn (Node $child) => $child->jsonSerialize(),
                $this->getChildren()
            ),
        ];
    }
}
