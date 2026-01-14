<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Parser\NodeKind;

class RawBuilder extends NodeBuilder
{
    public function __construct(
        private readonly string $source
    ) {}

    public function kind(): int
    {
        return NodeKind::Text;
    }

    public function toSource(): string
    {
        return $this->source;
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
