<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Document\NodeCollection;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;
use Illuminate\Support\LazyCollection;

trait QueriesPhpBlocks
{
    /**
     * Get Blade PHP blocks as a lazy, fluent collection.
     *
     * @return LazyCollection<int, PhpBlockNode>
     */
    public function queryPhpBlocks(): LazyCollection
    {
        return $this->queryNodesOfType(PhpBlockNode::class);
    }

    /**
     * @return LazyCollection<int, PhpBlockNode>
     *
     * @internal
     */
    protected function phpBlocks(): LazyCollection
    {
        return $this->queryPhpBlocks();
    }

    /**
     * Get all Blade PHP blocks.
     *
     * @return NodeCollection<int, PhpBlockNode>
     */
    public function getPhpBlocks(): NodeCollection
    {
        return NodeCollection::make($this->phpBlocks());
    }

    /**
     * Get all PHP tag nodes (<?php ?>) as a lazy, fluent collection.
     *
     * @return LazyCollection<int, PhpTagNode>
     */
    public function queryPhpTags(): LazyCollection
    {
        return $this->queryNodesOfType(PhpTagNode::class);
    }

    /**
     * @return LazyCollection<int, PhpTagNode>
     *
     * @internal
     */
    protected function phpTags(): LazyCollection
    {
        return $this->queryPhpTags();
    }

    /**
     * Get all PHP tags.
     *
     * @return NodeCollection<int, PhpTagNode>
     */
    public function getPhpTags(): NodeCollection
    {
        return NodeCollection::make($this->phpTags());
    }

    /**
     * Get all text nodes as a lazy, fluent collection.
     *
     * @return LazyCollection<int, TextNode>
     */
    public function queryTextNodes(): LazyCollection
    {
        return $this->queryNodesOfType(TextNode::class);
    }

    /**
     * @return LazyCollection<int, TextNode>
     *
     * @internal
     */
    protected function text(): LazyCollection
    {
        return $this->queryTextNodes();
    }

    /**
     * Get all Text nodes.
     *
     * @return NodeCollection<int, TextNode>
     */
    public function getText(): NodeCollection
    {
        return NodeCollection::make($this->text());
    }
}
