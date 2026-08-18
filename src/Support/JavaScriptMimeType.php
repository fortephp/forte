<?php

declare(strict_types=1);

namespace Forte\Support;

final class JavaScriptMimeType
{
    /** @var list<string> */
    private const TYPES = [
        'application/ecmascript',
        'application/javascript',
        'application/x-ecmascript',
        'application/x-javascript',
        'text/ecmascript',
        'text/javascript',
        'text/javascript1.0',
        'text/javascript1.1',
        'text/javascript1.2',
        'text/javascript1.3',
        'text/javascript1.4',
        'text/javascript1.5',
        'text/jscript',
        'text/livescript',
        'text/x-ecmascript',
        'text/x-javascript',
    ];

    public static function isEssenceMatch(string $value): bool
    {
        return in_array(self::normalizeScriptType($value), self::TYPES, true);
    }

    public static function normalizeScriptType(string $value): string
    {
        return strtolower(HtmlWhitespace::trim($value));
    }
}
