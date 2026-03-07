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
use Forte\Ast\Elements\ElementNode;
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

    private const INTERNAL_KIND_SET = [
        NodeKind::ElementName => true,
        NodeKind::ClosingElementName => true,
        NodeKind::Attribute => true,
        NodeKind::JsxAttribute => true,
        NodeKind::AttributeName => true,
        NodeKind::AttributeValue => true,
        NodeKind::AttributeWhitespace => true,
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

        // Synthetic descendants require composed rendering.
        if ($this->hasSyntheticDescendant()) {
            return $this->renderComposed();
        }

        // Recovered trees can attach child tokens beyond a node's token span.
        // Expand the raw slice to preserve exact source fidelity in those cases.
        if ($this->hasOutOfRangeChildTokens()) {
            return $this->renderExpandedTokenRange();
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
     * Detect malformed/recovered trees where direct children are outside this node's token range.
     *
     * In those cases, using raw source slices drops child content; composed rendering preserves fidelity.
     */
    protected function hasOutOfRangeChildTokens(): bool
    {
        $flat = $this->flat();
        $tokenStart = $flat['tokenStart'];
        $tokenCount = $flat['tokenCount'];

        if ($tokenStart < 0 || $tokenCount <= 0) {
            return false;
        }

        $nodeEnd = $tokenStart + $tokenCount - 1;

        foreach ($this->allFlatChildren() as $childIdx) {
            $childFlat = $this->document->getFlatNode($childIdx);
            $childTokenStart = $childFlat['tokenStart'];
            $childTokenCount = $childFlat['tokenCount'];

            if ($childTokenStart >= 0 && $childTokenCount > 0) {
                $childEnd = $childTokenStart + $childTokenCount - 1;
                $isNonOverlapping = $childTokenStart > $nodeEnd || $childEnd < $tokenStart;

                if ($isNonOverlapping) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function renderExpandedTokenRange(): string
    {
        $flat = $this->flat();
        $tokenStart = $flat['tokenStart'];
        $tokenCount = $flat['tokenCount'];

        if ($tokenStart < 0 || $tokenCount <= 0) {
            return $this->getDocumentContent();
        }

        $startOffset = $this->document->getToken($tokenStart)['start'];
        $endOffset = $this->document->getToken($tokenStart + $tokenCount - 1)['end'];

        foreach ($this->allFlatChildren() as $childIdx) {
            $childFlat = $this->document->getFlatNode($childIdx);
            $childTokenStart = $childFlat['tokenStart'];
            $childTokenCount = $childFlat['tokenCount'];

            if ($childTokenStart >= 0 && $childTokenCount > 0) {
                $childTokenEnd = $childTokenStart + $childTokenCount - 1;
                $isNonOverlapping = $childTokenStart > ($tokenStart + $tokenCount - 1) || $childTokenEnd < $tokenStart;

                if (! $isNonOverlapping) {
                    continue;
                }

                $childStart = $this->document->getToken($childTokenStart)['start'];
                $childEnd = $this->document->getToken($childTokenEnd)['end'];

                if ($childStart < $startOffset) {
                    $startOffset = $childStart;
                }

                if ($childEnd > $endOffset) {
                    $endOffset = $childEnd;
                }
            }
        }

        return $this->document->getSourceSlice($startOffset, $endOffset);
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
        $siblingStack = [];

        while ($childIdx !== self::NONE) {
            yield $childIdx;

            $childFlat = $this->document->getFlatNode($childIdx);
            $nextSibling = $childFlat['nextSibling'] ?? self::NONE;
            if ($nextSibling !== self::NONE) {
                $siblingStack[] = $nextSibling;
            }

            $firstChild = $childFlat['firstChild'] ?? self::NONE;
            if ($firstChild !== self::NONE) {
                $childIdx = $firstChild;

                continue;
            }

            $childIdx = empty($siblingStack) ? self::NONE : array_pop($siblingStack);
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

            if (! self::isInternalKind($kind)) {
                yield $this->document->getNode($childIdx);
            }

            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }
    }

    /**
     * Get children.
     *
     * @return array<int, Node>
     */
    public function getChildren(): array
    {
        $children = [];

        foreach ($this->children() as $child) {
            $children[] = $child;
        }

        return $children;
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
            if ($child instanceof self) {
                return $child;
            }
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
            if ($child instanceof self) {
                $last = $child;
            }
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
            if ($child instanceof self) {
                yield $child;
                yield from $child->descendants();
            }
        }
    }

    /**
     * Get all descendents as an array.
     *
     * @return array<int, Node>
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->descendants() as $descendant) {
            $descendants[] = $descendant;
        }

        return $descendants;
    }

    /**
     * Get this node tree filtered to a specific node type.
     *
     * By default this uses normal children traversal only. Pass
     * `TraversalOptions::deep()` or `true` to include element/component internals.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return NodeCollection<int, T>
     */
    public function allOfType(string $class, TraversalOptions|bool $options = false): NodeCollection
    {
        $options = TraversalOptions::from($options);
        $results = [];

        foreach ($this->traverseNodes($options) as $current) {
            if ($current instanceof $class) {
                $results[] = $current;
            }
        }

        /** @var NodeCollection<int, T> */
        return NodeCollection::make($results);
    }

    /**
     * Get all echo nodes in this node tree.
     *
     * @return NodeCollection<int, EchoNode>
     */
    public function allEchoes(TraversalOptions|bool $options = false): NodeCollection
    {
        /** @var NodeCollection<int, EchoNode> */
        return $this->allOfType(EchoNode::class, $options);
    }

    /**
     * Walk this node and all descendants.
     *
     * @param  callable(Node): void  $callback
     */
    public function walk(callable $callback, TraversalOptions|bool $options = false): void
    {
        $options = TraversalOptions::from($options);

        foreach ($this->traverseNodes($options) as $node) {
            $callback($node);
        }
    }

    /**
     * Traverse this node tree in pre-order.
     *
     * @return iterable<Node>
     */
    private function traverseNodes(TraversalOptions $options): iterable
    {
        /** @var array<int, array{0: Node, 1: int}> $stack */
        $stack = [[$this, 0]];
        /** @var array<int, true> $seen */
        $seen = [];

        while ($stack !== []) {
            $entry = array_pop($stack);
            if ($entry === null) {
                continue;
            }

            [$current, $depth] = $entry;
            $index = $current->index();

            if (isset($seen[$index])) {
                continue;
            }

            if ($options->maxDepth !== null && $depth > $options->maxDepth) {
                continue;
            }

            if (! $options->includeSynthetic && $current->isSynthetic()) {
                continue;
            }

            $seen[$index] = true;
            yield $current;

            if ($options->maxDepth !== null && $depth === $options->maxDepth) {
                continue;
            }

            /** @var array<int, Node> $children */
            $children = [];
            foreach ($current->children() as $child) {
                if ($child instanceof self) {
                    $children[] = $child;
                }
            }

            if ($options->includeInternal && $current instanceof ElementNode) {
                foreach ($current->internalNodes() as $internal) {
                    $children[] = $internal;
                }
            }

            for ($i = count($children) - 1; $i >= 0; $i--) {
                $stack[] = [$children[$i], $depth + 1];
            }
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
            if (! self::isInternalKind($kind)) {
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
            if (! self::isInternalKind($kind)) {
                $previousIdx = $childIdx;
            }

            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }

        if ($previousIdx === self::NONE) {
            return null;
        }

        return $this->document->getNode($previousIdx);
    }

    private static function isInternalKind(int $kind): bool
    {
        return isset(self::INTERNAL_KIND_SET[$kind]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $startOffset = $this->startOffset();
        $endOffset = $this->endOffset();

        $children = [];
        foreach ($this->getChildren() as $child) {
            $children[] = $child->jsonSerialize();
        }

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
            'children' => $children,
        ];
    }
}
