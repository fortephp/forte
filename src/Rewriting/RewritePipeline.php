<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Document\Document;

class RewritePipeline implements AstRewriter
{
    /** @var array<AstRewriter> */
    private array $steps;

    public function __construct(AstRewriter ...$steps)
    {
        $this->steps = $steps;
    }

    /**
     * Add a step to the pipeline.
     */
    public function add(AstRewriter $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Rewrite a document through all pipeline steps.
     */
    public function rewrite(Document $doc): Document
    {
        foreach ($this->steps as $step) {
            $doc = $step->rewrite($doc);
        }

        return $doc;
    }

    /**
     * Get the number of steps in the pipeline.
     */
    public function count(): int
    {
        return count($this->steps);
    }
}
