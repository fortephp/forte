<?php

declare(strict_types=1);

namespace Forte\Lexer\Extension;

use Forte\Lexer\Lexer;
use Forte\Lexer\State;

readonly class LexerContext
{
    public function __construct(private Lexer $lexer) {}

    /**
     * Get the current source position.
     */
    public function position(): int
    {
        return $this->lexer->position();
    }

    /**
     * Get source length.
     */
    public function length(): int
    {
        return $this->lexer->length();
    }

    /**
     * Check if at the end of the source document.
     */
    public function isAtEnd(): bool
    {
        return $this->lexer->isAtEnd();
    }

    /**
     * Get current byte.
     */
    public function current(): ?string
    {
        return $this->lexer->peek();
    }

    /**
     * Peek at byte at offset from the current position.
     *
     * @param  int  $offset  Offset from current position (0 = current)
     */
    public function peek(int $offset = 0): ?string
    {
        return $this->lexer->peekAhead($offset);
    }

    /**
     * Check if source matches needle at the current position.
     */
    public function matches(string $needle, bool $caseInsensitive = false): bool
    {
        return $this->lexer->matchesAt($needle, $this->position(), $caseInsensitive);
    }

    /**
     * Check if source matches needle at offset from the current position.
     */
    public function matchesAt(string $needle, int $offset, bool $caseInsensitive = false): bool
    {
        return $this->lexer->matchesAt($needle, $this->position() + $offset, $caseInsensitive);
    }

    /**
     * Get the source string.
     */
    public function source(): string
    {
        return $this->lexer->source();
    }

    /**
     * Extract substring from the source document.
     */
    public function substr(int $start, int $length): string
    {
        return substr($this->lexer->source(), $start, $length);
    }

    /**
     * Advance position by the provided number of bytes.
     */
    public function advance(int $bytes = 1): void
    {
        $this->lexer->advance($bytes);
    }

    /**
     * Sets the lexer position.
     */
    public function setPosition(int $pos): void
    {
        $this->lexer->setPosition($pos);
    }

    /**
     * Advance until a character is found.
     *
     * @param  string  $char  The character to find
     */
    public function advanceUntil(string $char): string
    {
        $start = $this->position();
        $source = $this->source();
        $len = $this->length();
        $pos = $this->position();

        while ($pos < $len && $source[$pos] !== $char) {
            $pos++;
        }

        $this->setPosition($pos);

        return substr($source, $start, $pos - $start);
    }

    /**
     * Advance past a needle, if it matches.
     */
    public function advancePast(string $needle): bool
    {
        if ($this->matches($needle)) {
            $this->advance(strlen($needle));

            return true;
        }

        return false;
    }

    /**
     * Emit a token.
     *
     * @param  int  $type  The token type
     * @param  int  $start  Start offset in the source document
     * @param  int  $end  End offset in the source document (exclusive)
     */
    public function emit(int $type, int $start, int $end): void
    {
        $this->lexer->emitToken($type, $start, $end);
    }

    /**
     * Get current lexer state.
     */
    public function state(): State
    {
        return $this->lexer->getState();
    }

    /**
     * Set lexer state.
     */
    public function setState(State $state): void
    {
        $this->lexer->setState($state);
    }

    /**
     * Get the lexer return state for nested constructs.
     */
    public function returnState(): State
    {
        return $this->lexer->getReturnState();
    }

    /**
     * Set the lexer return state.
     */
    public function setReturnState(State $state): void
    {
        $this->lexer->setReturnState($state);
    }

    public function isVerbatim(): bool
    {
        return $this->lexer->isVerbatim();
    }

    public function setVerbatim(bool $verbatim): void
    {
        $this->lexer->setVerbatim($verbatim);
    }

    /**
     * Check if in PHP block mode.
     */
    public function isPhpBlock(): bool
    {
        return $this->lexer->isPhpBlock();
    }

    /**
     * Set PHP block mode.
     */
    public function setPhpBlock(bool $phpBlock): void
    {
        $this->lexer->setPhpBlock($phpBlock);
    }

    /**
     * Check if in PHP tag mode.
     */
    public function isPhpTag(): bool
    {
        return $this->lexer->isPhpTag();
    }

    /**
     * Set PHP tag mode.
     */
    public function setPhpTag(bool $phpTag): void
    {
        $this->lexer->setPhpTag($phpTag);
    }

    /**
     * Skip a PHP string (single or double-quoted).
     *
     * Assumes position is at the opening quote.
     * Returns position after the closing quote.
     */
    public function skipPhpString(): void
    {
        $this->lexer->skipPhpString();
    }

    /**
     * Skip a PHP comment (// or /* style).
     */
    public function skipPhpComment(): void
    {
        $this->lexer->skipPhpComment();
    }

    /**
     * Skip balanced parentheses, handling strings and comments.
     */
    public function skipBalancedParens(): int
    {
        return $this->lexer->skipBalancedParens();
    }
}
