<?php

declare(strict_types=1);

namespace Forte\Support;

/**
 * Utilities for HTML's five ASCII whitespace characters.
 *
 * PHP's generic whitespace functions also recognize characters such as the
 * vertical tab, which HTML deliberately treats as ordinary data.
 */
final class HtmlWhitespace
{
    public const CHARACTERS = "\x09\x0A\x0C\x0D\x20";

    public static function trim(string $value): string
    {
        return trim($value, self::CHARACTERS);
    }

    public static function trimStart(string $value): string
    {
        return ltrim($value, self::CHARACTERS);
    }

    public static function trimEnd(string $value): string
    {
        return rtrim($value, self::CHARACTERS);
    }

    /**
     * Split an HTML space-separated token list.
     *
     * @return list<string>
     */
    public static function split(string $value): array
    {
        $value = self::trim($value);

        if ($value === '') {
            return [];
        }

        return preg_split('/[\x09\x0A\x0C\x0D\x20]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function isOnly(string $value): bool
    {
        return preg_match('/\A[\x09\x0A\x0C\x0D\x20]*\z/D', $value) === 1;
    }

    public static function startsWith(string $value): bool
    {
        return $value !== '' && str_contains(self::CHARACTERS, $value[0]);
    }

    public static function endsWith(string $value): bool
    {
        return $value !== '' && str_contains(self::CHARACTERS, $value[strlen($value) - 1]);
    }
}
