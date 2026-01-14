<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\SetAttribute;

describe('Element SetAttribute', function (): void {
    it('sets attribute on matching elements', function (): void {
        $doc = $this->parse('<input type="text">');

        $result = $doc->apply(new SetAttribute('input', 'autocomplete', 'off'))->render();

        expect($result)->toContain('autocomplete="off"');
    });

    it('overwrites existing attribute', function (): void {
        $doc = $this->parse('<div data-id="old">content</div>');

        $result = $doc->apply(new SetAttribute('div', 'data-id', 'new'))->render();

        expect($result)->toBe('<div data-id="new">content</div>');
    });
});
