<?php

declare(strict_types=1);

use Forte\Support\JavaScriptMimeType;

it('recognizes JavaScript MIME type essence matches', function (): void {
    expect(JavaScriptMimeType::isEssenceMatch("\tText/JavaScript\n"))->toBeTrue()
        ->and(JavaScriptMimeType::isEssenceMatch('application/json'))->toBeFalse();
});

it('trims only HTML whitespace when normalizing script types', function (): void {
    expect(JavaScriptMimeType::normalizeScriptType("\x0Btext/javascript"))
        ->toBe("\x0Btext/javascript");
});
