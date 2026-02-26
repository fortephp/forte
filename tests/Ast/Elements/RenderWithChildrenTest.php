<?php

declare(strict_types=1);

use Forte\Ast\Components\ComponentNode;
use Forte\Parser\ParserOptions;

describe('Element Rendering - Custom Content', function (): void {
    describe('Opening Tag', function (): void {
        it('returns opening tag for simple element', function (): void {
            $el = $this->parseElement('<div>content</div>');

            expect($el->renderOpeningTag())->toBe('<div>');
        });

        it('returns opening tag with attributes', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar">content</div>');

            expect($el->renderOpeningTag())->toBe('<div class="foo" id="bar">');
        });

        it('returns self-closing tag', function (): void {
            $el = $this->parseElement('<br />');

            expect($el->renderOpeningTag())->toBe('<br />');
        });

        it('preserves attribute whitespace', function (): void {
            $el = $this->parseElement('<div   class="foo"  >content</div>');

            expect($el->renderOpeningTag())->toBe('<div   class="foo"  >');
        });

        it('handles blade expressions in attributes', function (): void {
            $el = $this->parseElement('<div class="{{ $class }}">content</div>');

            expect($el->renderOpeningTag())->toBe('<div class="{{ $class }}">');
        });
    });

    describe('Closing Tag', function (): void {
        it('returns closing tag for paired element', function (): void {
            $el = $this->parseElement('<div>content</div>');

            expect($el->renderClosingTag())->toBe('</div>');
        });

        it('returns empty string for self-closing element', function (): void {
            $el = $this->parseElement('<br />');

            expect($el->renderClosingTag())->toBe('');
        });

        it('returns empty string for void element', function (): void {
            $el = $this->parseElement('<input>');

            expect($el->renderClosingTag())->toBe('');
        });
    });

    describe('With Children', function (): void {
        it('substitutes children for paired element', function (): void {
            $el = $this->parseElement('<div>original</div>');

            expect($el->renderWithChildren('replaced'))->toBe('<div>replaced</div>');
        });

        it('preserves attributes when substituting children', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar">original</div>');

            expect($el->renderWithChildren('new content'))->toBe('<div class="foo" id="bar">new content</div>');
        });

        it('returns render() for self-closing elements', function (): void {
            $el = $this->parseElement('<br />');

            expect($el->renderWithChildren('ignored'))->toBe('<br />');
        });

        it('returns render() for void elements', function (): void {
            $el = $this->parseElement('<input type="text">');

            expect($el->renderWithChildren('ignored'))->toBe('<input type="text">');
        });

        it('handles empty replacement content', function (): void {
            $el = $this->parseElement('<div>original content</div>');

            expect($el->renderWithChildren(''))->toBe('<div></div>');
        });

        it('handles nested element replacement', function (): void {
            $el = $this->parseElement('<div><span>inner</span></div>');

            expect($el->renderWithChildren('<p>replaced</p>'))->toBe('<div><p>replaced</p></div>');
        });

        it('preserves blade expressions in attributes', function (): void {
            $el = $this->parseElement('<div :class="$classes" @click="handle">old</div>');

            expect($el->renderWithChildren('new'))->toBe('<div :class="$classes" @click="handle">new</div>');
        });

        it('works on component nodes', function (): void {
            $options = ParserOptions::defaults()->withAllDirectives()->withComponentPrefix('x:');
            $doc = $this->parse('<x-card class="foo">original</x-card>', $options);
            $component = $doc->components->first();

            expect($component)->toBeInstanceOf(ComponentNode::class)
                ->and($component->renderWithChildren('compiled'))->toBe('<x-card class="foo">compiled</x-card>');
        });

        it('works on slot nodes', function (): void {
            $options = ParserOptions::defaults()->withAllDirectives()->withComponentPrefix('x:');
            $doc = $this->parse('<x-card><x-slot:header>old</x-slot:header></x-card>', $options);
            $component = $doc->components->first();
            $slot = $component->getSlots()[0];

            expect($slot->renderWithChildren('new'))->toBe('<x-slot:header>new</x-slot:header>');
        });

        it('round-trips correctly for unmodified content', function (): void {
            $input = '<section class="main" id="content"><p>Hello</p><span>World</span></section>';
            $el = $this->parseElement($input);

            expect($el->renderWithChildren($el->innerContent()))->toBe($input);
        });
    });

});
