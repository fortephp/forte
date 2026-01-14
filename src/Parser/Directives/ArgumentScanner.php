<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

use Forte\Support\ScanPrimitives;

class ArgumentScanner
{
    /**
     * Count top-level arguments in a directive argument string.
     *
     * @param  string  $args  The argument string (without outer parentheses)
     */
    public static function countArguments(string $args): int
    {
        $len = strlen($args);
        if ($len === 0) {
            return 0;
        }

        $pos = 0;
        self::skipWhitespace($args, $len, $pos);

        if ($pos >= $len) {
            return 0;
        }

        $count = 1;
        $depth = 0;

        while ($pos < $len) {
            $byte = $args[$pos];

            if ($byte === '"' || $byte === "'") {
                $pos++;
                ScanPrimitives::skipQuotedString($args, $pos, $len, $byte);

                continue;
            }

            if (self::isHeredocStart($args, $pos, $len)) {
                $pos += 3;
                ScanPrimitives::skipHeredoc($args, $pos, $len);

                continue;
            }

            if ($byte === '(' || $byte === '[' || $byte === '{') {
                $depth++;
                $pos++;

                continue;
            }

            if ($byte === ')' || $byte === ']' || $byte === '}') {
                $depth--;
                $pos++;

                continue;
            }

            if ($byte === ',' && $depth === 0) {
                $count++;
            }

            $pos++;
        }

        return $count;
    }

    /**
     * Check if arguments start with array notation.
     *
     * @param  string  $args  The argument string
     */
    public static function startsWithArray(string $args): bool
    {
        $len = strlen($args);
        $pos = 0;

        self::skipWhitespace($args, $len, $pos);

        return $pos < $len && $args[$pos] === '[';
    }

    /**
     * Check if arguments are a simple string (single quoted or doubly quoted).
     *
     * A "simple string" means the entire argument is just one
     * quoted string, not an array or complex expression.
     *
     * @param  string  $args  The argument string
     */
    public static function isSimpleString(string $args): bool
    {
        $len = strlen($args);
        $pos = 0;

        self::skipWhitespace($args, $len, $pos);

        if ($pos >= $len) {
            return false;
        }

        $byte = $args[$pos];

        if ($byte !== '"' && $byte !== "'") {
            return false;
        }

        $pos++;
        ScanPrimitives::skipQuotedString($args, $pos, $len, $byte);

        self::skipWhitespace($args, $len, $pos);

        return $pos >= $len;
    }

    /**
     * Check if arguments contain only a translation key (no default value).
     *
     * For @lang style: @lang('key') has 1 arg, @lang('key', 'default') has 2.
     *
     * @param  string  $args  The argument string
     */
    public static function hasSingleArgument(string $args): bool
    {
        return self::countArguments($args) === 1;
    }

    /**
     * Extract the first argument from an argument string.
     *
     * @param  string  $args  The argument string
     */
    public static function getFirstArgument(string $args): ?string
    {
        $len = strlen($args);
        if ($len === 0) {
            return null;
        }

        $pos = 0;
        self::skipWhitespace($args, $len, $pos);

        if ($pos >= $len) {
            return null;
        }

        $start = $pos;
        $depth = 0;

        while ($pos < $len) {
            $byte = $args[$pos];

            if ($byte === '"' || $byte === "'") {
                $pos++;
                ScanPrimitives::skipQuotedString($args, $pos, $len, $byte);

                continue;
            }

            if (self::isHeredocStart($args, $pos, $len)) {
                $pos += 3;
                ScanPrimitives::skipHeredoc($args, $pos, $len);

                continue;
            }

            if ($byte === '(' || $byte === '[' || $byte === '{') {
                $depth++;
                $pos++;

                continue;
            }

            if ($byte === ')' || $byte === ']' || $byte === '}') {
                $depth--;
                $pos++;

                continue;
            }

            if ($byte === ',' && $depth === 0) {
                break;
            }

            $pos++;
        }

        $end = self::rtrimWhitespace($args, $start, $pos);

        if ($end <= $start) {
            return null;
        }

        return substr($args, $start, $end - $start);
    }

    private static function skipWhitespace(string $str, int $len, int &$pos): void
    {
        while ($pos < $len && self::isWhitespace($str[$pos])) {
            $pos++;
        }
    }

    private static function rtrimWhitespace(string $str, int $start, int $pos): int
    {
        $end = $pos;
        while ($end > $start && self::isWhitespace($str[$end - 1])) {
            $end--;
        }

        return $end;
    }

    private static function isWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }

    private static function isHeredocStart(string $args, int $pos, int $len): bool
    {
        return $pos + 2 < $len && $args[$pos] === '<' && $args[$pos + 1] === '<' && $args[$pos + 2] === '<';
    }
}
