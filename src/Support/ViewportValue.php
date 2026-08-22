<?php

declare(strict_types=1);

namespace Forte\Support;

final class ViewportValue
{
    public static function trimWhitespace(string $value): string
    {
        return preg_replace('/^[\x09\x0A\x0D\x20]+|[\x09\x0A\x0D\x20]+$/', '', $value) ?? $value;
    }

    public static function numberPrefix(string $value): ?float
    {
        if (preg_match('/^[\x09\x0A\x0D\x20]*([+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)/', $value, $matches) !== 1) {
            return null;
        }

        return (float) $matches[1];
    }
}
