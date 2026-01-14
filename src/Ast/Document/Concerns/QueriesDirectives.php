<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\NodeCollection;
use Illuminate\Support\LazyCollection;

trait QueriesDirectives
{
    /**
     * @return LazyCollection<int, DirectiveNode>
     *
     * @internal
     */
    protected function directives(): LazyCollection
    {
        return $this->queryNodesOfType(
            DirectiveNode::class,
            fn (DirectiveNode $n) => ! $n->getParent() instanceof DirectiveBlockNode
        );
    }

    /**
     * Get all un-paired directives.
     *
     * @return NodeCollection<int, DirectiveNode>
     */
    public function getDirectives(): NodeCollection
    {
        return NodeCollection::make($this->directives());
    }

    /**
     * @return LazyCollection<int, DirectiveBlockNode>
     *
     * @internal
     */
    protected function blockDirectives(): LazyCollection
    {
        return $this->queryNodesOfType(DirectiveBlockNode::class);
    }

    /**
     * Get all paired directives.
     *
     * @return NodeCollection<int, DirectiveBlockNode>
     */
    public function getBlockDirectives(): NodeCollection
    {
        return NodeCollection::make($this->blockDirectives());
    }

    /**
     * Find the first directive with the given name.
     *
     * @param  string  $name  Directive name, without @.
     */
    public function findDirectiveByName(string $name): ?DirectiveNode
    {
        /** @var DirectiveNode|null */
        return $this->directives()
            ->filter(fn (DirectiveNode $n) => $n->is($name))
            ->first();
    }

    /**
     * Find all directives with the given name.
     *
     * @param  string  $name  Directive name, without @
     * @return LazyCollection<int, DirectiveNode>
     */
    public function findDirectivesByName(string $name): LazyCollection
    {
        return $this->directives()
            ->filter(fn (DirectiveNode $n) => $n->is($name));
    }

    /**
     * Find the first block directive with the given name.
     *
     * @param  string  $name  Directive name, without @
     */
    public function findBlockDirectiveByName(string $name): ?DirectiveBlockNode
    {
        /** @var DirectiveBlockNode|null */
        return $this->blockDirectives()
            ->filter(fn (DirectiveBlockNode $n) => $n->is($name))
            ->first();
    }

    /**
     * Find all block directives with the given name.
     *
     * @param  string  $name  Directive name, without @
     * @return LazyCollection<int, DirectiveBlockNode>
     */
    public function findBlockDirectivesByName(string $name): LazyCollection
    {
        return $this->blockDirectives()
            ->filter(fn (DirectiveBlockNode $n) => $n->is($name));
    }
}
