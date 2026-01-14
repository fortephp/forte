<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Ast\TextNode;

trait ManagesChildrenLookups
{
    /**
     * Get the child at a specific index.
     *
     * @param  int  $index  Zero-based index among non-internal children
     */
    public function childAt(int $index): ?Node
    {
        if ($index < 0) {
            return null;
        }

        $i = 0;
        foreach ($this->children() as $child) {
            if ($i === $index) {
                return $child;
            }
            $i++;
        }

        return null;
    }

    /**
     * Find the first child matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function firstChildWhere(callable $predicate): ?Node
    {
        foreach ($this->children() as $child) {
            if ($predicate($child)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Find the last child matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function lastChildWhere(callable $predicate): ?Node
    {
        $last = null;
        foreach ($this->children() as $child) {
            if ($predicate($child)) {
                $last = $child;
            }
        }

        return $last;
    }

    /**
     * Get all children matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     * @return iterable<Node>
     */
    public function childrenWhere(callable $predicate): iterable
    {
        foreach ($this->children() as $child) {
            if ($predicate($child)) {
                yield $child;
            }
        }
    }

    /**
     * Get the first child of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function firstChildOfType(string $class): ?Node
    {
        /** @var T|null */
        return $this->firstChildWhere(fn (Node $node) => $node instanceof $class);
    }

    /**
     * Get the last child of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function lastChildOfType(string $class): ?Node
    {
        /** @var T|null */
        return $this->lastChildWhere(fn (Node $node) => $node instanceof $class);
    }

    /**
     * Get all children of a specific type.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return iterable<T>
     */
    public function childrenOfType(string $class): iterable
    {
        foreach ($this->children() as $child) {
            if ($child instanceof $class) {
                yield $child;
            }
        }
    }

    /**
     * Get all children matching a predicate as an array.
     *
     * @param  callable(Node): bool  $predicate
     * @return array<Node>
     */
    public function getChildrenWhere(callable $predicate): array
    {
        return iterator_to_array($this->childrenWhere($predicate));
    }

    /**
     * Get all children of a specific type as an array.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @return array<T>
     */
    public function getChildrenOfType(string $class): array
    {
        return iterator_to_array($this->childrenOfType($class));
    }

    /**
     * Get the first DirectiveNode child.
     */
    public function firstDirective(): ?DirectiveNode
    {
        return $this->firstChildOfType(DirectiveNode::class);
    }

    /**
     * Get the first DirectiveBlockNode child.
     */
    public function firstDirectiveBlock(): ?DirectiveBlockNode
    {
        return $this->firstChildOfType(DirectiveBlockNode::class);
    }

    /**
     * Get the first ElementNode child.
     */
    public function firstElement(): ?ElementNode
    {
        return $this->firstChildOfType(ElementNode::class);
    }

    /**
     * Get the first TextNode child.
     */
    public function firstText(): ?TextNode
    {
        return $this->firstChildOfType(TextNode::class);
    }

    /**
     * Get the first EchoNode child.
     */
    public function firstEcho(): ?EchoNode
    {
        return $this->firstChildOfType(EchoNode::class);
    }

    /**
     * Get all DirectiveNode children.
     *
     * @return array<DirectiveNode>
     */
    public function getDirectiveChildren(): array
    {
        return $this->getChildrenOfType(DirectiveNode::class);
    }

    /**
     * Get all DirectiveBlockNode children.
     *
     * @return array<DirectiveBlockNode>
     */
    public function getDirectiveBlockChildren(): array
    {
        return $this->getChildrenOfType(DirectiveBlockNode::class);
    }

    /**
     * Get all ElementNode children.
     *
     * @return array<ElementNode>
     */
    public function getElementChildren(): array
    {
        return $this->getChildrenOfType(ElementNode::class);
    }

    /**
     * Get all TextNode children.
     *
     * @return array<TextNode>
     */
    public function getTextChildren(): array
    {
        return $this->getChildrenOfType(TextNode::class);
    }

    /**
     * Get all EchoNode children.
     *
     * @return array<EchoNode>
     */
    public function getEchoChildren(): array
    {
        return $this->getChildrenOfType(EchoNode::class);
    }

    /**
     * Get the text content of the first TextNode child.
     *
     * Returns null if there are no text children.
     */
    public function textContent(): ?string
    {
        $text = $this->firstText();

        return $text?->getDocumentContent();
    }

    /**
     * Get the trimmed text content of the first TextNode child.
     *
     * Returns null if there are no text children.
     */
    public function trimmedTextContent(): ?string
    {
        $content = $this->textContent();

        return $content !== null ? trim($content) : null;
    }

    /**
     * Get the combined text content of all TextNode children.
     */
    public function allTextContent(): string
    {
        $result = '';
        foreach ($this->getTextChildren() as $text) {
            $result .= $text->getDocumentContent();
        }

        return $result;
    }

    /**
     * Get the combined and trimmed text content of all TextNode children.
     */
    public function allTrimmedTextContent(): string
    {
        return trim($this->allTextContent());
    }
}
