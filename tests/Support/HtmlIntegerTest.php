<?php

declare(strict_types=1);

use Forte\Support\HtmlInteger;

describe('HTML integer parsing', function (): void {
    it('parses signed numeric prefixes after HTML whitespace', function (): void {
        expect(HtmlInteger::parse("\t +12junk"))->toBe(12)
            ->and(HtmlInteger::parse('-7.5'))->toBe(-7)
            ->and(HtmlInteger::parse("\x0B12"))->toBeNull()
            ->and(HtmlInteger::parse('nope'))->toBeNull();
    });
});
