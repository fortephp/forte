<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\Builders\ElementBuilder;
use Forte\Rewriting\Builders\NodeBuilder;
use Forte\Rewriting\Builders\WrapperBuilder;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class Rewriter implements AstRewriter
{
    /** @var array<RewriteVisitor> */
    private array $visitors = [];

    /**
     * Add a visitor to the rewriter.
     */
    public function addVisitor(RewriteVisitor $visitor): self
    {
        $this->visitors[] = $visitor;

        return $this;
    }

    /**
     * Rewrite a document, returning a new document with changes applied.
     */
    public function rewrite(Document $doc): Document
    {
        if ($this->visitors === []) {
            return $doc;
        }

        $context = new RewriteContext;
        $builder = new DocumentBuilder($doc);

        $childIndex = 0;
        foreach ($doc->children() as $child) {
            if ($context->isStopRequested()) {
                break;
            }

            $this->processNode($child, null, $childIndex++, $context, $builder);
        }

        return $builder->build();
    }

    private function processNode(
        Node $node,
        ?NodePath $parentPath,
        int $indexInParent,
        RewriteContext $context,
        DocumentBuilder $builder
    ): void {
        if ($context->isStopRequested()) {
            return;
        }

        $path = new NodePath(
            $node,
            $parentPath,
            $indexInParent,
            $builder->sourceDocument(),
            $context
        );

        if ($this->runVisitorsEnter($path, $context)) {
            return;
        }

        $operation = $context->getOperation($node);

        $this->applyOperation($node, $operation, $path, $context, $builder);

        if (! $this->isKeepOperation($operation)) {
            return;
        }

        if ($this->runVisitorsLeave($path, $context)) {
            return;
        }

        $this->processLeaveInsertAfter($node, $operation, $context, $builder);
    }

    private function applyOperation(
        Node $node,
        ?Operation $operation,
        NodePath $path,
        RewriteContext $context,
        DocumentBuilder $builder
    ): void {
        if ($operation !== null) {
            $this->emitInsertions($operation->insertBefore, $builder);
        }

        /** @phpstan-ignore nullsafe.neverNull */
        $type = $operation?->type ?? OperationType::Keep;

        switch ($type) {
            case OperationType::Remove:
                break;

            case OperationType::Replace:
                $this->emitInsertions($operation->replacement ?? [], $builder);
                break;

            case OperationType::Wrap:
                assert($operation !== null);
                $this->applyWrap($node, $operation, $path, $context, $builder);
                break;

            case OperationType::Unwrap:
                $this->processChildrenForUnwrap($node, $path, $context, $builder);
                break;

            case OperationType::ReplaceChildren:
                assert($operation !== null);
                $this->copyNodeReplaceChildren($node, $operation, $builder);
                break;

            case OperationType::Keep:
            default:
                $this->copyNodeWithChildren($node, $path, $context, $builder, $operation);
                break;
        }

        if ($operation !== null) {
            $this->emitInsertions($operation->insertAfter, $builder);
        }
    }

    private function copyNodeWithChildren(
        Node $node,
        NodePath $path,
        RewriteContext $context,
        DocumentBuilder $builder,
        ?Operation $operation = null
    ): void {
        if ($this->shouldModifyElement($node, $operation) && $node instanceof ElementNode) {
            assert($operation !== null);
            $this->copyElementWithModifications($node, $path, $context, $builder, $operation);

            return;
        }

        if ($context->shouldSkipChildren($node) || ! $node->hasChildren()) {
            $builder->copySubtree($node);

            return;
        }

        $newIndex = $builder->copyNode($node, forComposition: true);
        $builder->pushParent($newIndex);

        if ($operation !== null) {
            $this->emitInsertions($operation->prependChildren, $builder);
        }

        $contentChildren = $this->indexContentChildren($node);
        $hasAppendChildren = $operation !== null && $operation->appendChildren !== [];
        $appendInserted = false;
        $childIndex = 0;

        foreach ($this->getAllFlatChildren($node, $builder->sourceDocument()) as $child) {
            if ($context->isStopRequested()) {
                break;
            }

            if (isset($contentChildren[$child->index()])) {
                $this->processNode($child, $path, $childIndex++, $context, $builder);

                continue;
            }

            if ($hasAppendChildren && ! $appendInserted) {
                $flat = $builder->sourceDocument()->getFlatNode($child->index());
                if ($flat['kind'] === NodeKind::ClosingElementName) {
                    $this->emitInsertions($operation->appendChildren, $builder);
                    $appendInserted = true;
                }
            }

            $builder->copySubtree($child);
        }

        if ($hasAppendChildren && ! $appendInserted) {
            $this->emitInsertions($operation->appendChildren, $builder);
        }

        $builder->popParent();
    }

    private function copyElementWithModifications(
        ElementNode $element,
        NodePath $path,
        RewriteContext $context,
        DocumentBuilder $builder,
        Operation $operation
    ): void {
        $spec = $this->buildElementSpec($element, $operation, $builder->sourceDocument());
        $meta = $builder->sourceDocument()->getSyntheticMeta($element->index());
        $selfClosing = ($meta['selfClosing'] ?? false) || $element->isSelfClosing();
        $void = ($meta['void'] ?? false) || $element->isVoid();

        if ($selfClosing) {
            $spec->selfClosing();
        } elseif ($void) {
            $spec->void();
        }

        if (! $element->hasChildren() || $selfClosing || $void) {
            $builder->addSyntheticNode($spec);

            return;
        }

        $newIndex = $builder->addSyntheticElement($spec);
        $builder->pushParent($newIndex);

        $this->emitInsertions($operation->prependChildren, $builder);

        $childIndex = 0;
        foreach ($element->children() as $child) {
            if ($context->isStopRequested()) {
                break;
            }

            $this->processNode($child, $path, $childIndex++, $context, $builder);
        }

        $this->emitInsertions($operation->appendChildren, $builder);
        $builder->popParent();
    }

    private function copyNodeReplaceChildren(Node $node, Operation $operation, DocumentBuilder $builder): void
    {
        $newIndex = $builder->copyNode($node, forComposition: true);
        $builder->pushParent($newIndex);

        $contentChildren = $this->indexContentChildren($node);
        $inserted = false;

        foreach ($this->getAllFlatChildren($node, $builder->sourceDocument()) as $child) {
            if (! isset($contentChildren[$child->index()])) {
                $builder->copySubtree($child);

                continue;
            }

            if (! $inserted) {
                $this->emitInsertions($operation->replacement ?? [], $builder);
                $inserted = true;
            }
        }

        if (! $inserted) {
            $this->emitInsertions($operation->replacement ?? [], $builder);
        }

        $builder->popParent();
    }

    private function applyWrap(
        Node $node,
        Operation $operation,
        NodePath $path,
        RewriteContext $context,
        DocumentBuilder $builder
    ): void {
        if ($operation->wrapper instanceof WrapperBuilder) {
            $builder->addSyntheticNode(Builder::raw($operation->wrapper->getOpeningSource()));
            $this->copyNodeWithChildren($node, $path, $context, $builder);
            $closing = $operation->wrapper->getClosingSource();

            if ($closing !== '') {
                $builder->addSyntheticNode(Builder::raw($closing));
            }

            return;
        }

        if ($operation->wrapper === null) {
            return;
        }

        $wrapperIndex = $builder->addSyntheticNode($operation->wrapper);
        $builder->pushParent($wrapperIndex);
        $this->copyNodeWithChildren($node, $path, $context, $builder);
        $builder->popParent();
    }

    private function processChildrenForUnwrap(
        Node $node,
        NodePath $path,
        RewriteContext $context,
        DocumentBuilder $builder
    ): void {
        $childIndex = 0;
        foreach ($node->children() as $child) {
            if ($context->isStopRequested()) {
                break;
            }

            $this->processNode($child, $path, $childIndex++, $context, $builder);
        }
    }

    /**
     * Emit synthetic nodes from a list.
     *
     * @param  array<NodeBuilder>  $specs
     */
    private function emitInsertions(array $specs, DocumentBuilder $builder): void
    {
        foreach ($specs as $spec) {
            $builder->addSyntheticNode($spec);
        }
    }

    private function runVisitorsEnter(NodePath $path, RewriteContext $context): bool
    {
        foreach ($this->visitors as $visitor) {
            $visitor->enter($path);

            if ($context->isStopRequested()) {
                return true;
            }
        }

        return false;
    }

    private function runVisitorsLeave(NodePath $path, RewriteContext $context): bool
    {
        foreach ($this->visitors as $visitor) {
            $visitor->leave($path);

            if ($context->isStopRequested()) {
                return true;
            }
        }

        return false;
    }

    private function processLeaveInsertAfter(
        Node $node,
        ?Operation $initialOp,
        RewriteContext $context,
        DocumentBuilder $builder
    ): void {
        $leaveOp = $context->getOperation($node);

        if ($leaveOp === null || $leaveOp->insertAfter === []) {
            return;
        }

        $already = $initialOp !== null ? count($initialOp->insertAfter) : 0;
        if ($already >= count($leaveOp->insertAfter)) {
            return;
        }

        $newInserts = array_slice($leaveOp->insertAfter, $already);
        $this->emitInsertions($newInserts, $builder);
    }

    private function isKeepOperation(?Operation $operation): bool
    {
        return $operation === null || $operation->type === OperationType::Keep;
    }

    /**
     * @return iterable<Node>
     */
    private function getAllFlatChildren(Node $node, Document $doc): iterable
    {
        $flat = $doc->getFlatNode($node->index());
        $childIdx = $flat['firstChild'] ?? -1;

        while ($childIdx !== -1) {
            $childFlat = $doc->getFlatNode($childIdx);
            yield $doc->getNode($childIdx);
            $childIdx = $childFlat['nextSibling'] ?? -1;
        }
    }

    private function shouldModifyElement(Node $node, ?Operation $operation): bool
    {
        return $operation !== null
            && $node instanceof ElementNode
            && (
                $operation->newTagName !== null
                || ! empty($operation->attributeChanges)
            );
    }

    private function buildElementSpec(
        ElementNode $element,
        Operation $operation,
        Document $sourceDoc
    ): ElementBuilder {
        $tagName = $operation->newTagName ?? $element->tagNameText();
        $spec = Builder::element($tagName);

        $meta = $sourceDoc->getSyntheticMeta($element->index());
        $isSynthetic = $meta !== null && isset($meta['attributes']) && is_array($meta['attributes']);

        if ($isSynthetic) {
            /** @var list<array{0: string, 1: string|true}> $existing */
            $existing = $meta['attributes'];
            $handledChanges = [];
            $seenNames = [];

            foreach ($existing as [$name, $value]) {
                if (array_key_exists($name, $operation->attributeChanges)) {
                    $change = $operation->attributeChanges[$name];

                    if ($change === null) {
                        continue;
                    }

                    if (isset($handledChanges[$name])) {
                        continue;
                    }

                    $handledChanges[$name] = true;
                    $value = $change;
                }

                $seenNames[$name] = true;

                $spec->appendAttr($name, $value);
            }

            foreach ($operation->attributeChanges as $name => $value) {
                if ($value !== null && ! isset($seenNames[$name])) {
                    $spec->appendAttr($name, $value);
                }
            }
        } else {
            $handledChanges = [];
            $seenNames = [];

            foreach ($element->attributes() as $attr) {
                $strippedName = $attr->nameText();

                if ($strippedName === '') {
                    continue;
                }

                $rawName = $attr->rawName();
                $value = $attr->valueText() ?? true;

                if (array_key_exists($strippedName, $operation->attributeChanges)) {
                    $change = $operation->attributeChanges[$strippedName];

                    if ($change === null) {
                        continue;
                    }

                    if (isset($handledChanges[$strippedName])) {
                        continue;
                    }

                    $handledChanges[$strippedName] = true;
                    $value = $change;
                    $outputName = $strippedName;
                } else {
                    $outputName = $rawName;
                }

                $seenNames[$strippedName] = true;

                $spec->appendAttr($outputName, $value);
            }

            foreach ($operation->attributeChanges as $name => $value) {
                if ($value !== null && ! isset($seenNames[$name])) {
                    $spec->appendAttr($name, $value);
                }
            }
        }

        return $spec;
    }

    /**
     * @return array<int, true>
     */
    private function indexContentChildren(Node $node): array
    {
        $indexed = [];

        foreach ($node->children() as $child) {
            $indexed[$child->index()] = true;
        }

        return $indexed;
    }
}
