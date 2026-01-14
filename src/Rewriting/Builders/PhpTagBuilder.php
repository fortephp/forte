<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Parser\NodeKind;

class PhpTagBuilder extends NodeBuilder
{
    public const TYPE_STANDARD = 'standard';

    public const TYPE_ECHO = 'echo';

    public function __construct(
        private readonly string $code,
        private readonly string $type = self::TYPE_STANDARD,
        private readonly bool $hasClose = true
    ) {}

    public static function php(string $code, bool $hasClose = true): self
    {
        return new self($code, self::TYPE_STANDARD, $hasClose);
    }

    public static function echo(string $code, bool $hasClose = true): self
    {
        return new self($code, self::TYPE_ECHO, $hasClose);
    }

    public function kind(): int
    {
        return NodeKind::PhpTag;
    }

    public function toSource(): string
    {
        $open = $this->type === self::TYPE_ECHO ? '<'.'?=' : '<'.'?php';
        $code = $this->code;

        $prefix = '';
        if ($this->type === self::TYPE_STANDARD) {
            $prefix = str_starts_with($code, ' ') || str_starts_with($code, "\t") || str_starts_with($code, "\n") ? '' : ' ';
        }

        $suffix = '';
        if ($this->hasClose) {
            $close = '?'.'>';
            $suffix = str_ends_with($code, ' ') || str_ends_with($code, "\t") || str_ends_with($code, "\n") ? $close : ' '.$close;
        }

        return $open.$prefix.$code.$suffix;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function hasClose(): bool
    {
        return $this->hasClose;
    }
}
