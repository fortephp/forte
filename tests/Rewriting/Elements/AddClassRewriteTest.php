<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\AddClass;

describe('Element AddClass', function (): void {
    it('adds class to matching elements', function (): void {
        $doc = $this->parse('<div>content</div>');

        $result = $doc->apply(new AddClass('div', 'active'))->render();

        expect($result)->toBe('<div class="active">content</div>');
    });

    it('adds class to elements matching wildcard', function (): void {
        $doc = $this->parse('<p>one</p><p>two</p><span>three</span>');

        $result = $doc->apply(new AddClass('p', 'paragraph'))->render();

        expect($result)->toBe('<p class="paragraph">one</p><p class="paragraph">two</p><span>three</span>');
    });

    it('adds class to all elements with * pattern', function (): void {
        $doc = $this->parse('<div>one</div><span>two</span>');

        $result = $doc->apply(new AddClass('*', 'touched'))->render();

        expect($result)->toContain('class="touched"');
    });
});
