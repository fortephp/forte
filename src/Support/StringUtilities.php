<?php

declare(strict_types=1);

namespace Forte\Support;

use Illuminate\Support\Str;

class StringUtilities
{
    /**
     * Remove matching outer parentheses from a string.
     */
    public static function unwrapParentheses(string $value): string
    {
        while (Str::startsWith($value, '(') && Str::endsWith($value, ')')) {
            $value = mb_substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Remove matching outer quotes (single or double) from a string.
     */
    public static function unwrapString(string $value): string
    {
        if (Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
            return mb_substr($value, 1, -1);
        }

        if (Str::startsWith($value, "'") && Str::endsWith($value, "'")) {
            return mb_substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Wrap a string in quotes, escaping existing quotes.
     */
    public static function wrapInQuotes(string $value, string $quoteStyle): string
    {
        if (Str::startsWith($value, $quoteStyle) && Str::endsWith($value, $quoteStyle)) {
            return $value;
        }

        $quoteReplace = '_replace:'.Str::uuid();
        $value = str_replace('\\'.$quoteStyle, $quoteReplace, $value);
        $value = str_replace($quoteStyle, '\\'.$quoteStyle, $value);
        $value = str_replace($quoteReplace, '\\'.$quoteStyle, $value);

        return $quoteStyle.$value.$quoteStyle;
    }

    /**
     * Wrap a value in single quotes unless it's a PHP variable.
     */
    public static function wrapInSingleQuotes(string $value): string
    {
        if (Str::startsWith($value, '$')) {
            return $value;
        }

        if (Str::startsWith($value, "'") && Str::endsWith($value, "'")) {
            return $value;
        }

        return "'".$value."'";
    }

    /**
     * Escape single quotes in a string.
     */
    public static function escapeSingleQuotes(string $value): string
    {
        return str_replace('\'', '\\\'', $value);
    }

    /**
     * Normalize line endings to LF.
     */
    public static function normalizeLineEndings(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }
}
