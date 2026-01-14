<?php

declare(strict_types=1);

namespace Forte\Querying;

use Countable;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Node;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Node>
 */
readonly class XPathWrapper implements Countable, IteratorAggregate
{
    public function __construct(
        private XPathQuery $engine,
        private string $expression,
    ) {}

    /**
     * Get all matching nodes.
     *
     * @return NodeCollection<int, Node>
     *
     * @throws XPathException
     */
    public function get(): NodeCollection
    {
        return $this->engine->query($this->expression);
    }

    /**
     * @return array<int, Node>
     */
    public function all(): array
    {
        return $this->get()->all();
    }

    /**
     * Get the first matching node.
     *
     * @throws XPathException
     */
    public function first(): ?Node
    {
        return $this->engine->queryFirst($this->expression);
    }

    /**
     * Check if any nodes match.
     *
     * @throws XPathException
     */
    public function exists(): bool
    {
        return $this->engine->exists($this->expression);
    }

    /**
     * Count matching nodes.
     *
     * @return int<0, max>
     *
     * @throws XPathException
     */
    public function count(): int
    {
        /** @var int<0, max> */
        return $this->engine->count($this->expression);
    }

    /**
     * Evaluate the expression and returns the results.
     *
     * @return mixed The evaluation result
     *
     * @throws XPathException
     */
    public function evaluate(): mixed
    {
        return $this->engine->evaluate($this->expression);
    }

    /**
     * Get an iterator over matching nodes.
     *
     * @return Traversable<int, Node>
     *
     * @throws XPathException
     */
    public function getIterator(): Traversable
    {
        return $this->get();
    }
}
