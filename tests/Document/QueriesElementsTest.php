<?php

declare(strict_types=1);

use Forte\Ast\Components\ComponentNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\ElementNode;

describe('Document Elements', function (): void {
    it('can get all elements', function (): void {
        $doc = $this->parse('<div></div> <span></span> <x-component />');

        $elements = $doc->getElements();

        expect($elements)->toHaveCount(2)
            ->and($elements)->toBeInstanceOf(NodeCollection::class);
    });

    it('can get all components', function (): void {
        $doc = $this->parse('<div></div> <x-alert /> <x-button></x-button>');

        $components = $doc->getComponents();

        expect($components)->toHaveCount(2)
            ->and($components->first())->toBeInstanceOf(ComponentNode::class);
    });

    it('can find element by tag name', function (): void {
        $doc = $this->parse('<div></div> <span></span>');

        $span = $doc->findElementByName('span');

        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span');
    });

    it('can find all elements by tag name', function (): void {
        $doc = $this->parse('<div></div> <span></span> <div></div>');

        $divs = $doc->findElementsByName('div');

        expect($divs)->toHaveCount(2);
    });

    it('can find component by name', function (): void {
        $doc = $this->parse('<x-alert /> <x-button />');

        $alert = $doc->findComponentByName('x-alert');

        expect($alert)->toBeInstanceOf(ComponentNode::class);
    });

    it('can find all components by name', function (): void {
        $doc = $this->parse('<x-alert /> <x-button /> <x-alert />');

        $alerts = $doc->findComponentsByName('x-alert');

        expect($alerts)->toHaveCount(2);
    });

    it('returns null when element not found', function (): void {
        $doc = $this->parse('<div></div>');

        $span = $doc->findElementByName('span');

        expect($span)->toBeNull();
    });

    it('returns empty collection when no elements match', function (): void {
        $doc = $this->parse('<div></div>');

        $spans = $doc->findElementsByName('span');

        expect($spans)->toHaveCount(0);
    });

    it('can find elements with wildcards', function (): void {
        $doc = $this->parse('<div-1></div-1> <div-2></div-2> <span></span>');

        $divs = $doc->findElementsByName('div-*');

        expect($divs)->toHaveCount(2);
    });
});
