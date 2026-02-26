<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Elements\RenameTag;

describe('Rename Element', function (): void {
    it('renames elements matching exact tag name', function (): void {
        $doc = $this->parse('<div>content</div>');

        $result = $doc->apply(new RenameTag('div', 'section'))->render();

        expect($result)->toBe('<section>content</section>');
    });

    it('renames elements matching wildcard pattern', function (): void {
        $doc = $this->parse('<h1>title</h1><h2>subtitle</h2><p>text</p>');

        $result = $doc->apply(new RenameTag('h*', 'span'))->render();

        expect($result)->toBe('<span>title</span><span>subtitle</span><p>text</p>');
    });

    it('uses callback to generate new name', function (): void {
        $doc = $this->parse('<old-name>content</old-name>');

        $result = $doc->apply(new RenameTag('old-*', fn ($tag) => str_replace('old-', 'new-', $tag)))->render();

        expect($result)->toBe('<new-name>content</new-name>');
    });

    it('preserves bound and escaped attributes when renaming', function (): void {
        $doc = $this->parse('<div :class="$cls" ::style="raw" disabled>content</div>');

        $result = $doc->apply(new RenameTag('div', 'section'))->render();

        expect($result)->toContain('<section')
            ->and($result)->toContain(':class="$cls"')
            ->and($result)->toContain('::style="raw"')
            ->and($result)->toContain('disabled')
            ->and($result)->toContain('</section>');
    });

    it('preserves attributes on void element rename', function (): void {
        $doc = $this->parse('<input :value="$val" type="text">');

        $result = $doc->apply(new RenameTag('input', 'custom-input'))->render();

        expect($result)->toContain('<custom-input')
            ->and($result)->toContain(':value="$val"')
            ->and($result)->toContain('type="text"');
    });
});
