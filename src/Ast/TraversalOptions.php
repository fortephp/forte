<?php

declare(strict_types=1);

namespace Forte\Ast;

final readonly class TraversalOptions
{
    public function __construct(
        public bool $includeInternal = false,
        public bool $includeSynthetic = true,
        public bool $includeTrivia = false,
        public ?int $maxDepth = null,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    public static function deep(): self
    {
        return new self(includeInternal: true);
    }

    public static function from(self|bool|null $options): self
    {
        if ($options instanceof self) {
            return $options;
        }

        if ($options === true) {
            return self::deep();
        }

        return self::defaults();
    }
}
