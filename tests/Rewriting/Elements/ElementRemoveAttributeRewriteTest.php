<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\RemoveAttributes;

describe('Element RemoveAttributes', function (): void {
    it('removes attributes matching exact name', function (): void {
        $doc = $this->parse('<div data-test="value" class="keep">content</div>');

        $result = $doc->apply(new RemoveAttributes('div', ['data-test']))->render();

        expect($result)->not->toContain('data-test')
            ->and($result)->toContain('class="keep"');
    });

    it('removes attributes matching wildcard pattern', function (): void {
        $doc = $this->parse('<div data-test-id="1" data-test-name="foo" class="keep">content</div>');

        $result = $doc->apply(new RemoveAttributes('div', ['data-test-*']))->render();

        expect($result)->not->toContain('data-test-id')
            ->and($result)->not->toContain('data-test-name')
            ->and($result)->toContain('class="keep"');
    });

    it('removes multiple attribute patterns', function (): void {
        $doc = $this->parse('<div onclick="bad()" onmouseover="bad()" id="keep">content</div>');

        $result = $doc->apply(new RemoveAttributes('div', ['onclick', 'onmouseover']))->render();

        expect($result)->not->toContain('onclick')
            ->and($result)->not->toContain('onmouseover')
            ->and($result)->toContain('id="keep"');
    });

    it('preserves bound and escaped sibling attributes', function (): void {
        $doc = $this->parse('<div data-x="1" :class="$cls" ::style="raw">content</div>');

        $result = $doc->apply(new RemoveAttributes('div', ['data-*']))->render();

        expect($result)->not->toContain('data-x')
            ->and($result)->toContain(':class="$cls"')
            ->and($result)->toContain('::style="raw"');
    });
});
