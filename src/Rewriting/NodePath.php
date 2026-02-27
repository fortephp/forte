<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Document;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;
use Forte\Rewriting\Builders\NodeBuilder;
use LogicException;

readonly class NodePath
{
    public function __construct(
        private Node $node,
        private ?NodePath $parentPath,
        private int $indexInParent,
        private Document $document,
        private RewriteContext $context
    ) {}

    /**
     * Get the current node being visited.
     */
    public function node(): Node
    {
        return $this->node;
    }

    /**
     * Get the parent NodePath, or null if this is a root node.
     */
    public function parentPath(): ?NodePath
    {
        return $this->parentPath;
    }

    /**
     * Get the parent Node, or null if this is a root node.
     */
    public function parent(): ?Node
    {
        return $this->parentPath?->node();
    }

    /**
     * Get the document containing this node.
     */
    public function document(): Document
    {
        return $this->document;
    }

    /**
     * Get the index of this node within its parent's children.
     *
     * Returns the zero-based position, or -1 for root-level nodes.
     */
    public function indexInParent(): int
    {
        return $this->indexInParent;
    }

    /**
     * Get the node's index in the document's flat storage.
     */
    public function nodeIndex(): int
    {
        return $this->node->index();
    }

    /**
     * Check if this is a root-level node (no parent).
     */
    public function isRoot(): bool
    {
        return $this->parentPath === null;
    }

    /**
     * Get the depth of this node in the tree.
     */
    public function depth(): int
    {
        $d = 0;
        $p = $this->parentPath;
        while ($p !== null) {
            $d++;
            $p = $p->parentPath;
        }

        return $d;
    }

    /**
     * Get sibling nodes, excluding itself.
     *
     * @return array<Node>
     */
    public function siblings(): array
    {
        if ($this->parentPath === null) {
            return $this->document->getSiblings($this->indexInParent);
        }

        $siblings = $this->parentPath->node()->getChildren();

        return array_values(array_filter(
            $siblings,
            fn (Node $n) => $n->index() !== $this->node->index()
        ));
    }

    /**
     * Get the previous sibling, or null.
     */
    public function previousSibling(): ?Node
    {
        if ($this->indexInParent <= 0) {
            return null;
        }

        if ($this->parentPath === null) {
            return $this->document->getSiblingBefore($this->indexInParent);
        }

        $siblings = $this->parentPath->node()->getChildren();

        return $siblings[$this->indexInParent - 1] ?? null;
    }

    /**
     * Get the next sibling, or null.
     */
    public function nextSibling(): ?Node
    {
        if ($this->parentPath === null) {
            return $this->document->getSiblingAfter($this->indexInParent);
        }

        $siblings = $this->parentPath->node()->getChildren();

        return $siblings[$this->indexInParent + 1] ?? null;
    }

    /**
     * Get all ancestors from root to immediate parent.
     *
     * @return array<Node>
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $p = $this->parentPath;
        while ($p !== null) {
            array_unshift($ancestors, $p->node());
            $p = $p->parentPath;
        }

        return $ancestors;
    }

    /**
     * Find the nearest ancestor matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function findAncestor(callable $predicate): ?Node
    {
        $p = $this->parentPath;
        while ($p !== null) {
            if ($predicate($p->node())) {
                return $p->node();
            }
            $p = $p->parentPath;
        }

        return null;
    }

    /**
     * Replace this node with one or more new nodes.
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $replacement
     */
    public function replaceWith(NodeBuilder|array|string $replacement): self
    {
        $this->context->queueReplace($this->node, $replacement);
        $this->skipChildren();

        return $this;
    }

    /**
     * Remove this node from the tree.
     */
    public function remove(): self
    {
        $this->context->queueRemove($this->node);
        $this->skipChildren();

        return $this;
    }

    /**
     * Insert nodes before this node.
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $nodes
     */
    public function insertBefore(NodeBuilder|array|string $nodes): self
    {
        $this->context->queueInsertBefore($this->node, $nodes);

        return $this;
    }

    /**
     * Insert nodes after this node.
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $nodes
     */
    public function insertAfter(NodeBuilder|array|string $nodes): self
    {
        $this->context->queueInsertAfter($this->node, $nodes);

        return $this;
    }

    /**
     * Wrap this node with a parent.
     */
    public function wrapWith(NodeBuilder $wrapper): self
    {
        $this->context->queueWrap($this->node, $wrapper);

        return $this;
    }

    /**
     * Unwrap this node - replace it with its children.
     *
     * Only valid for nodes that can have children.
     */
    public function unwrap(): self
    {
        $this->context->queueUnwrap($this->node);
        $this->skipChildren();

        return $this;
    }

    /**
     * Replace children of this node.
     *
     * Only valid for nodes that can have children (elements, directive blocks).
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $children
     */
    public function replaceChildren(NodeBuilder|array|string $children): self
    {
        $this->context->queueReplaceChildren($this->node, $children);

        return $this;
    }

    /**
     * Prepend children to this node.
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $children
     */
    public function prependChildren(NodeBuilder|array|string $children): self
    {
        $this->context->queuePrependChildren($this->node, $children);

        return $this;
    }

    /**
     * Append children to this node.
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $children
     */
    public function appendChild(NodeBuilder|array|string $children): self
    {
        $this->context->queueAppendChildren($this->node, $children);

        return $this;
    }

    /**
     * Surround this node with before/after content while replacing it.
     *
     * @param  NodeBuilder|array<NodeBuilder>|string  $before
     * @param  NodeBuilder|array<NodeBuilder>|string  $replacement
     * @param  NodeBuilder|array<NodeBuilder>|string  $after
     */
    public function surroundWith(
        NodeBuilder|array|string $before,
        NodeBuilder|array|string $replacement,
        NodeBuilder|array|string $after
    ): self {
        return $this->insertBefore($before)
            ->replaceWith($replacement)
            ->insertAfter($after);
    }

    /**
     * @param  NodeBuilder|array<NodeBuilder>|string  $before
     * @param  NodeBuilder|array<NodeBuilder>|string  $after
     */
    public function wrapIn(
        NodeBuilder|array|string $before,
        NodeBuilder|array|string $after
    ): self {
        $this->context
            ->queueWrapPair($this->node, $before, $after);

        return $this;
    }

    /**
     * Safely surround a node with start/end content.
     */
    public function safeSurround(NodeBuilder|string $start, NodeBuilder|string|null $end = null): self
    {
        $end ??= $start;

        // Check if this is the sole root element
        if ($this->isSoleRootElement()) {
            // Insert inside the element to preserve the root
            $this->prependChildren($start)
                ->appendChild($end);
        } else {
            // Safe to insert outside
            $this->insertBefore($start)
                ->insertAfter($end);
        }

        return $this;
    }

    /**
     * Check if this node is the sole root-level element.
     */
    public function isSoleRootElement(): bool
    {
        // Must be at root level
        if ($this->parentPath !== null) {
            return false;
        }

        if (! $this->node instanceof ElementNode) {
            return false;
        }

        $elementCount = 0;

        foreach ($this->document->children() as $sibling) {
            if ($sibling instanceof ElementNode) {
                $elementCount++;
                if ($elementCount > 1) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Set an attribute on this element.
     *
     * @throws LogicException
     */
    public function setAttribute(string $name, string $value): self
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('setAttribute() can only be called on elements');
        }

        $this->context->queueSetAttribute($this->node, $name, $value);

        return $this;
    }

    /**
     * Remove an attribute from this element.
     *
     * @throws LogicException
     */
    public function removeAttribute(string $name): self
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('removeAttribute() can only be called on elements');
        }

        $this->context->queueRemoveAttribute($this->node, $name);

        return $this;
    }

    /**
     * Rename the tag of this element.
     *
     * @throws LogicException
     */
    public function renameTag(string $newTagName): self
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('renameTag() can only be called on elements');
        }

        $this->context->queueRenameTag($this->node, $newTagName);

        return $this;
    }

    /**
     * Add a CSS class to this element.
     *
     * @throws LogicException
     */
    public function addClass(string $class): self
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('addClass() can only be called on elements');
        }

        $currentClass = $this->context->getEffectiveAttribute($this->node, 'class');
        $classes = $currentClass !== null ? array_filter(explode(' ', $currentClass)) : [];

        if (! in_array($class, $classes, true)) {
            $classes[] = $class;
        }

        return $this->setAttribute('class', implode(' ', $classes));
    }

    /**
     * Remove a CSS class from this element.
     *
     * @throws LogicException
     */
    public function removeClass(string $class): self
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('removeClass() can only be called on elements');
        }

        $currentClass = $this->context->getEffectiveAttribute($this->node, 'class');
        if ($currentClass === null) {
            return $this;
        }

        $classes = array_filter(explode(' ', $currentClass));
        $classes = array_filter($classes, fn ($c) => $c !== $class);

        if (empty($classes)) {
            return $this->removeAttribute('class');
        }

        return $this->setAttribute('class', implode(' ', $classes));
    }

    /**
     * Toggle a CSS class on this element.
     *
     * @param  bool|null  $force  If provided, add if true, remove if false
     *
     * @throws LogicException
     */
    public function toggleClass(string $class, ?bool $force = null): self
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('toggleClass() can only be called on elements');
        }

        $hasClass = $this->hasClass($class);

        if ($force === true || ($force === null && ! $hasClass)) {
            return $this->addClass($class);
        }

        return $this->removeClass($class);
    }

    /**
     * Check if this element has an attribute.
     *
     * @throws LogicException if node is not an element
     */
    public function hasAttribute(string $name): bool
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('hasAttribute() can only be called on elements');
        }

        return $this->node->hasAttribute($name);
    }

    /**
     * Get an attribute value from this element.
     *
     * @throws LogicException
     */
    public function getAttribute(string $name): ?string
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('getAttribute() can only be called on elements');
        }

        return $this->node->getAttribute($name);
    }

    /**
     * Check if this element has a CSS class.
     *
     * @throws LogicException
     */
    public function hasClass(string $class): bool
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('hasClass() can only be called on elements');
        }

        $currentClass = $this->context->getEffectiveAttribute($this->node, 'class');
        if ($currentClass === null) {
            return false;
        }

        $classes = array_filter(explode(' ', $currentClass));

        return in_array($class, $classes, true);
    }

    /**
     * Get all CSS classes on this element.
     *
     * @return array<string>
     *
     * @throws LogicException
     */
    public function getClasses(): array
    {
        if (! $this->node instanceof ElementNode) {
            throw new LogicException('getClasses() can only be called on elements');
        }

        $currentClass = $this->context->getEffectiveAttribute($this->node, 'class');
        if ($currentClass === null) {
            return [];
        }

        return array_values(array_filter(explode(' ', $currentClass)));
    }

    /**
     * Skip traversing this node's children.
     */
    public function skipChildren(): self
    {
        $this->context->markSkipChildren($this->node);

        return $this;
    }

    /**
     * Stop the entire traversal.
     */
    public function stopTraversal(): self
    {
        $this->context->requestStop();

        return $this;
    }

    public function isElement(): bool
    {
        return $this->node->isElement();
    }

    public function isText(): bool
    {
        return $this->node->isText();
    }

    public function isDirective(): bool
    {
        return $this->node->isDirective();
    }

    public function isDirectiveBlock(): bool
    {
        return $this->node->isDirectiveBlock();
    }

    public function isEcho(): bool
    {
        return $this->node->isEcho();
    }

    public function isComment(): bool
    {
        return $this->node->isComment();
    }

    public function isBladeComment(): bool
    {
        return $this->node->isBladeComment();
    }

    public function isPhpBlock(): bool
    {
        return $this->node->isPhpBlock();
    }

    public function isPhpTag(): bool
    {
        return $this->node->isPhpTag();
    }

    public function asElement(): ?ElementNode
    {
        return $this->node->asElement();
    }

    public function asText(): ?TextNode
    {
        return $this->node->asText();
    }

    public function asDirective(): ?DirectiveNode
    {
        return $this->node->asDirective();
    }

    public function asDirectiveBlock(): ?DirectiveBlockNode
    {
        return $this->node->asDirectiveBlock();
    }

    public function asEcho(): ?EchoNode
    {
        return $this->node->asEcho();
    }

    public function asComment(): ?CommentNode
    {
        return $this->node->asComment();
    }

    public function asPhpBlock(): ?PhpBlockNode
    {
        return $this->node->asPhpBlock();
    }

    public function asPhpTag(): ?PhpTagNode
    {
        return $this->node->asPhpTag();
    }

    /**
     * Check if this is an element with a specific tag name.
     */
    public function isTag(string $tagName): bool
    {
        if (! $this->node instanceof ElementNode) {
            return false;
        }

        return strcasecmp($this->node->tagNameText(), $tagName) === 0;
    }

    /**
     * Check if this is a directive with a specific name.
     */
    public function isDirectiveNamed(string $name): bool
    {
        if ($this->node instanceof DirectiveNode) {
            return $this->node->nameText() === $name;
        }

        if ($this->node instanceof DirectiveBlockNode) {
            return $this->node->nameText() === $name;
        }

        return false;
    }
}
