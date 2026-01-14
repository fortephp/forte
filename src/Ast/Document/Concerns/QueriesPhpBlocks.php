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
     * @return LazyCollection<int, PhpBlockNode>
     *
     * @internal
     */
    protected function phpBlocks(): LazyCollection
    {
        return $this->queryNodesOfType(PhpBlockNode::class);
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
     * Get all PHP tag nodes (<?php ?>) as a lazy collection.
     *
     * @return LazyCollection<int, PhpTagNode>
     *
     * @internal
     */
    protected function phpTags(): LazyCollection
    {
        return $this->queryNodesOfType(PhpTagNode::class);
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
     * Get all text nodes as a lazy collection.
     *
     * @return LazyCollection<int, TextNode>
     *
     * @internal
     */
    protected function text(): LazyCollection
    {
        return $this->queryNodesOfType(TextNode::class);
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
