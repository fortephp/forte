<?php

declare(strict_types=1);

namespace Forte\Lexer\Tokens;

class Token
{
    /**
     * Create a new token array.
     *
     * @return array{type: int, start: int, end: int}
     */
    public static function create(int $type, int $start, int $end): array
    {
        return [
            'type' => $type,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Get token length in bytes.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function length(array $token): int
    {
        return $token['end'] - $token['start'];
    }

    /**
     * Check if token is zero-width, synthetic token.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function isEmpty(array $token): bool
    {
        return $token['start'] === $token['end'];
    }

    /**
     * Extract token text from the source document.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function text(array $token, string $source): string
    {
        return substr($source, $token['start'], self::length($token));
    }

    /**
     * Check if this token matches a specific type.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function is(array $token, int $type): bool
    {
        return $token['type'] === $type;
    }

    /**
     * Check if this token matches any of the given types.
     *
     * @param  array{type: int, start: int, end: int}  $token
     * @param  int[]  $types
     */
    public static function isAny(array $token, array $types): bool
    {
        return in_array($token['type'], $types, true);
    }

    /**
     * Get debug representation of token.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function toString(array $token): string
    {
        return sprintf(
            '%s[%d:%d]',
            TokenType::label($token['type']),
            $token['start'],
            $token['end']
        );
    }

    /**
     * Reconstruct source content from tokens.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     */
    public static function reconstructFromTokens(array $tokens, string $source): string
    {
        $reconstructed = '';
        foreach ($tokens as $token) {
            $reconstructed .= self::text($token, $source);
        }

        return $reconstructed;
    }
}
