<?php

declare(strict_types=1);

namespace Forte\Support;

final class HtmlCharacterReferences
{
    /**
     * Named references which HTML permits without a trailing semicolon.
     *
     * @var array<string, true>
     */
    private const LEGACY_NAMES = [
        'AElig' => true, 'AMP' => true, 'Aacute' => true, 'Acirc' => true, 'Agrave' => true,
        'Aring' => true, 'Atilde' => true, 'Auml' => true, 'COPY' => true, 'Ccedil' => true,
        'ETH' => true, 'Eacute' => true, 'Ecirc' => true, 'Egrave' => true, 'Euml' => true,
        'GT' => true, 'Iacute' => true, 'Icirc' => true, 'Igrave' => true, 'Iuml' => true,
        'LT' => true, 'Ntilde' => true, 'Oacute' => true, 'Ocirc' => true, 'Ograve' => true,
        'Oslash' => true, 'Otilde' => true, 'Ouml' => true, 'QUOT' => true, 'REG' => true,
        'THORN' => true, 'Uacute' => true, 'Ucirc' => true, 'Ugrave' => true, 'Uuml' => true,
        'Yacute' => true, 'aacute' => true, 'acirc' => true, 'acute' => true, 'aelig' => true,
        'agrave' => true, 'amp' => true, 'aring' => true, 'atilde' => true, 'auml' => true,
        'brvbar' => true, 'ccedil' => true, 'cedil' => true, 'cent' => true, 'copy' => true,
        'curren' => true, 'deg' => true, 'divide' => true, 'eacute' => true, 'ecirc' => true,
        'egrave' => true, 'eth' => true, 'euml' => true, 'frac12' => true, 'frac14' => true,
        'frac34' => true, 'gt' => true, 'iacute' => true, 'icirc' => true, 'iexcl' => true,
        'igrave' => true, 'iquest' => true, 'iuml' => true, 'laquo' => true, 'lt' => true,
        'macr' => true, 'micro' => true, 'middot' => true, 'nbsp' => true, 'not' => true,
        'ntilde' => true, 'oacute' => true, 'ocirc' => true, 'ograve' => true, 'ordf' => true,
        'ordm' => true, 'oslash' => true, 'otilde' => true, 'ouml' => true, 'para' => true,
        'plusmn' => true, 'pound' => true, 'quot' => true, 'raquo' => true, 'reg' => true,
        'sect' => true, 'shy' => true, 'sup1' => true, 'sup2' => true, 'sup3' => true,
        'szlig' => true, 'thorn' => true, 'times' => true, 'uacute' => true, 'ucirc' => true,
        'ugrave' => true, 'uml' => true, 'uuml' => true, 'yacute' => true, 'yen' => true,
        'yuml' => true,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1252_REPLACEMENTS = [
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
        0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
        0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
        0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
        0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
        0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    ];

    public static function decodeText(string $value): string
    {
        return self::decode($value, false);
    }

    public static function decodeAttribute(string $value): string
    {
        return self::decode($value, true);
    }

    private static function decode(string $value, bool $attributeContext): string
    {
        if (! str_contains($value, '&')) {
            return $value;
        }

        $decoded = preg_replace_callback(
            '/&(?:#[xX][0-9A-Fa-f]+;?|#[0-9]+;?|[A-Za-z][A-Za-z0-9]*;?)/',
            static function (array $match) use ($value, $attributeContext): string {
                $reference = $match[0][0];
                $offset = $match[0][1];

                if (str_starts_with($reference, '&#')) {
                    return self::decodeNumeric($reference);
                }

                return self::decodeNamed($reference, $value, $offset, $attributeContext);
            },
            $value,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );

        return $decoded ?? $value;
    }

    private static function decodeNumeric(string $reference): string
    {
        $digits = rtrim(substr($reference, 2), ';');
        $base = 10;

        if ($digits !== '' && ($digits[0] === 'x' || $digits[0] === 'X')) {
            $base = 16;
            $digits = substr($digits, 1);
        }

        $codepoint = intval($digits, $base);
        if ($codepoint === 0 || $codepoint > 0x10FFFF || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            $codepoint = 0xFFFD;
        } else {
            $codepoint = self::WINDOWS_1252_REPLACEMENTS[$codepoint] ?? $codepoint;
        }

        return self::codepointToUtf8($codepoint);
    }

    private static function decodeNamed(string $reference, string $source, int $offset, bool $attributeContext): string
    {
        $hasSemicolon = str_ends_with($reference, ';');
        $name = substr($reference, 1, $hasSemicolon ? -1 : null);

        if ($hasSemicolon) {
            $decoded = html_entity_decode('&'.$name.';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== '&'.$name.';') {
                return $decoded;
            }
        }

        for ($length = strlen($name); $length > 0; $length--) {
            $legacyName = substr($name, 0, $length);
            if (! isset(self::LEGACY_NAMES[$legacyName])) {
                continue;
            }

            $next = $name[$length] ?? $source[$offset + 1 + strlen($name) + ($hasSemicolon ? 1 : 0)] ?? '';
            if ($attributeContext && ($next === '=' || self::isAsciiAlphanumeric($next))) {
                return $reference;
            }

            $decoded = html_entity_decode('&'.$legacyName.';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $remainder = substr($name, $length).($hasSemicolon ? ';' : '');

            return $decoded.$remainder;
        }

        return $reference;
    }

    private static function isAsciiAlphanumeric(string $character): bool
    {
        return $character !== '' && (
            ($character >= '0' && $character <= '9')
            || ($character >= 'A' && $character <= 'Z')
            || ($character >= 'a' && $character <= 'z')
        );
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7FF) {
            return chr(0xC0 | ($codepoint >> 6))
                .chr(0x80 | ($codepoint & 0x3F));
        }

        if ($codepoint <= 0xFFFF) {
            return chr(0xE0 | ($codepoint >> 12))
                .chr(0x80 | (($codepoint >> 6) & 0x3F))
                .chr(0x80 | ($codepoint & 0x3F));
        }

        return chr(0xF0 | ($codepoint >> 18))
            .chr(0x80 | (($codepoint >> 12) & 0x3F))
            .chr(0x80 | (($codepoint >> 6) & 0x3F))
            .chr(0x80 | ($codepoint & 0x3F));
    }
}
