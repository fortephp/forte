<?php

declare(strict_types=1);

namespace Forte\Support;

final class LanguageTag
{
    /** @var array<string, true> */
    private const GRANDFATHERED = [
        'art-lojban' => true,
        'cel-gaulish' => true,
        'en-gb-oed' => true,
        'i-ami' => true,
        'i-bnn' => true,
        'i-default' => true,
        'i-enochian' => true,
        'i-hak' => true,
        'i-klingon' => true,
        'i-lux' => true,
        'i-mingo' => true,
        'i-navajo' => true,
        'i-pwn' => true,
        'i-tao' => true,
        'i-tay' => true,
        'i-tsu' => true,
        'no-bok' => true,
        'no-nyn' => true,
        'sgn-be-fr' => true,
        'sgn-be-nl' => true,
        'sgn-ch-de' => true,
        'zh-guoyu' => true,
        'zh-hakka' => true,
        'zh-min' => true,
        'zh-min-nan' => true,
        'zh-xiang' => true,
    ];

    public static function isWellFormed(string $value): bool
    {
        $lower = strtolower($value);
        if (isset(self::GRANDFATHERED[$lower])) {
            return true;
        }

        if (preg_match(
            '/\A(?:
                (?:
                    [a-z]{2,3}(?:-[a-z]{3}){0,3}
                    | [a-z]{4}
                    | [a-z]{5,8}
                )
                (?:-[a-z]{4})?
                (?:-(?:[a-z]{2}|[0-9]{3}))?
                (?:-(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3}))*
                (?:-[0-9a-wy-z](?:-[a-z0-9]{2,8})+)*
                (?:-x(?:-[a-z0-9]{1,8})+)?
                | x(?:-[a-z0-9]{1,8})+
            )\z/ixD',
            $value,
        ) !== 1) {
            return false;
        }

        return self::hasUniqueVariantsAndExtensionSingletons($lower);
    }

    private static function hasUniqueVariantsAndExtensionSingletons(string $tag): bool
    {
        $subtags = explode('-', $tag);
        $seenVariants = [];
        $seenSingletons = [];
        $insideExtension = false;

        foreach (array_slice($subtags, 1) as $subtag) {
            if ($subtag === 'x') {
                break;
            }

            if (strlen($subtag) === 1) {
                if (isset($seenSingletons[$subtag])) {
                    return false;
                }

                $seenSingletons[$subtag] = true;
                $insideExtension = true;

                continue;
            }

            if ($insideExtension || preg_match('/\A(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3})\z/iD', $subtag) !== 1) {
                continue;
            }

            if (isset($seenVariants[$subtag])) {
                return false;
            }

            $seenVariants[$subtag] = true;
        }

        return true;
    }
}
