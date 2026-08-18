<?php

declare(strict_types=1);

namespace Forte\Support;

/** Parse an HTML integer from the longest valid numeric prefix. */
final class HtmlInteger
{
    public static function parse(string $value): ?int
    {
        if (preg_match('/\A[\x09\x0A\x0C\x0D\x20]*([+-]?)([0-9]+)/', $value, $matches) !== 1) {
            return null;
        }

        return (int) ($matches[1].$matches[2]);
    }
}
