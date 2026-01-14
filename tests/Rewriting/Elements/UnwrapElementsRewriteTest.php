<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\UnwrapElements;

describe('Element UnwrapElements', function (): void {
    it('unwraps matching elements preserving children', function (): void {
        $doc = $this->parse('<div class="wrapper"><p>content</p></div>');

        $result = $doc->apply(new UnwrapElements('div'))->render();

        expect($result)->toBe('<p>content</p>');
    });

    it('unwraps nested elements', function (): void {
        $doc = $this->parse('<span><strong>bold</strong></span>');

        $result = $doc->apply(new UnwrapElements('span'))->render();

        expect($result)->toBe('<strong>bold</strong>');
    });
});
