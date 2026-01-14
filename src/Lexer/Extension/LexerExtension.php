<?php

declare(strict_types=1);

namespace Forte\Lexer\Extension;

use Forte\Lexer\Tokens\TokenTypeRegistry;

interface LexerExtension
{
    /**
     * Unique identifier for this extension.
     */
    public function name(): string;

    /**
     * Priority determines the order of extension checks.
     */
    public function priority(): int;

    /**
     * Get characters that trigger this extension.
     */
    public function triggerCharacters(): string;

    /**
     * Register custom token types for this extension.
     *
     * @return int[]
     */
    public function registerTokenTypes(TokenTypeRegistry $registry): array;

    /**
     * Check if this extension should activate at the current position.
     */
    public function shouldActivate(LexerContext $ctx): bool;

    /**
     * Attempt to tokenize at the current position.
     */
    public function tokenize(LexerContext $ctx): bool;
}
