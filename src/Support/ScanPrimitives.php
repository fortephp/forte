<?php

declare(strict_types=1);

namespace Forte\Support;

class ScanPrimitives
{
    /**
     * Find the position of the next line ending.
     */
    public static function findLineEnding(string $source, int $pos, ?int $len = null): int|false
    {
        $lfPos = strpos($source, "\n", $pos);
        $crPos = strpos($source, "\r", $pos);

        if ($len !== null) {
            if ($lfPos !== false && $lfPos >= $len) {
                $lfPos = false;
            }
            if ($crPos !== false && $crPos >= $len) {
                $crPos = false;
            }
        }

        if ($lfPos === false && $crPos === false) {
            return false;
        }

        if ($lfPos === false) {
            return $crPos;
        }

        if ($crPos === false) {
            return $lfPos;
        }

        // Return whichever comes first
        return min($lfPos, $crPos);
    }

    /**
     * Skip past a line ending sequence.
     */
    public static function skipLineEnding(string $source, int &$pos, int $len): void
    {
        if ($pos >= $len) {
            return;
        }

        $byte = $source[$pos];

        if ($byte === "\n") {
            $pos++;
        } elseif ($byte === "\r") {
            $pos++;

            if ($pos < $len && $source[$pos] === "\n") {
                $pos++;
            }
        }
    }

    /**
     * Skip quoted string content.
     */
    public static function skipQuotedString(string $source, int &$pos, int $len, string $quote): void
    {
        while ($pos < $len) {
            $quotePos = strpos($source, $quote, $pos);

            if ($quotePos === false) {
                $pos = $len;

                return;
            }

            $pos = $quotePos;

            $backslashCount = 0;
            $checkPos = $pos - 1;

            while ($checkPos >= 0 && $source[$checkPos] === '\\') {
                $backslashCount++;
                $checkPos--;
            }

            $pos++;

            if ($backslashCount % 2 === 0) {
                return;
            }
        }

        $pos = $len;
    }

    /**
     * Skip block comment content: /* ... *\/
     */
    public static function skipBlockComment(string $source, int &$pos, int $len): void
    {
        while ($pos < $len) {
            $starPos = strpos($source, '*', $pos);

            if ($starPos === false) {
                $pos = $len;

                return;
            }

            $pos = $starPos + 1; // Move past *

            if ($pos < $len && $source[$pos] === '/') {
                $pos++;

                return;
            }
        }

        $pos = $len;
    }

    /**
     * Skip a PHP-style line comment.
     */
    public static function skipLineComment(string $source, int &$pos, int $len): void
    {
        $lineEndPos = self::findLineEnding($source, $pos, $len);

        if ($lineEndPos === false) {
            $pos = $len;
        } else {
            $pos = $lineEndPos;
            self::skipLineEnding($source, $pos, $len);
        }
    }

    /**
     * Skip line comment, stopping early if a specific sequence is found.
     */
    public static function skipLineCommentStoppingAt(string $source, int &$pos, int $len, string $stopSequence): bool
    {
        $seqLen = strlen($stopSequence);

        while ($pos < $len) {
            $byte = $source[$pos];

            if ($byte === "\n" || $byte === "\r") {
                $pos++;

                return false;
            }

            if ($pos + $seqLen <= $len && substr($source, $pos, $seqLen) === $stopSequence) {
                return true;
            }

            $pos++;
        }

        return false;
    }

    /**
     * Skip line comment content while detecting specific sequences.
     *
     * @param  string  $source  Source code
     * @param  int  &$pos  Current position (will be advanced)
     * @param  int  $len  Length of the original source
     * @param  array<string>  $sequences  Sequences to detect (e.g., ['?>'])
     * @return array<array{sequence: string, offset: int}>
     */
    public static function skipLineCommentDetecting(string $source, int &$pos, int $len, array $sequences): array
    {
        $detected = [];

        $lineEndPos = self::findLineEnding($source, $pos, $len);
        $endPos = $lineEndPos === false ? $len : $lineEndPos;

        // Scan for each sequence within the comment range
        foreach ($sequences as $sequence) {
            $seqLen = strlen($sequence);
            $searchPos = $pos;

            while ($searchPos + $seqLen <= $endPos) {
                $foundPos = strpos($source, $sequence, $searchPos);

                if ($foundPos === false || $foundPos >= $endPos) {
                    break;
                }

                $detected[] = [
                    'sequence' => $sequence,
                    'offset' => $foundPos,
                ];

                // Continue searching after this occurrence
                $searchPos = $foundPos + $seqLen;
            }
        }

        if ($lineEndPos === false) {
            $pos = $len;
        } else {
            $pos = $lineEndPos;
            self::skipLineEnding($source, $pos, $len);
        }

        return $detected;
    }

