<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Document\NodeCollection;
use Forte\Ast\EchoNode;
use Forte\Parser\NodeKind;
use Illuminate\Support\LazyCollection;

trait QueriesEchoes
{
    /**
     * Get all Blade echoes as a lazy, fluent collection.
     *
     * @return LazyCollection<int, EchoNode>
     */
    public function echoes(): LazyCollection
    {
        return $this->queryEchoes();
    }

    /**
     * @return LazyCollection<int, EchoNode>
     *
     * @internal
     */
    protected function queryEchoes(): LazyCollection
    {
        return $this->queryNodesOfType(EchoNode::class);
    }

    /**
     * Get all Blade echoes.
     *
     * @return NodeCollection<int, EchoNode>
     */
    public function getEchoes(): NodeCollection
    {
        return NodeCollection::make($this->echoes());
    }

    /**
     * Get raw Blade echoes as a lazy, fluent collection.
     *
     * @return LazyCollection<int, EchoNode>
     */
    public function rawEchoes(): LazyCollection
    {
        return $this->queryRawEchoes();
    }

    /**
     * @return LazyCollection<int, EchoNode>
     *
     * @internal
     */
    protected function queryRawEchoes(): LazyCollection
    {
        return $this->queryNodesOfType(
            EchoNode::class,
            fn (EchoNode $n) => $n->kind() === NodeKind::RawEcho
        );
    }

    /**
     * Get all raw Blade echoes.
     *
     * @return NodeCollection<int, EchoNode>
     */
    public function getRawEchoes(): NodeCollection
    {
        return NodeCollection::make($this->rawEchoes());
    }

    /**
     * Get escaped Blade echoes as a lazy, fluent collection.
     *
     * @return LazyCollection<int, EchoNode>
     */
    public function escapedEchoes(): LazyCollection
    {
        return $this->queryEscapedEchoes();
    }

    /**
     * @return LazyCollection<int, EchoNode>
     *
     * @internal
     */
    protected function queryEscapedEchoes(): LazyCollection
    {
        return $this->queryNodesOfType(
            EchoNode::class,
            fn (EchoNode $n) => $n->kind() === NodeKind::Echo
        );
    }

    /**
     * Get all escaped Blade echoes.
     *
     * @return NodeCollection<int, EchoNode>
     */
    public function getEscapedEchoes(): NodeCollection
    {
        return NodeCollection::make($this->escapedEchoes());
    }

    /**
     * Get triple Blade echoes as a lazy, fluent collection.
     *
     * @return LazyCollection<int, EchoNode>
     */
    public function tripleEchoes(): LazyCollection
    {
        return $this->queryTripleEchoes();
    }

    /**
     * @return LazyCollection<int, EchoNode>
     *
     * @internal
     */
    protected function queryTripleEchoes(): LazyCollection
    {
        return $this->queryNodesOfType(
            EchoNode::class,
            fn (EchoNode $n) => $n->kind() === NodeKind::TripleEcho
        );
    }

    /**
     * Get all triple ({{{ ... }}}) Blade echoes.
     *
     * @return NodeCollection<int, EchoNode>
     */
    public function getTripleEchoes(): NodeCollection
    {
        return NodeCollection::make($this->tripleEchoes());
    }
}
