<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\LexerError;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;
use Forte\Support\ScanPrimitives;

trait DirectiveScanner
{
    abstract public function directives(): Directives;

    protected function scanDirective(): void
    {
        $start = $this->pos;

        $this->pos++;

        $nameStart = $this->pos;
        $pos = $nameStart;

        while ($pos < $this->len) {
            $byte = $this->source[$pos];
            if (ctype_alnum((string) $byte) || $byte === '_') {
                $pos++;
            } else {
                break;
            }
        }

        if ($pos === $nameStart) {
            $this->emitToken(TokenType::Text, $start, $start + 1);
            $this->state = $this->returnState;
            $this->returnState = State::Data;

            return;
        }

        $name = substr($this->source, $nameStart, $pos - $nameStart);

        if (! $this->directives()->isDirective($name)) {
            // Not a known directive, emit everything scanned as text
            $this->pos = $pos;
            $this->emitToken(TokenType::Text, $start, $pos);
            $this->state = $this->returnState;
            $this->returnState = State::Data;

            return;
        }

        $nameLower = strtolower($name);

        $argPos = $pos;

        // Skip whitespace before potential args
        while ($argPos < $this->len && ctype_space((string) $this->source[$argPos])) {
            $argPos++;
        }

        $hasArgs = $argPos < $this->len && $this->source[$argPos] === '(';

        $this->pos = $pos;
        if ($nameLower === 'php' && ! $hasArgs) {
            // Emit PhpBlockStart token
            $this->emitToken(TokenType::PhpBlockStart, $start, $pos);
            $this->setPhpBlock(true);
            $this->state = $this->returnState;
            $this->returnState = State::Data;

            return;
        }

        if ($nameLower === 'endphp') {
            $this->emitToken(TokenType::PhpBlockEnd, $start, $pos);
            if ($this->phpBlock) {
                $this->setPhpBlock(false);
            }
            $this->state = $this->returnState;
            $this->returnState = State::Data;

            return;
        }

        if ($nameLower === 'verbatim') {
            $this->emitToken(TokenType::VerbatimStart, $start, $pos);
            $this->verbatimReturnState = $this->state;
            $this->setVerbatim(true);
            $this->state = State::Data;
            $this->returnState = State::Data;

            return;
        }

        if ($nameLower === 'endverbatim') {
            $this->emitToken(TokenType::VerbatimEnd, $start, $pos);
            if ($this->verbatimReturnState !== null) {
                $this->state = $this->verbatimReturnState;
                $this->verbatimReturnState = null;
            } else {
                $this->state = State::Data;
            }
            $this->setVerbatim(false);
            $this->returnState = State::Data;

            return;
        }

        $this->emitToken(TokenType::Directive, $start, $pos);

        if ($hasArgs && $argPos > $pos) {
            $this->emitToken(TokenType::Whitespace, $pos, $argPos);
            $this->pos = $argPos;
        }

        if ($hasArgs) {
            $this->scanDirectiveArgs();
        }

        $this->state = $this->returnState;
        $this->returnState = State::Data;
    }

    protected function scanDirectiveArgs(): void
    {
        $start = $this->pos;

        if ($this->pos >= $this->len || $this->source[$this->pos] !== '(') {
            return;
        }

        $this->pos++;
        $depth = 1;

        while ($this->pos < $this->len && $depth > 0) {
            $byte = $this->source[$this->pos];

            if ($byte === "'") {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, "'");
            } elseif ($byte === '"') {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, '"');
            } elseif ($byte === '`') {
                $this->pos++;
                ScanPrimitives::skipBacktickString($this->source, $this->pos, $this->len);
            } elseif ($byte === '<' && $this->peekAhead(1) === '<' && $this->peekAhead(2) === '<') {
                $this->pos += 3;
                ScanPrimitives::skipHeredoc($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '*') {
                $this->pos += 2;
                ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '/') {
                $this->pos += 2;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '#') {
                $this->pos++;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '(') {
                $depth++;
                $this->pos++;
            } elseif ($byte === ')') {
                $depth--;
                $this->pos++;
            } else {
                $this->pos++;
            }
        }

        if ($depth > 0) {
            $this->logError(LexerError::unexpectedEof(State::Data, $this->pos));
        }

        $this->emitToken(TokenType::DirectiveArgs, $start, $this->pos);
    }

    protected function isEndverbatimAt(int $pos): bool
    {
        if ($pos + 12 > $this->len || $this->source[$pos] !== '@') {
            return false;
        }

        if (strncasecmp(substr($this->source, $pos + 1, 11), 'endverbatim', 11) !== 0) {
            return false;
        }

        if ($pos + 12 < $this->len) {
            $next = $this->source[$pos + 12];
            if (ctype_alnum((string) $next) || $next === '_') {
                return false;
            }
        }

        return true;
    }

    protected function isEndphpAt(int $pos): bool
    {
        if ($pos + 7 > $this->len || $this->source[$pos] !== '@') {
            return false;
        }

        if (strncasecmp(substr($this->source, $pos + 1, 6), 'endphp', 6) !== 0) {
            return false;
        }

        if ($pos + 7 < $this->len) {
            $next = $this->source[$pos + 7];
            if (ctype_alnum((string) $next) || $next === '_') {
                return false;
            }
        }

        return true;
    }
}
