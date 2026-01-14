<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\Tokens\TokenType;

trait ByteHelpers
{
    public function peek(): ?string
    {
        return $this->pos < $this->len ? $this->source[$this->pos] : null;
    }

    public function peekAhead(int $n): ?string
    {
        $pos = $this->pos + $n;

        return $pos < $this->len ? $this->source[$pos] : null;
    }

    public function consume(): ?string
    {
        if ($this->pos < $this->len) {
            $byte = $this->source[$this->pos];
            $this->pos++;

            return $byte;
        }

        return null;
    }

    public function isAtEnd(): bool
    {
        return $this->pos >= $this->len;
    }

    /**
     * Skip whitespace characters at the current position.
     */
    protected function skipWhitespace(): void
    {
        while ($this->pos < $this->len && ctype_space((string) $this->source[$this->pos])) {
            $this->pos++;
        }
    }

    /**
     * Skip whitespace and emit a whitespace token if any was skipped.
     */
    protected function skipAndEmitWhitespace(): void
    {
        $start = $this->pos;

        while ($this->pos < $this->len && ctype_space((string) $this->source[$this->pos])) {
            $this->pos++;
        }

        if ($start < $this->pos) {
            $this->emitToken(TokenType::Whitespace, $start, $this->pos);
        }
    }
}