    /**
     * Skip HEREDOC or NOWDOC content: <<<DELIM or <<<'DELIM'
     */
    public static function skipHeredoc(string $source, int &$pos, int $len): void
    {
        if ($pos >= $len) {
            return; // EOF
        }

        $isNowdoc = $source[$pos] === "'";
        if ($isNowdoc) {
            $pos++; // Skip opening quote
        }

        $delimStart = $pos;
        while ($pos < $len) {
            $byte = $source[$pos];
            if (ctype_alnum($byte) || $byte === '_') {
                $pos++;
            } else {
                break;
            }
        }

        if ($pos === $delimStart) {
            $pos = $len;

            return;
        }

        $delimiter = substr($source, $delimStart, $pos - $delimStart);

        if ($isNowdoc && $pos < $len && $source[$pos] === "'") {
            $pos++;
        }

        $lineEndPos = self::findLineEnding($source, $pos, $len);
        if ($lineEndPos === false) {
            $pos = $len;

            return;
        }
        $pos = $lineEndPos;
        self::skipLineEnding($source, $pos, $len);

        $delimLen = strlen($delimiter);
        while ($pos < $len) {
            if ($pos + $delimLen <= $len) {
                $potentialDelim = substr($source, $pos, $delimLen);

                if ($potentialDelim === $delimiter) {
                    $afterPos = $pos + $delimLen;
                    if (
                        $afterPos >= $len
                        || $source[$afterPos] === "\n"
                        || $source[$afterPos] === "\r"
                        || $source[$afterPos] === ';'
                    ) {
                        $pos = $afterPos;

                        if ($pos < $len && $source[$pos] === ';') {
                            $pos++;
                        }

                        self::skipLineEnding($source, $pos, $len);

                        return;
                    }
                }
            }

            // Not the delimiter, skip to the next line
            $lineEndPos = self::findLineEnding($source, $pos, $len);
            if ($lineEndPos === false) {
                // No more line endings
                $pos = $len;

                return;
            }
            $pos = $lineEndPos;
            self::skipLineEnding($source, $pos, $len);
        }

        // EOF without closing delimiter
        $pos = $len;
    }

    /**
     * Skip template literal content: `text ${expr} more`
     */
    public static function skipTemplateLiteral(string $source, int &$pos, int $len): void
    {
        while ($pos < $len) {
            $byte = $source[$pos];

            if ($byte === '`') {
                $pos++;

                return;
            } elseif ($byte === '\\') {
                $pos += 2;
            } elseif ($byte === '$' && $pos + 1 < $len && $source[$pos + 1] === '{') {
                // Template expression ${...}
                $pos += 2; // Skip ${
                self::skipTemplateExpression($source, $pos, $len);
            } else {
                $pos++;
            }
        }
    }

    /**
     * Skip content inside a template expression ${...}
     */
    public static function skipTemplateExpression(string $source, int &$pos, int $len): void
    {
        $depth = 1;

        while ($depth > 0 && $pos < $len) {
            $byte = $source[$pos];

            if ($byte === '{') {
                $depth++;
                $pos++;
            } elseif ($byte === '}') {
                $depth--;
                $pos++;
            } elseif ($byte === '\'' || $byte === '"') {
                $pos++;
                self::skipQuotedString($source, $pos, $len, $byte);
            } elseif ($byte === '`') {
                $pos++;
                self::skipTemplateLiteral($source, $pos, $len);
            } elseif ($byte === '/' && $pos + 1 < $len) {
                $next = $source[$pos + 1];
                if ($next === '/') {
                    $pos += 2;
                    self::skipLineComment($source, $pos, $len);
                } elseif ($next === '*') {
                    $pos += 2;
                    self::skipBlockComment($source, $pos, $len);
                } else {
                    $pos++;
                }
            } else {
                $pos++;
            }
        }
    }

    /**
     * Skip backtick string content: `command`
     */
    public static function skipBacktickString(string $source, int &$pos, int $len): void
    {
        self::skipQuotedString($source, $pos, $len, '`');
    }

    /**
     * Skip balanced paired delimiters with PHP-aware content handling.
     *
     * @param  string  $open  Opening delimiter character (e.g., '(', '[', '{')
     * @param  string  $close  Closing delimiter character (e.g., ')', ']', '}')
     */
    public static function skipBalancedPair(string $source, int &$pos, int $len, string $open, string $close): void
    {
        $depth = 1;

        while ($depth > 0 && $pos < $len) {
            $byte = $source[$pos];

            if ($byte === $open) {
                $depth++;
                $pos++;
            } elseif ($byte === $close) {
                $depth--;
                $pos++;
            } elseif ($byte === '\'' || $byte === '"') {
                $pos++;
                self::skipQuotedString($source, $pos, $len, $byte);
            } elseif ($byte === '/' && $pos + 1 < $len) {
                $next = $source[$pos + 1];
                if ($next === '/') {
                    $pos += 2;
                    self::skipLineComment($source, $pos, $len);
                } elseif ($next === '*') {
                    $pos += 2;
                    self::skipBlockComment($source, $pos, $len);
                } else {
                    $pos++;
                }
            } elseif ($byte === '<' && $pos + 2 < $len && $source[$pos + 1] === '<' && $source[$pos + 2] === '<') {
                $pos += 3;
                self::skipHeredoc($source, $pos, $len);
            } else {
                $pos++;
            }
        }
    }
}
