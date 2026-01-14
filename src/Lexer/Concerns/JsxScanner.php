<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;
use Forte\Support\ScanPrimitives;

trait JsxScanner
{
    protected function tryScanJsxShorthandAttribute(): bool
    {
        if ($this->pos >= $this->len || $this->source[$this->pos] !== '{') {
            return false;
        }

        // Make sure it's not an echo.
        if ($this->pos + 1 < $this->len) {
            $next = $this->source[$this->pos + 1];
            if ($next === '{' || $next === '!') {
                return false;
            }
        }

        $start = $this->pos;
        $originalPos = $this->pos;
        $this->pos++;

        $fastScanPos = $this->pos;
        while ($fastScanPos < $this->len) {
            $byte = $this->source[$fastScanPos];

            if ($byte === '}') {
                $this->pos = $fastScanPos + 1;
                $this->emitToken(TokenType::JsxShorthandAttribute, $start, $this->pos);
                $this->state = State::BeforeAttrName;

                return true;
            }

            if ($byte === '{' || $byte === '\'' || $byte === '"' || $byte === '`' || $byte === '/') {
                break; // complex case
            }

            if ($byte === "\n" || $byte === "\r" || $byte === '@') {
                $this->pos = $originalPos;

                return false;
            }

            $fastScanPos++;
        }

        $ok = $this->scanBalancedJsLike(
            $start,
            TokenType::JsxShorthandAttribute,
            $originalPos,
            1,
            0,
            false,
            true,
            true,
            true
        );

        if ($ok) {
            $this->state = State::BeforeAttrName;
        }

        return $ok;
    }

    /**
     * Scan JSX attribute value: data={expression} or data=({expression})
     */
    protected function scanJsxAttributeValue(): bool
    {
        $start = $this->pos;
        $originalPos = $this->pos;

        $startsWithParen = $this->source[$this->pos] === '(';
        $this->pos++;

        $braceDepth = $startsWithParen ? 0 : 1;
        $parenDepth = $startsWithParen ? 1 : 0;

        // Simple {expr} with no nesting and no strings/templates/comments
        if ($braceDepth === 1 && $parenDepth === 0) {
            $fastScanPos = $this->pos;
            while ($fastScanPos < $this->len) {
                $byte = $this->source[$fastScanPos];

                if ($byte === '}') {
                    $this->pos = $fastScanPos + 1;
                    $this->emitToken(TokenType::JsxAttributeValue, $start, $this->pos);

                    return true;
                }

                if ($byte === '{' || $byte === '(' || $byte === '\'' || $byte === '"' || $byte === '`' || $byte === '/') {
                    break;
                }

                if ($byte === "\n" || $byte === "\r" || $byte === '@') {
                    $this->pos = $originalPos;

                    return false;
                }

                $fastScanPos++;
            }
        }

        return $this->scanBalancedJsLike(
            $start,
            TokenType::JsxAttributeValue,
            $originalPos,
            $braceDepth,
            $parenDepth,
            true,
            true,
            true,
            false
        );
    }

