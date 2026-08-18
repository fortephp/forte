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
     * Get standalone directives as a lazy, fluent collection, optionally by
     * directive name.
     *
     * @param  string|array<string>|null  $names
     * @return LazyCollection<int, DirectiveNode>
     */
    public function queryDirectives(string|array|null $names = null): LazyCollection
    {
        $names = is_string($names) ? [$names] : $names;

        return $this->queryNodesOfType(
            DirectiveNode::class,
            function (DirectiveNode $node) use ($names): bool {
                if ($node->getParent() instanceof DirectiveBlockNode) {
                    return false;
                }

                if ($names === null) {
                    return true;
                }

                return $node->isAny($names);
            }
        );
    }

    /**
     * @return LazyCollection<int, DirectiveNode>
     *
     * @internal
     */
    protected function directives(): LazyCollection
    {
        return $this->queryDirectives();
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
     * Get paired directives as a lazy, fluent collection, optionally by name.
     *
     * @param  string|array<string>|null  $names
     * @return LazyCollection<int, DirectiveBlockNode>
     */
    public function queryBlockDirectives(string|array|null $names = null): LazyCollection
    {
        $names = is_string($names) ? [$names] : $names;

        return $this->queryNodesOfType(
            DirectiveBlockNode::class,
            function (DirectiveBlockNode $node) use ($names): bool {
                if ($names === null) {
                    return true;
                }

                return $node->isAny($names);
            }
        );
    }

    /**
     * @return LazyCollection<int, DirectiveBlockNode>
     *
     * @internal
     */
    protected function blockDirectives(): LazyCollection
    {
        return $this->queryBlockDirectives();
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
        return $this->queryDirectives($name)->first();
    }

    /**
     * Find all directives with the given name.
     *
     * @param  string  $name  Directive name, without @
     * @return LazyCollection<int, DirectiveNode>
     */
    public function findDirectivesByName(string $name): LazyCollection
    {
        return $this->queryDirectives($name);
    }

    /**
     * Find the first block directive with the given name.
     *
     * @param  string  $name  Directive name, without @
     */
    public function findBlockDirectiveByName(string $name): ?DirectiveBlockNode
    {
        /** @var DirectiveBlockNode|null */
        return $this->queryBlockDirectives($name)->first();
    }

    /**
     * Find all block directives with the given name.
     *
     * @param  string  $name  Directive name, without @
     * @return LazyCollection<int, DirectiveBlockNode>
     */
    public function findBlockDirectivesByName(string $name): LazyCollection
    {
        return $this->queryBlockDirectives($name);
    }
}
