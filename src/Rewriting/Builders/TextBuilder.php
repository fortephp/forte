<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Parser\NodeKind;

class TextBuilder extends NodeBuilder
{
    public function __construct(
        private readonly string $content
    ) {}

    public function kind(): int
    {
        return NodeKind::Text;
    }

    public function toSource(): string
    {
        return $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
