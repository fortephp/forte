<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Node;
use Forte\Querying\XPathQuery;
use Forte\Querying\XPathWrapper;

trait QueriesNodes
{
    private ?XPathQuery $xpathEngine = null;

    public function xpath(string $expression): XPathWrapper
    {
        return new XPathWrapper($this->getXPathEngine(), $expression);
    }

    private function getXPathEngine(): XPathQuery
    {
        if ($this->xpathEngine === null) {
            $this->xpathEngine = new XPathQuery($this);
        }

        return $this->xpathEngine;
    }

    /**
     * Find the first node matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     */
    public function find(callable $predicate): ?Node
    {
        foreach ($this->allDescendants() as $node) {
            if ($predicate($node)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Find all nodes matching a predicate.
     *
     * @param  callable(Node): bool  $predicate
     * @return array<int, Node>
     */
    public function findAll(callable $predicate): array
    {
        $results = [];
        foreach ($this->allDescendants() as $node) {
            if ($predicate($node)) {
                $results[] = $node;
            }
        }

        return $results;
    }

    /**
     * Find the deepest node that spans the given document character offset.
     *
     * @param  int  $offset  Zero-based character offset in the original text
     */
    public function findNodeAtOffset(int $offset): ?Node
    {
        $result = null;

        foreach ($this->allDescendants() as $node) {
            if ($node->startOffset() <= $offset && $offset < $node->endOffset()) {
                $result = $node;
            }
        }

        return $result;
    }

    /**
     * Find the node at the given line/character position.
     *
     * @param  int  $line  1-based line number
     * @param  int  $character  1-based character index within the line
     */
    public function findNodeAtPosition(int $line, int $character): ?Node
    {
        return $this->findNodeAtOffset(
            $this->getOffsetFromPosition($line, $character)
        );
    }
}
