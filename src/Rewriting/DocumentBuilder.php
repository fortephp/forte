<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Document\Document;
use Forte\Ast\Node;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use Forte\Rewriting\Builders\DirectiveBuilder;
use Forte\Rewriting\Builders\ElementBuilder;
use Forte\Rewriting\Builders\NodeBuilder;
use LogicException;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class DocumentBuilder
{
    private const NONE = -1;

    /** @var array<int, array<string, mixed>> */
    private array $nodes = [];

    private int $nodeCount = 0;

    /** @var array<int, int> */
    private array $parentStack = [];

    /** @var array<int, string> */
    private array $syntheticContent = [];

    /** @var array<int, array<string, mixed>> */
    private array $syntheticMeta = [];

    /** @var array<int, int> */
    private array $indexMap = [];

    public function __construct(
        private readonly Document $source
    ) {
        $this->nodes[0] = [
            'kind' => NodeKind::Root,
            'parent' => self::NONE,
            'firstChild' => self::NONE,
            'lastChild' => self::NONE,
            'nextSibling' => self::NONE,
            'tokenStart' => 0,
            'tokenCount' => 0,
            'genericOffset' => 0,
            'data' => 0,
        ];
        $this->nodeCount = 1;
        $this->parentStack = [0];
    }

    /**
     * Get the source document.
     */
    public function sourceDocument(): Document
    {
        return $this->source;
    }

    /**
     * Copy a node from the source.
     *
     * @param  Node  $node  The source node to copy
     * @param  bool  $forComposition  If true, marks the node as needing composed rendering
     * @return int The new index
     */
    public function copyNode(Node $node, bool $forComposition = false): int
    {
        $sourceFlat = $this->source->getFlatNode($node->index());
        $newIndex = $this->nodeCount++;

        $this->nodes[$newIndex] = [
            'kind' => $sourceFlat['kind'],
            'parent' => $this->currentParent(),
            'firstChild' => self::NONE,
            'lastChild' => self::NONE,
            'nextSibling' => self::NONE,
            'tokenStart' => $sourceFlat['tokenStart'],
            'tokenCount' => $sourceFlat['tokenCount'],
            'genericOffset' => $sourceFlat['genericOffset'] ?? 0,
            'data' => $sourceFlat['data'] ?? 0,
        ];

        $sourceMeta = $this->source->getSyntheticMeta($node->index());
        if ($sourceMeta !== null) {
            $this->syntheticMeta[$newIndex] = $sourceMeta;
        }

        $sourceContent = $this->source->getSyntheticContent($node->index());
        if ($sourceContent !== null) {
            $this->syntheticContent[$newIndex] = $sourceContent;
        }

        if ($forComposition) {
            $this->syntheticMeta[$newIndex] = array_merge(
                $this->syntheticMeta[$newIndex] ?? [],
                ['needsComposition' => true]
            );
        }

        if (isset($sourceFlat['name'])) {
            $this->nodes[$newIndex]['name'] = $sourceFlat['name'];
        }
        if (isset($sourceFlat['args'])) {
            $this->nodes[$newIndex]['args'] = $sourceFlat['args'];
        }

        $this->linkToParent($newIndex);

        $this->indexMap[$node->index()] = $newIndex;

        return $newIndex;
    }

    /**
     * Copy a node and all its descendants unchanged.
     */
    public function copySubtree(Node $node): int
    {
        $rootNewIndex = $this->copyNode($node);

        $this->pushParent($rootNewIndex);

        foreach ($this->getAllChildren($node) as $child) {
            $this->copySubtree($child);
        }

        $this->popParent();

        return $rootNewIndex;
    }

    /**
     * Add a synthetic node from a spec.
     */
    public function addSyntheticNode(NodeBuilder $spec): int
    {
        $newIndex = $this->nodeCount++;

        $this->nodes[$newIndex] = [
            'kind' => $spec->kind(),
            'parent' => $this->currentParent(),
            'firstChild' => self::NONE,
            'lastChild' => self::NONE,
            'nextSibling' => self::NONE,
            'tokenStart' => self::NONE,
            'tokenCount' => 0,
            'genericOffset' => 0,
            'data' => 0,
        ];

        $content = $spec->toSource();

        if ($spec->needsLeadingSeparator() && $this->previousEndsWithWordChar()) {
            $content = ' '.$content;
        }

        $this->syntheticContent[$newIndex] = $content;

        $meta = $this->extractSyntheticMeta($spec);
        if ($meta !== null) {
            $this->syntheticMeta[$newIndex] = $meta;
        }

        $this->linkToParent($newIndex);

        return $newIndex;
    }

    /**
     * Add a synthetic element that can contain children.
     */
    public function addSyntheticElement(ElementBuilder $spec): int
    {
        $newIndex = $this->nodeCount++;

        $this->nodes[$newIndex] = [
            'kind' => NodeKind::Element,
            'parent' => $this->currentParent(),
            'firstChild' => self::NONE,
            'lastChild' => self::NONE,
            'nextSibling' => self::NONE,
            'tokenStart' => self::NONE,
            'tokenCount' => 0,
            'genericOffset' => 0,
            'data' => 0,
        ];

        $this->syntheticMeta[$newIndex] = [
            'tagName' => $spec->getTagName(),
            'attributes' => $spec->getAttributes(),
            'selfClosing' => $spec->isSelfClosing(),
            'void' => $spec->isVoid(),
            'needsComposition' => true,
        ];

        $this->linkToParent($newIndex);

        return $newIndex;
    }

    /**
     * Retroactively update the synthetic metadata for an already-added element node.
     */
    public function patchElementMeta(int $nodeIndex, ElementBuilder $spec): void
    {
        $existing = $this->syntheticMeta[$nodeIndex] ?? [];

        $this->syntheticMeta[$nodeIndex] = array_merge($existing, [
            'tagName' => $spec->getTagName(),
            'attributes' => $spec->getAttributes(),
            'selfClosing' => $spec->isSelfClosing(),
            'void' => $spec->isVoid(),
            'needsComposition' => true,
        ]);
    }

    public function pushParent(int $index): void
    {
        $this->parentStack[] = $index;
    }

    /**
     * @throws LogicException
     */
    public function popParent(): void
    {
        if (count($this->parentStack) <= 1) {
            throw new LogicException('Cannot pop below root node in parent stack');
        }
        array_pop($this->parentStack);
    }

    /**
     * Get the current parent index.
     */
    public function currentParent(): int
    {
        return end($this->parentStack) ?: 0;
    }

    private function linkToParent(int $nodeIndex): void
    {
        $parentIndex = $this->nodes[$nodeIndex]['parent'];
        if (! is_int($parentIndex)) {
            return;
        }
        $parent = &$this->nodes[$parentIndex];

        if ($parent['firstChild'] === self::NONE) {
            $parent['firstChild'] = $nodeIndex;
        } else {
            $lastChildIdx = $parent['lastChild'];
            if (is_int($lastChildIdx)) {
                $this->nodes[$lastChildIdx]['nextSibling'] = $nodeIndex;
            }
        }

        $parent['lastChild'] = $nodeIndex;
    }

    /**
     * Get the new index for a source node index.
     */
    public function getMappedIndex(int $sourceIndex): ?int
    {
        return $this->indexMap[$sourceIndex] ?? null;
    }

    /**
     * Build and return the new Document.
     */
    public function build(): Document
    {
        return Document::fromParts(
            $this->nodes,
            $this->source->getTokens(),
            $this->source->source(),
            $this->source->getDirectivesRegistry(),
            $this->source->getComponentManager(),
            $this->syntheticContent,
            $this->syntheticMeta
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractSyntheticMeta(NodeBuilder $spec): ?array
    {
        if ($spec instanceof ElementBuilder) {
            return [
                'tagName' => $spec->getTagName(),
                'attributes' => $spec->getAttributes(),
                'selfClosing' => $spec->isSelfClosing(),
                'void' => $spec->isVoid(),
            ];
        }

        if ($spec instanceof DirectiveBuilder) {
            return [
                'name' => $spec->getName(),
                'arguments' => $spec->getArguments(),
            ];
        }

        return null;
    }

    /**
     * @return iterable<Node>
     */
    private function getAllChildren(Node $node): iterable
    {
        $flat = $this->source->getFlatNode($node->index());
        $childIdx = $flat['firstChild'] ?? self::NONE;

        while ($childIdx !== self::NONE) {
            $childFlat = $this->source->getFlatNode($childIdx);
            yield $this->source->getNode($childIdx);
            $childIdx = $childFlat['nextSibling'] ?? self::NONE;
        }
    }

    private function previousEndsWithWordChar(): bool
    {
        $parentIndex = $this->currentParent();
        $parent = $this->nodes[$parentIndex];
        $lastChildIdx = $parent['lastChild'];
        if (! is_int($lastChildIdx)) {
            return false;
        }

        while ($lastChildIdx !== self::NONE && $this->isInternalNode($lastChildIdx)) {
            $lastChildIdx = $this->findPreviousSibling($parentIndex, $lastChildIdx);
        }

        if ($lastChildIdx === self::NONE) {
            return false;
        }

        $content = $this->getNodeTrailingContent($lastChildIdx);
        if ($content === '') {
            return false;
        }

        $lastChar = substr($content, -1);

        return preg_match('/\w/', $lastChar) === 1;
    }

    private function isInternalNode(int $nodeIndex): bool
    {
        $kind = $this->nodes[$nodeIndex]['kind'];

        return $kind === NodeKind::ElementName
            || $kind === NodeKind::ClosingElementName
            || $kind === NodeKind::Attribute
            || $kind === NodeKind::JsxAttribute
            || $kind === NodeKind::AttributeWhitespace
            || $kind === NodeKind::AttributeName
            || $kind === NodeKind::AttributeValue;
    }

    private function findPreviousSibling(int $parentIndex, int $nodeIndex): int
    {
        $parent = $this->nodes[$parentIndex];
        $current = $parent['firstChild'];
        if (! is_int($current)) {
            return self::NONE;
        }
        $previous = self::NONE;

        while ($current !== self::NONE && $current !== $nodeIndex) {
            $previous = $current;
            $next = $this->nodes[$current]['nextSibling'];
            $current = is_int($next) ? $next : self::NONE;
        }

        return $previous;
    }

    private function getNodeTrailingContent(int $nodeIndex): string
    {
        $node = $this->nodes[$nodeIndex];
        $kind = $node['kind'];

        // Elements always end with > (closing tag or self-closing)
        if ($kind === NodeKind::Element) {
            return '>';
        }

        // Synthetic node - use stored content
        if ($node['tokenStart'] === self::NONE) {
            return $this->syntheticContent[$nodeIndex] ?? '';
        }

        // Copied node - find the source and get its content
        $sourceIndex = array_search($nodeIndex, $this->indexMap, true);
        if ($sourceIndex !== false) {
            return $this->source->getNode($sourceIndex)->getDocumentContent();
        }

        return '';
    }
}
