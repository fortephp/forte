<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Node;
use Illuminate\Support\LazyCollection;

trait QueriesComments
{
    /**
     * @return LazyCollection<int, CommentNode|BladeCommentNode>
     *
     * @internal
     */
    protected function comments(): LazyCollection
    {
        /** @var LazyCollection<int, CommentNode|BladeCommentNode> */
        return $this->queryNodesOfType(
            Node::class,
            fn (Node $n) => $n instanceof CommentNode || $n instanceof BladeCommentNode
        );
    }

    /**
     * Get all HTML and Blade comments.
     *
     * @return NodeCollection<int, CommentNode|BladeCommentNode>
     */
    public function getComments(): NodeCollection
    {
        return NodeCollection::make($this->comments());
    }

    /**
     * @return LazyCollection<int, BladeCommentNode>
     *
     * @internal
     */
    protected function bladeComments(): LazyCollection
    {
        return $this->queryNodesOfType(BladeCommentNode::class);
    }

    /**
     * Get all Blade comments.
     *
     * @return NodeCollection<int, BladeCommentNode>
     */
    public function getBladeComments(): NodeCollection
    {
        return NodeCollection::make($this->bladeComments());
    }

    /**
     * @return LazyCollection<int, CommentNode>
     *
     * @internal
     */
    protected function htmlComments(): LazyCollection
    {
        return $this->queryNodesOfType(CommentNode::class);
    }

    /**
     * Get all HTML comments.
     *
     * @return NodeCollection<int, CommentNode>
     */
    public function getHtmlComments(): NodeCollection
    {
        return NodeCollection::make($this->htmlComments());
    }
}
