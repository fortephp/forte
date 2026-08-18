<?php

declare(strict_types=1);

namespace Forte\Support;

final class ImageSource
{
    public static function isSvg(string $source): bool
    {
        $source = self::normalizeUrl($source);

        if (preg_match('/\Adata:image\/svg\+xml(?:[;,])/i', $source) === 1) {
            return true;
        }

        $path = parse_url($source, PHP_URL_PATH);

        return is_string($path) && str_ends_with(strtolower($path), '.svg');
    }

    public static function isDataUrl(string $source): bool
    {
        return str_starts_with(strtolower(self::normalizeUrl($source)), 'data:');
    }

    /** Normalize the leading and trailing C0 controls or space stripped by URLs. */
    public static function normalizeUrl(string $source): string
    {
        return preg_replace('/\A[\x00-\x20]+|[\x00-\x20]+\z/', '', $source) ?? $source;
    }
}
