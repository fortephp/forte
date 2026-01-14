<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\RemoveClass;

describe('Element RemoveClass', function (): void {
    it('removes class from matching elements', function (): void {
        $doc = $this->parse('<div class="old new">content</div>');

        $result = $doc->apply(new RemoveClass('div', 'old'))->render();

        expect($result)->toBe('<div class="new">content</div>');
    });

    it('removes class attribute when last class removed', function (): void {
        $doc = $this->parse('<div class="only">content</div>');

        $result = $doc->apply(new RemoveClass('div', 'only'))->render();

        expect($result)->toBe('<div>content</div>');
    });
});
