<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Support\ScanPrimitives;

trait PhpScanner
{
    protected function tryScanPhpTag(): bool
    {
        if ($this->pos + 1 >= $this->len || $this->source[$this->pos] !== '<' || $this->source[$this->pos + 1] !== '?') {
            return false;
        }

        $start = $this->pos;
        $this->pos += 2; // Skip <?

        // Check for specific PHP tag types
        if ($this->pos + 3 <= $this->len) {
            $matchesPhp = (strtolower((string) $this->source[$this->pos]) === 'p')
                && (strtolower((string) $this->source[$this->pos + 1]) === 'h')
                && (strtolower((string) $this->source[$this->pos + 2]) === 'p');

            if ($matchesPhp) {
                // Make sure it's followed by non-alphanumeric
                if ($this->pos + 3 < $this->len) {
                    $next = $this->source[$this->pos + 3];
                    if (ctype_alnum((string) $next) || $next === '_') {
                        // Could be <?phps or something else, not a PHP tag
                        $this->pos = $start;

                        return false;
                    }
                }
                $this->pos += 3;
                // Skip optional whitespace after <?php
                while ($this->pos < $this->len && ($this->source[$this->pos] === ' ' || $this->source[$this->pos] === "\t" || $this->source[$this->pos] === "\n" || $this->source[$this->pos] === "\r")) {
                    $this->pos++;
                }
            } elseif ($this->pos < $this->len && $this->source[$this->pos] === '=') {
                $this->pos++; // <?=
            } else {
                // Just <?. Short-tags are not supported and will just become a bogus comment later.
                $this->pos = $start;

                return false;
            }
        } elseif ($this->pos < $this->len && $this->source[$this->pos] === '=') {
            $this->pos++; // <?=
        } else {
            // Just <?. Short-tags are not supported and will just become a bogus comment later.
            $this->pos = $start;

            return false;
        }

        $this->emitToken(TokenType::PhpTagStart, $start, $this->pos);

        /* Scan until finding the ?> */
        $this->scanPhpContent();

        return true;
    }

    protected function scanPhpContent(): void
    {
        $start = $this->pos;

        while (true) {
            if ($this->pos >= $this->len) {
                // EOF
                if ($this->pos > $start) {
                    $this->emitToken(TokenType::PhpContent, $start, $this->pos);
                }

                return;
            }

            /* Check for ?> closing tag. */
            if ($this->pos + 1 < $this->len && $this->source[$this->pos] === '?' && $this->source[$this->pos + 1] === '>') {
                // Found closing tag
                if ($this->pos > $start) {
                    $this->emitToken(TokenType::PhpContent, $start, $this->pos);
                }
                $this->emitToken(TokenType::PhpTagEnd, $this->pos, $this->pos + 2);
                $this->pos += 2;

                return;
            }

            // Check for @ directive
            if ($this->source[$this->pos] === '@' && $this->pos + 1 < $this->len) {
                $next = $this->source[$this->pos + 1];
                if (ctype_alpha((string) $next)) {
                    // This is a Blade directive, stop PHP content here.
                    if ($this->pos > $start) {
                        $this->emitToken(TokenType::PhpContent, $start, $this->pos);
                    }

                    return;
                }
            }

            $byte = $this->source[$this->pos];

            if ($byte === '"' || $byte === "'") {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, $byte);
            } elseif ($byte === '/' && $this->pos + 1 < $this->len) {
                $next = $this->source[$this->pos + 1];
                if ($next === '/') {
                    // //-style line comment
                    $this->pos += 2;
                    ScanPrimitives::skipLineCommentStoppingAt($this->source, $this->pos, $this->len, '?>');
                } elseif ($next === '*') {
                    // Block comment
                    $this->pos += 2;
                    ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
                } else {
                    $this->pos++;
                }
            } elseif ($byte === '#') {
                // #-style line comment
                $this->pos++;
                ScanPrimitives::skipLineCommentStoppingAt($this->source, $this->pos, $this->len, '?>');
            } elseif ($byte === '<' && $this->pos + 2 < $this->len && $this->source[$this->pos + 1] === '<' && $this->source[$this->pos + 2] === '<') {
                // Heredoc/Nowdoc
                $this->pos += 3;
                ScanPrimitives::skipHeredoc($this->source, $this->pos, $this->len);
            } else {
                $this->pos++;
            }
        }
    }

    protected function phpTagStartLength(int $pos): int
    {
        // Need at least 2 bytes for <?
        if ($pos + 2 > $this->len || $this->source[$pos] !== '<' || $this->source[$pos + 1] !== '?') {
            return 0;
        }

        // Check for <?php
        if ($pos + 5 <= $this->len) {
            $matchesPhp = (strtolower((string) $this->source[$pos + 2]) === 'p')
                && (strtolower((string) $this->source[$pos + 3]) === 'h')
                && (strtolower((string) $this->source[$pos + 4]) === 'p');

            if ($matchesPhp) {
                if ($pos + 5 < $this->len) {
                    $next = $this->source[$pos + 5];
                    if (ctype_alnum((string) $next) || $next === '_') {
                        // Could be <?phps or something else, not a PHP tag
                        return 0;
                    }
                }

                return 5;
            }
        }

        // Check for <?=
        if ($pos + 3 <= $this->len && $this->source[$pos + 2] === '=') {
            return 3;
        }

        // Just <?. We do not support PHP short tags.
        return 0;
    }

    protected function isPhpTagEndAt(int $pos): bool
    {
        return $pos + 2 <= $this->len && $this->source[$pos] === '?' && $this->source[$pos + 1] === '>';
    }
}
