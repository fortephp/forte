<?php

declare(strict_types=1);

namespace Forte\Lexer\Extension;

use Forte\Lexer\Lexer;

class AttributeLexerContext
{
    public function __construct(private readonly Lexer $lexer, private int $startPosition) {}

    /**
     * Reset the context to a new start position.
     */
    public function reset(int $startPosition): self
    {
        $this->startPosition = $startPosition;

        return $this;
    }

    /**
     * Peek at a character at offset from the start position.
     *
     * @param  int  $offset  Offset from the start position (0 = first character)
     */
    public function peek(int $offset = 0): ?string
    {
        $absolutePos = $this->startPosition + $offset;
        $source = $this->lexer->source();
        $len = $this->lexer->length();

        if ($absolutePos >= $len) {
            return null;
        }

        return $source[$absolutePos];
    }

    /**
     * Check if source matches needle at an offset from the start position.
     *
     * @param  string  $needle  The string to match
     * @param  int  $offset  Offset from the start position
     */
    public function matches(string $needle, int $offset = 0): bool
    {
        return $this->lexer->matchesAt($needle, $this->startPosition + $offset);
    }

    /**
     * Get a substring from the start position.
     *
     * @param  int  $length  Number of characters to get
     */
    public function substr(int $length): string
    {
        return substr($this->lexer->source(), $this->startPosition, $length);
    }

    /**
     * Get the start position in the source.
     */
    public function startPosition(): int
    {
        return $this->startPosition;
    }

    /**
     * Get the source string.
     */
    public function source(): string
    {
        return $this->lexer->source();
    }

    /**
     * Get the remaining length from the start position to the end of the source document.
     */
    public function remainingLength(): int
    {
        return $this->lexer->length() - $this->startPosition;
    }

    /**
     * Check if the position is at or past the end of the source document.
     *
     * @param  int  $offset  Offset from the start position
     */
    public function isAtEnd(int $offset = 0): bool
    {
        return ($this->startPosition + $offset) >= $this->lexer->length();
    }
}
