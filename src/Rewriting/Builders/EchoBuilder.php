<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Parser\NodeKind;

class EchoBuilder extends NodeBuilder
{
    public const TYPE_ESCAPED = 'escaped';

    public const TYPE_RAW = 'raw';

    public const TYPE_TRIPLE = 'triple';

    public function __construct(
        private readonly string $expression,
        private readonly string $type = self::TYPE_ESCAPED
    ) {}

    public function kind(): int
    {
        return match ($this->type) {
            self::TYPE_RAW => NodeKind::RawEcho,
            self::TYPE_TRIPLE => NodeKind::TripleEcho,
            default => NodeKind::Echo,
        };
    }

    public function toSource(): string
    {
        return match ($this->type) {
            self::TYPE_RAW => '{!! '.$this->expression.' !!}',
            self::TYPE_TRIPLE => '{{{ '.$this->expression.' }}}',
            default => '{{ '.$this->expression.' }}',
        };
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
