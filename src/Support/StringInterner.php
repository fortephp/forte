<?php

declare(strict_types=1);

namespace Forte\Support;

class StringInterner
{
    /**
     * @var array<string, string>
     */
    private static array $pool = [];

    /**
     * @var array<string, string>
     */
    private static array $lowerCache = [];

    /**
     * Intern a string, returning a canonical instance.
     */
    public static function intern(string $s): string
    {
        return self::$pool[$s] ??= $s;
    }

    /**
     * Intern a lowercased version of the string.
     */
    public static function lower(string $s): string
    {
        if (isset(self::$lowerCache[$s])) {
            return self::$lowerCache[$s];
        }

        $lower = strtolower($s);
        $interned = self::$pool[$lower] ??= $lower;

        return self::$lowerCache[$s] = $interned;
    }

    public static function clear(): void
    {
        self::$pool = [];
        self::$lowerCache = [];
    }
}
