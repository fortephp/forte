<?php

declare(strict_types=1);

use Forte\Support\LanguageTag;

it('accepts well-formed BCP 47 language tags', function (string $tag): void {
    expect(LanguageTag::isWellFormed($tag))->toBeTrue();
})->with([
    'language' => 'en',
    'language and region' => 'en-US',
    'script and region' => 'zh-Hant-TW',
    'variant' => 'de-CH-1901',
    'multiple variants' => 'sl-rozaj-biske-1994',
    'extension' => 'en-US-u-islamcal',
    'private use suffix' => 'de-CH-x-phonebk',
    'private use tag' => 'x-project',
    'grandfathered tag' => 'i-klingon',
]);

it('rejects malformed BCP 47 language tags', function (string $tag): void {
    expect(LanguageTag::isWellFormed($tag))->toBeFalse();
})->with([
    'empty' => '',
    'underscore' => 'en_US',
    'punctuation' => '!!!',
    'trailing separator' => 'en-',
    'duplicate separator' => 'en--US',
    'one-letter language' => 'e',
    'oversized subtag' => 'en-abcdefghij',
    'duplicate extension singleton' => 'en-u-ca-gregory-u-nu-latn',
    'duplicate numeric variant' => 'de-1901-1901',
    'duplicate alphabetic variant' => 'sl-rozaj-ROZAJ',
    'surrounding whitespace' => ' en-US ',
]);

it('does not apply variant uniqueness to extension or private-use values', function (string $tag): void {
    expect(LanguageTag::isWellFormed($tag))->toBeTrue();
})->with([
    'repeated extension value' => 'en-u-abcde-abcde',
    'repeated private-use value' => 'en-x-project-project',
]);
