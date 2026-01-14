<?php

declare(strict_types=1);

namespace Forte\Parser;

use Forte\Lexer\Tokens\TokenType;

class ConstructScanner
{
    /**
     * @var array<int, int>
     */
    private const CONSTRUCT_PAIRS = [
        TokenType::EchoStart => TokenType::EchoEnd,
        TokenType::RawEchoStart => TokenType::RawEchoEnd,
        TokenType::TripleEchoStart => TokenType::TripleEchoEnd,
        TokenType::PhpTagStart => TokenType::PhpTagEnd,
        TokenType::PhpBlockStart => TokenType::PhpBlockEnd,
    ];

    /**
     * @var array<int, int>
     */
    private const CONSTRUCT_KINDS = [
        TokenType::EchoStart => NodeKind::Echo,
        TokenType::RawEchoStart => NodeKind::RawEcho,
        TokenType::TripleEchoStart => NodeKind::TripleEcho,
        TokenType::PhpTagStart => NodeKind::PhpTag,
        TokenType::PhpBlockStart => NodeKind::PhpBlock,
    ];

    public static function isConstructStart(int $tokenType): bool
    {
        return isset(self::CONSTRUCT_PAIRS[$tokenType]);
    }

    public static function isEchoStart(int $tokenType): bool
    {
        return $tokenType === TokenType::EchoStart
            || $tokenType === TokenType::RawEchoStart
            || $tokenType === TokenType::TripleEchoStart;
    }

    public static function isPhpStart(int $tokenType): bool
    {
        return $tokenType === TokenType::PhpTagStart
            || $tokenType === TokenType::PhpBlockStart;
    }

    /**
     * Get the end token type for a construct start token.
     */
    public static function getEndType(int $startType): ?int
    {
        return self::CONSTRUCT_PAIRS[$startType] ?? null;
    }

    /**
     * Get the NodeKind constant for a construct start token.
     */
    public static function getNodeKind(int $startType): ?int
    {
        return self::CONSTRUCT_KINDS[$startType] ?? null;
    }

    /**
     * Scan a construct from start to end, returning the new position and token count.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens  The token array
     * @param  int  $pos  Current position (at construct start token)
     * @param  int  $end  End boundary (exclusive)
     * @return array{int, int} [newPosition, tokenCount]
     */
    public static function scanConstruct(array $tokens, int $pos, int $end): array
    {
        if ($pos >= $end) {
            return [$pos, 0];
        }

        $startType = $tokens[$pos]['type'];
        $endType = self::getEndType($startType);

        if ($endType === null) {
            // Not starting anything, just return a single token to advance
            return [$pos + 1, 1];
        }

        $count = 1;  // Start token
        $pos++;

        // Scan until end token or boundary
        while ($pos < $end && $tokens[$pos]['type'] !== $endType) {
            $count++;
            $pos++;
        }

        // Include end token if found
        if ($pos < $end && $tokens[$pos]['type'] === $endType) {
            $count++;
            $pos++;
        }

        return [$pos, $count];
    }

    /**
     * Find the end position of a construct (exclusive).
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens  The token array
     * @param  int  $pos  Current position (at construct start token)
     * @param  int  $end  End boundary (exclusive)
     */
    public static function findConstructEnd(array $tokens, int $pos, int $end): int
    {
        [$newPos, $_] = self::scanConstruct($tokens, $pos, $end);

        return $newPos;
    }

    /**
     * Advance past a construct or single token.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens  The token array
     * @param  int  $pos  Current position
     * @param  int  $end  End boundary (exclusive)
     */
    public static function advancePast(array $tokens, int $pos, int $end): int
    {
        if ($pos >= $end) {
            return $pos;
        }

        $tokenType = $tokens[$pos]['type'];

        if (self::isConstructStart($tokenType)) {
            return self::findConstructEnd($tokens, $pos, $end);
        }

        return $pos + 1;
    }

    /**
     * Count tokens in a construct starting at the given position.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens  The token array
     * @param  int  $pos  Current position (at construct start token)
     * @param  int  $end  End boundary (exclusive)
     */
    public static function countConstructTokens(array $tokens, int $pos, int $end): int
    {
        [$_, $count] = self::scanConstruct($tokens, $pos, $end);

        return $count;
    }
}
