<?php

declare(strict_types=1);

use Forte\Support\ViewportValue;

it('trims CSS viewport whitespace without consuming other controls', function (): void {
    expect(ViewportValue::trimWhitespace("\t width=device-width \n"))->toBe('width=device-width')
        ->and(ViewportValue::trimWhitespace("\x0Bwidth=device-width"))->toBe("\x0Bwidth=device-width");
});

it('parses viewport numeric prefixes', function (): void {
    expect(ViewportValue::numberPrefix('  +1.5junk'))->toBe(1.5)
        ->and(ViewportValue::numberPrefix('device-width'))->toBeNull()
        ->and(ViewportValue::numberPrefix("\x0B1"))->toBeNull();
});
