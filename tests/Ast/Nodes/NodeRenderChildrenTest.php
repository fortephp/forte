<?php

declare(strict_types=1);

describe('Node renderChildrenOnly()', function (): void {
    describe('with elements', function (): void {
        it('renders only the inner content of an element', function (): void {
            $el = $this->parseElement('<div>Hello World</div>');

            expect($el->renderChildrenOnly())->toBe('Hello World')
                ->and($el->render())->toBe('<div>Hello World</div>');
        });

        it('renders nested elements without parent tags', function (): void {
            $el = $this->parseElement('<div><span>Hello</span> <span>World</span></div>');

            expect($el->renderChildrenOnly())->toBe('<span>Hello</span> <span>World</span>');
        });

        it('returns empty string for self-closing elements', function (): void {
            $el = $this->parseElement('<input type="text" />');

            expect($el->renderChildrenOnly())->toBe('');
        });

        it('returns empty string for void elements', function (): void {
            $el = $this->parseElement('<br>');

            expect($el->renderChildrenOnly())->toBe('');
        });
    });

    describe('with directive blocks', function (): void {
        it('renders children which are the directive nodes', function (): void {
            $doc = $this->parse('@if($show) Content @endif');
            $block = $doc->getBlockDirectives()->first();

            $childContent = $block->renderChildrenOnly();

            expect($childContent)->toContain('@if')
                ->and($childContent)->toContain('@endif');
        });
    });

    describe('with blade components', function (): void {
        it('renders children of component element', function (): void {
            $template = '<x-card><p>Card content</p></x-card>';
            $el = $this->parseElement($template);

            $children = $el->renderChildrenOnly();
            expect($children)->toContain('Card content');
        });

        it('renders nested component content', function (): void {
            $template = '<x-layout>Inner content</x-layout>';
            $doc = $this->parse($template);
            $component = $doc->getChildren()[0];

            $children = $component->renderChildrenOnly();
            expect($children)->toContain('Inner content');
        });
    });

    describe('with mixed content', function (): void {
        it('preserves all child content types', function (): void {
            $template = '<div>Text {{ $var }} <span>element</span></div>';
            $el = $this->parseElement($template);

            $children = $el->renderChildrenOnly();
            expect($children)->toContain('Text')
                ->and($children)->toContain('{{ $var }}')
                ->and($children)->toContain('<span>element</span>');
        });
    });
});
