<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\Tokens\TokenType;

trait DoctypeScanner
{
    protected function scanDoctype(): void
    {
        $this->emitToken(TokenType::DoctypeStart, $this->pos, $this->pos + 9);
        $this->pos += 9;

        $this->skipAndEmitWhitespace();

        $contentStart = $this->pos;
        while ($this->pos < $this->len && $this->source[$this->pos] !== '>') {
            $this->pos++;
        }

        if ($contentStart < $this->pos) {
            $this->emitToken(TokenType::Doctype, $contentStart, $this->pos);
        }

        if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
            $this->emitToken(TokenType::DoctypeEnd, $this->pos, $this->pos + 1);
            $this->pos++;
        }
    }
}
