<?php

declare(strict_types=1);

namespace Forte\Lexer\Tokens;

readonly class ExtensionTokenType
{
    public function __construct(
        public int $id,
        public string $namespace,
        public string $name,
        public string $label,
    ) {}
}
