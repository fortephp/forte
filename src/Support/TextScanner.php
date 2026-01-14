<?php

declare(strict_types=1);

namespace Forte\Support;

abstract class TextScanner
{
    protected string $text;

    protected int $pos = 0;

    protected int $offset = 0;

    protected int $length = 0;

    protected ?string $curChar = null;

    protected bool $isAscii = false;

    protected function initializeScanner(string $text): void
    {
        $this->text = $text;
        $this->length = strlen($text);
        $this->pos = 0;
        $this->curChar = $this->text[$this->pos] ?? null;
        $this->isAscii = $this->length === mb_strlen($text);
    }

    public function isAtEnd(): bool
    {
        return $this->curChar === null;
    }

    /**
     * Advances a certain number of characters and returns the consumed characters.
     *
     * @param  int  $length  Number of characters to advance
     *
     * @phpstan-impure
     */
    public function advance(int $length = 1): string
    {
        $oldPos = $this->pos;

        if ($this->isAscii) {
            $buffer = substr($this->text, $oldPos, $length);
            $this->pos = $oldPos + $length;
            $this->curChar = $this->text[$this->pos] ?? null;

            $remaining = $this->length - $oldPos;
            $consumed = min($remaining, $length);
            $this->offset += $consumed;

            return $buffer;
        }

        if ($length === 1) {
            $b0 = ord($this->text[$oldPos] ?? "\0");
            if ($b0 < 0x80) {
                $byteLen = 1; // 0xxxxxxx
            } elseif (($b0 & 0xE0) === 0xC0) {
                $byteLen = 2; // 110xxxxx
            } elseif (($b0 & 0xF0) === 0xE0) {
                $byteLen = 3; // 1110xxxx
            } elseif (($b0 & 0xF8) === 0xF0) {
                $byteLen = 4; // 11110xxx
            } else {
                $byteLen = 1;
            }

            $buffer = substr($this->text, $oldPos, $byteLen);
            $this->pos = $oldPos + $byteLen;
            $this->curChar = $this->text[$this->pos] ?? null;

            $this->offset += 1;

            return $buffer;
        }

        $buffer = substr($this->text, $oldPos, $length);
        $this->pos = $oldPos + $length;
        $this->curChar = $this->text[$this->pos] ?? null;
        $this->offset += mb_strlen($buffer);

        return $buffer;
    }

    /**
     * Peek at characters ahead of current position.
     */
    public function peek(int $length = 1, int $offset = 0): ?string
    {
        $seek = substr($this->text, $this->pos + $offset, $length);

        return $seek === '' ? null : $seek;
    }

    /**
     * Look at characters behind current position.
     */
    public function prev(int $length = 1, int $offset = 0): ?string
    {
        $start = $this->pos - $offset - $length;

        if ($start < 0) {
            return null;
        }

        $seek = substr($this->text, $start, $length);

        return $seek === '' ? null : $seek;
    }

    /**
     * Check if a string matches at current position.
     *
     * @param  string  $search  String to match
     * @param  bool  $caseSensitive  Whether matching is case-sensitive
     */
    public function nextIs(string $search, bool $caseSensitive = false): bool
    {
        $len = strlen($search);

        return substr_compare($this->text, strtolower($search), $this->pos, $len, ! $caseSensitive) === 0;
    }

    /**
     * Advance until any of the specified characters is found.
     *
     * Returns consumed characters, if any.
     *
     * @param  string  $stopAt  Characters to stop at
     */
    public function advanceUntil(string $stopAt): string
    {
        $offset = strcspn($this->text, $stopAt, $this->pos);

        return $this->advance($offset);
    }

    /**
     * Advance while characters match the specified set.
     *
     * @param  string  $advanceWhile  Characters to continue advancing through
     */
    public function advanceWhile(string $advanceWhile): string
    {
        $offset = strspn($this->text, $advanceWhile, $this->pos);

        return $this->advance($offset);
    }

    /**
     * Advance through a quoted string, handling escapes.
     *
     * @param  string  $quote  Quote character (' or ")
     */
    public function advanceString(string $quote): string
    {
        $start = $this->pos;

        $this->pos++;

        ScanPrimitives::skipQuotedString($this->text, $this->pos, $this->length, $quote);

        $result = substr($this->text, $start, $this->pos - $start);

        $this->curChar = $this->text[$this->pos] ?? null;
        $this->offset += mb_strlen($result);

        return $result;
    }

    /**
     * Advance through a block comment /* ... *\/.
     */
    public function advanceComment(): string
    {
        $start = $this->pos;

        $this->pos += 2;

        ScanPrimitives::skipBlockComment($this->text, $this->pos, $this->length);

        $result = substr($this->text, $start, $this->pos - $start);

        $this->curChar = $this->text[$this->pos] ?? null;
        $this->offset += mb_strlen($result);

        return $result;
    }

    /**
     * Advance through a line comment // ... until the newline.
     */
    public function advanceLineComment(): string
    {
        $start = $this->pos;

        $this->pos += 2;

        ScanPrimitives::skipLineComment($this->text, $this->pos, $this->length);

        $result = substr($this->text, $start, $this->pos - $start);

        $this->curChar = $this->text[$this->pos] ?? null;
        $this->offset += mb_strlen($result);

        return $result;
    }

    /**
     * Advance through a heredoc/nowdoc <<<DELIM ... DELIM;
     */
    public function advanceHereNowDoc(): string
    {
        $start = $this->pos;

        $this->pos += 3;

        ScanPrimitives::skipHeredoc($this->text, $this->pos, $this->length);

        $result = substr($this->text, $start, $this->pos - $start);

        $this->curChar = $this->text[$this->pos] ?? null;
        $this->offset += mb_strlen($result);

        return $result;
    }

    public function isStartingString(): bool
    {
        return $this->curChar === '"' || $this->curChar === "'";
    }

    public function isStartingBlockComment(): bool
    {
        return $this->peek(2) === '/*';
    }

    public function isStartingLineComment(): bool
    {
        return $this->peek(2) === '//';
    }

    public function isStartingHeredoc(): bool
    {
        return $this->peek(3) === '<<<';
    }

    public function advancedInternalStructures(): string
    {
        if ($this->curChar === '"' || $this->curChar === "'") {
            return $this->advanceString($this->curChar);
        }

        if ($this->peek(2) === '/*') {
            return $this->advanceComment();
        }

        if ($this->peek(2) === '//') {
            return $this->advanceLineComment();
        }

        if ($this->peek(3) === '<<<') {
            return $this->advanceHereNowDoc();
        }

        return $this->advance();
    }

    /**
     * Advance through paired delimiters like (), [], {}.
     */
    public function advancePairedStructure(string $startChar, string $endChar): string
    {
        $start = $this->pos;

        $this->pos++;

        ScanPrimitives::skipBalancedPair($this->text, $this->pos, $this->length, $startChar, $endChar);

        $result = substr($this->text, $start, $this->pos - $start);

        $this->curChar = $this->text[$this->pos] ?? null;
        $this->offset += mb_strlen($result);

        return $result;
    }
}
