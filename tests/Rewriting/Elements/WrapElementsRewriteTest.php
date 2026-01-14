<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\WrapElements;

describe('Element WrapElements', function (): void {
    it('wraps matching elements', function (): void {
        $doc = $this->parse('<img src="photo.jpg">');

        $result = $doc->apply(new WrapElements('img', 'figure'))->render();

        expect($result)->toBe('<figure><img src="photo.jpg"></figure>');
    });

    it('wraps with attributes', function (): void {
        $doc = $this->parse('<input type="text">');

        $result = $doc->apply(new WrapElements('input', 'div', ['class' => 'form-group']))->render();

        expect($result)->toContain('<div class="form-group">')
            ->and($result)->toContain('</div>');
    });
});
