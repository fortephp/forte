<?php

declare(strict_types=1);

namespace Forte\Ast\Document;

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Components\ComponentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\TextNode;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * @template TKey of array-key
 * @template TValue of Node
 *
 * @extends Collection<TKey, TValue>
 */
class NodeCollection extends Collection
{
    /**
     * Create a new NodeCollection instance.
     *
     * @template TMakeKey of array-key
     * @template TMakeValue of Node
     *
     * @param  iterable<TMakeKey, TMakeValue>|LazyCollection<TMakeKey, TMakeValue>|null  $items
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make($items = []): static
    {
        if ($items instanceof LazyCollection) {
            /** @var static<TMakeKey, TMakeValue> */
            /** @phpstan-ignore new.static */
            $result = new static($items->all());

            return $result;
        }

        /** @var static<TMakeKey, TMakeValue> */
        return parent::make($items);
    }

    /**
     * Filter the collection to nodes that are instances of the given class.
     *
     * @param  class-string  $className
     */
    public function ofType(string $className): static
    {
        return $this->filter(fn ($n) => $n instanceof $className);
    }

    /**
     * Get a collection of directive nodes.
     *
     * @return static<int, DirectiveNode>
     */
    public function directives(): static
    {
        /** @var static<int, DirectiveNode> */
        return $this->ofType(DirectiveNode::class);
    }

    /**
     * Get a collection of block directive nodes.
     *
     * @return static<int, DirectiveBlockNode>
     */
    public function blockDirectives(): static
    {
        /** @var static<int, DirectiveBlockNode> */
        return $this->ofType(DirectiveBlockNode::class);
    }

    /**
     * Get a collection of echo nodes.
     *
     * @return static<int, EchoNode>
     */
    public function echoes(): static
    {
        /** @var static<int, EchoNode> */
        return $this->ofType(EchoNode::class);
    }

    /**
     * Get a collection of element nodes.
     *
     * @return static<int, ElementNode>
     */
    public function elements(): static
    {
        /** @var static<int, ElementNode> */
        return $this->ofType(ElementNode::class);
    }

    /**
     * Get a collection of comment nodes.
     */
    public function comments(): static
    {
        return $this->filter(fn ($n) => $n instanceof CommentNode || $n instanceof BladeCommentNode);
    }

    /**
     * Get a collection of PHP block nodes.
     *
     * @return static<int, PhpBlockNode>
     */
    public function phpBlocks(): static
    {
        /** @var static<int, PhpBlockNode> */
        return $this->ofType(PhpBlockNode::class);
    }

    /**
     * Get a collection of text nodes.
     *
     * @return static<int, TextNode>
     */
    public function text(): static
    {
        /** @var static<int, TextNode> */
        return $this->ofType(TextNode::class);
    }

    /**
     * Get a collection of component nodes.
     *
     * @return static<int, ComponentNode>
     */
    public function components(): static
    {
        /** @var static<int, ComponentNode> */
        return $this->ofType(ComponentNode::class);
    }

    /**
     * Filter nodes that cover the given 1-based line number.
     *
     * @param  int  $line  1-based line number
     */
    public function onLine(int $line): static
    {
        return $this->filter(function (Node $node) use ($line) {
            $start = $node->startLine();
            $end = $node->endLine();

            return $start <= $line && $end >= $line;
        });
    }

    /**
     * Filter nodes that start on the given 1-based line number.
     *
     * @param  int  $line  1-based line number
     */
    public function startingOnLine(int $line): static
    {
        return $this->filter(fn (Node $n) => $n->startLine() === $line);
    }

    /**
     * Filter nodes that end on the given 1-based line number.
     *
     * @param  int  $line  1-based line number
     */
    public function endingOnLine(int $line): static
    {
        return $this->filter(fn (Node $n) => $n->endLine() === $line);
    }

    /**
     * Filter nodes whose character range contains the given offset.
     *
     * @param  int  $offset  Zero-based offset
     */
    public function containingOffset(int $offset): static
    {
        return $this->filter(function (Node $n) use ($offset) {
            $start = $n->startOffset();
            $end = $n->endOffset();

            return $start <= $offset && $offset < $end;
        });
    }

    /**
     * Filter nodes fully contained between the given start and end offsets.
     *
     * @param  int  $startOffset  Zero-based start offset (inclusive)
     * @param  int  $endOffset  Zero-based end offset (exclusive)
     */
    public function betweenOffsets(int $startOffset, int $endOffset): static
    {
        return $this->filter(fn (Node $n) => $n->startOffset() >= $startOffset
            && $n->endOffset() <= $endOffset);
    }

    /**
     * Get a collection of only root nodes (nodes without parents).
     */
    public function roots(): static
    {
        return $this->filter(fn (Node $n) => $n->isRoot());
    }

    /**
     * Get a collection of leaf nodes (nodes without children).
     */
    public function leaves(): static
    {
        return $this->filter(fn (Node $n) => ! $n->hasChildren());
    }

    /**
     * Get a collection of nodes that have children.
     */
    public function withChildren(): static
    {
        return $this->filter(fn (Node $n) => $n->hasChildren());
    }

    /**
     * Get a collection of directives with the provided name.
     *
     * @param  string  $name  Directive name, without @.
     */
    public function whereDirectiveName(string $name): static
    {
        return $this->filter(fn ($n) => ($n instanceof DirectiveNode || $n instanceof DirectiveBlockNode) && $n->is($name));
    }

    /**
     * Get a collection of elements that match the provided tag name.
     *
     * @param  string  $tag  Tag name to match
     */
    public function whereElementIs(string $tag): static
    {
        return $this->filter(fn ($n) => $n instanceof ElementNode
            && ! $n instanceof ComponentNode
            && $n->is($tag));
    }

    /**
     * Get all items in the collection as a re-indexed array.
     *
     * @return array<int, TValue>
     */
    public function all(): array
    {
        return array_values(parent::all());
    }

    /**
     * Render all nodes in the collection and concatenate the output.
     */
    public function render(): string
    {
        return $this->map(fn (Node $n) => $n->render())->implode('');
    }
}
