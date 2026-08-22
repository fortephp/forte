<?php

declare(strict_types=1);

use Forte\Support\AttributeQuoting;
use Forte\Tests\ForteTestCase;

uses(ForteTestCase::class);

describe('attribute quoting', function (): void {
    it('chooses a quote that does not occur in the value', function (): void {
        expect(AttributeQuoting::preferredQuote('btn btn-primary'))->toBe('"')
            ->and(AttributeQuoting::preferredQuote('say "hi"'))->toBe("'")
            ->and(AttributeQuoting::preferredQuote('say "it\'s"'))->toBeNull();
    });

    it('reads existing styles and defaults unquoted attributes', function (): void {
        expect(AttributeQuoting::styleOf($this->parseElement('<div class="x">')->attribute('class')))->toBe('"')
            ->and(AttributeQuoting::styleOf($this->parseElement("<div class='x'>")->attribute('class')))->toBe("'")
            ->and(AttributeQuoting::styleOf($this->parseElement('<div class=x>')->attribute('class')))->toBe('"')
            ->and(AttributeQuoting::styleOf($this->parseElement('<input disabled>')->attribute('disabled')))->toBe('"');
    });

    it('renders a complete attribute in the requested style', function (): void {
        expect(AttributeQuoting::render('class', 'btn', '"'))->toBe('class="btn"')
            ->and(AttributeQuoting::render('rel', 'noreferrer noopener', "'"))->toBe("rel='noreferrer noopener'");
    });
});
