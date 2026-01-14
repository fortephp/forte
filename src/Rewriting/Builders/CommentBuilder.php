<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Parser\NodeKind;

class CommentBuilder extends NodeBuilder
{
    public function __construct(
        private readonly string $content
    ) {}

    public function kind(): int
    {
        return NodeKind::Comment;
    }

    public function toSource(): string
    {
        return '<!--'.$this->addContentWhitespace($this->content).'-->';
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