    /**
     * Try to scan TSX generic type parameter: <User> or <{ id: number }>
     */
    protected function tryScanTsxGenericType(): bool
    {
        if ($this->pos >= $this->len || $this->source[$this->pos] !== '<') {
            return false;
        }

        // Check if we are starting a PHP tag.
        if ($this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '?') {
            return false;
        }

        $start = $this->pos;
        $originalPos = $this->pos;
        $this->pos++; // Skip opening <

        // Validate the next character looks like a generic start
        if ($this->pos < $this->len) {
            $nextByte = $this->source[$this->pos];

            $isPascalCaseTag = $this->currentTagName !== ''
                && $this->currentTagName[0] >= 'A'
                && $this->currentTagName[0] <= 'Z';

            $looksLikeGeneric = $nextByte === '{'
                || ($nextByte >= 'A' && $nextByte <= 'Z')
                || $nextByte === '_';

            if ($isPascalCaseTag) {
                $looksLikeGeneric = $looksLikeGeneric
                    || ($nextByte >= 'a' && $nextByte <= 'z')
                    || ($nextByte >= '0' && $nextByte <= '9')
                    || $nextByte === '['
                    || $nextByte === '"'
                    || $nextByte === '\''
                    || $nextByte === '('
                    || $nextByte === ' '
                    || $nextByte === "\t";
            }

            if (! $looksLikeGeneric) {
                $this->pos = $originalPos;

                return false;
            }
        }

        $depth = 1;

        while (true) {
            if ($this->pos >= $this->len) {
                // EOF. Invalid generic, restore
                $this->pos = $originalPos;

                return false;
            }

            $nextPos = $this->nextInterestingPos("<>\"'`@\n\r");
            if ($nextPos >= $this->len) {
                $this->pos = $originalPos;

                return false;
            }

            $this->pos = $nextPos;
            $byte = $this->source[$this->pos];

            if ($byte === '<') {
                $depth++;
                $this->pos++;

                continue;
            }

            if ($byte === '>') {
                // Arrow function => should not close generics
                if ($this->pos > 0 && $this->source[$this->pos - 1] === '=') {
                    $this->pos++;

                    continue;
                }

                $depth--;
                $this->pos++;
                if ($depth === 0) {
                    $this->emitToken(TokenType::TsxGenericType, $start, $this->pos);

                    return true;
                }

                continue;
            }

            if ($byte === "\n" || $byte === "\r" || $byte === '@') {
                $this->pos = $originalPos;

                return false;
            }

            if ($byte === '"' || $byte === '\'') {
                $quote = $byte;
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, $quote);

                continue;
            }

            if ($byte === '`') {
                $this->pos++;
                ScanPrimitives::skipTemplateLiteral($this->source, $this->pos, $this->len);

                continue;
            }

            $this->pos++;
        }
    }

    private function nextInterestingPos(string $mask): int
    {
        if ($this->pos >= $this->len) {
            return $this->len;
        }

        $skip = strcspn($this->source, $mask, $this->pos);
        $next = $this->pos + $skip;

        return $next <= $this->len ? $next : $this->len;
    }

    /**
     * Balanced scanner for JSX-ish content.
     *
     * @param  int  $start  Token start offset.
     * @param  int  $tokenType  Token type to emit.
     * @param  int  $originalPos  Position to restore on abort.
     * @param  int  $braceDepth  Initial brace depth.
     * @param  int  $parenDepth  Initial paren depth.
     * @param  bool  $trackParens  Whether to track parentheses.
     * @param  bool  $allowComments  Whether to skip JS comments.
     * @param  bool  $abortOnDirective  Abort (restore) on '@'.
     * @param  bool  $abortOnNewline  Abort (restore) on a newline.
     */
    private function scanBalancedJsLike(
        int $start,
        int $tokenType,
        int $originalPos,
        int $braceDepth,
        int $parenDepth,
        bool $trackParens,
        bool $allowComments,
        bool $abortOnDirective,
        bool $abortOnNewline
    ): bool {
        while (true) {
            if ($this->pos >= $this->len) {
                // EOF: emit what we have
                $this->emitToken($tokenType, $start, $this->pos);

                return true;
            }

            $nextPos = $this->nextInterestingPos("{}()\"'`/@\n\r");
            if ($nextPos >= $this->len) {
                $this->pos = $this->len;
                $this->emitToken($tokenType, $start, $this->pos);

                return true;
            }

            $this->pos = $nextPos;
            $byte = $this->source[$this->pos];

            if ($byte === '{') {
                $braceDepth++;
                $this->pos++;

                continue;
            }

            if ($byte === '}') {
                $braceDepth--;
                $this->pos++;
                if ($braceDepth === 0 && (! $trackParens || $parenDepth === 0)) {
                    $this->emitToken($tokenType, $start, $this->pos);

                    return true;
                }

                continue;
            }

            if ($trackParens && $byte === '(') {
                $parenDepth++;
                $this->pos++;

                continue;
            }

            if ($trackParens && $byte === ')') {
                $parenDepth--;
                $this->pos++;
                if ($braceDepth === 0 && $parenDepth === 0) {
                    $this->emitToken($tokenType, $start, $this->pos);

                    return true;
                }

                continue;
            }

            if ($byte === '"' || $byte === '\'') {
                $quote = $byte;
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, $quote);

                continue;
            }

            if ($byte === '`') {
                $this->pos++;
                ScanPrimitives::skipTemplateLiteral($this->source, $this->pos, $this->len);

                continue;
            }

            if ($byte === '/') {
                if (! $allowComments) {
                    $this->pos++;

                    continue;
                }

                if ($this->pos + 1 < $this->len) {
                    $next = $this->source[$this->pos + 1];
                    if ($next === '/') {
                        $this->pos += 2;
                        ScanPrimitives::skipLineComment($this->source, $this->pos, $this->len);

                        continue;
                    }
                    if ($next === '*') {
                        $this->pos += 2;
                        ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);

                        continue;
                    }
                }

                $this->pos++;

                continue;
            }

            if ($abortOnDirective && $byte === '@') {
                $this->pos = $originalPos;

                return false;
            }

            if ($abortOnNewline && ($byte === "\n" || $byte === "\r")) {
                $this->pos = $originalPos;

                return false;
            }

            $this->pos++;
        }
    }
}
