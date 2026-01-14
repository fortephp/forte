<?php

declare(strict_types=1);

namespace Forte\Lexer;

readonly class LexerResult
{
    /**
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens  Tokens extracted from source
     * @param  array<int, LexerError>  $errors  Errors encountered (if any)
     */
    public function __construct(
        public array $tokens,
        public array $errors
    ) {}

    /**
     * Check if tokenization was successful.
     */
    public function isOk(): bool
    {
        return empty($this->errors);
    }

    /**
     * Check if there were errors during tokenization
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }
}
