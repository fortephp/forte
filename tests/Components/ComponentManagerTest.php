<?php

declare(strict_types=1);

use Forte\Components\ComponentManager;

describe('Component Manager', function (): void {
    it('includes default component prefixes', function (): void {
        $manager = new ComponentManager;

        expect($manager->getPrefixes())->toBe(['x-', 'livewire:', 'flux:']);
    });

    it('can be created without default prefixes', function (): void {
        $manager = new ComponentManager(withDefaults: false);

        expect($manager->getPrefixes())->toBe([]);
    });

    it('can register custom prefix', function (): void {
        $manager = new ComponentManager(withDefaults: false);
        $manager->register('ui:');

        expect($manager->getPrefixes())->toBe(['ui:']);
    });

    it('does not register duplicate prefixes', function (): void {
        $manager = new ComponentManager(withDefaults: false);
        $manager->register('ui:');
        $manager->register('ui:');
        $manager->register('ui:');

        expect($manager->getPrefixes())->toBe(['ui:']);
    });

    it('detects Blade components with x- prefix', function (): void {
        $manager = new ComponentManager;

        expect($manager->isComponent('x-alert'))->toBeTrue()
            ->and($manager->isComponent('x-button'))->toBeTrue()
            ->and($manager->isComponent('x-admin.card'))->toBeTrue()
            ->and($manager->isComponent('x-slot:title'))->toBeTrue()
            ->and($manager->isComponent('div'))->toBeFalse()
            ->and($manager->isComponent('livewire-counter'))->toBeFalse();
    });

    it('detects Livewire components with livewire: prefix', function (): void {
        $manager = new ComponentManager;

        expect($manager->isComponent('livewire:counter'))->toBeTrue()
            ->and($manager->isComponent('livewire:table'))->toBeTrue()
            ->and($manager->isComponent('livewire-counter'))->toBeFalse();
    });

    it('detects Flux components with flux: prefix', function (): void {
        $manager = new ComponentManager;

        expect($manager->isComponent('flux:modal'))->toBeTrue()
            ->and($manager->isComponent('flux:button'))->toBeTrue()
            ->and($manager->isComponent('flux-modal'))->toBeFalse();
    });

    it('detects custom registered component prefixes', function (): void {
        $manager = new ComponentManager(withDefaults: false);
        $manager->register('ui:');
        $manager->register('admin-');

        expect($manager->isComponent('ui:card'))->toBeTrue()
            ->and($manager->isComponent('admin-button'))->toBeTrue()
            ->and($manager->isComponent('x-alert'))->toBeFalse();
    });

    it('gets matching prefix for component name', function (): void {
        $manager = new ComponentManager;

        expect($manager->getMatchingPrefix('x-alert'))->toBe('x-')
            ->and($manager->getMatchingPrefix('livewire:counter'))->toBe('livewire:')
            ->and($manager->getMatchingPrefix('flux:modal'))->toBe('flux:')
            ->and($manager->getMatchingPrefix('div'))->toBeNull();
    });

    it('parses Blade component metadata', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('x-alert');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('blade')
            ->and($metadata->prefix)->toBe('x-')
            ->and($metadata->componentName)->toBe('alert')
            ->and($metadata->nameParts)->toBe(['alert'])
            ->and($metadata->isSlot)->toBeFalse()
            ->and($metadata->slotName)->toBeNull();
    });

    it('parses namespaced Blade component metadata', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('x-admin.card');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('blade')
            ->and($metadata->componentName)->toBe('admin.card')
            ->and($metadata->nameParts)->toBe(['admin', 'card']);
    });

    it('parses Blade slot component metadata', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('x-slot');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('blade')
            ->and($metadata->componentName)->toBe('slot')
            ->and($metadata->isSlot)->toBeTrue()
            ->and($metadata->slotName)->toBeNull();
    });

    it('parses Blade named slot component metadata', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('x-slot:title');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('blade')
            ->and($metadata->componentName)->toBe('slot:title')
            ->and($metadata->nameParts)->toBe(['slot', 'title'])
            ->and($metadata->isSlot)->toBeTrue()
            ->and($metadata->slotName)->toBe('title');
    });

    it('parses Livewire component metadata', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('livewire:counter');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('livewire')
            ->and($metadata->prefix)->toBe('livewire:')
            ->and($metadata->componentName)->toBe('counter')
            ->and($metadata->nameParts)->toBe(['counter'])
            ->and($metadata->isSlot)->toBeFalse();
    });

    it('parses Flux component metadata', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('flux:modal');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('flux')
            ->and($metadata->prefix)->toBe('flux:')
            ->and($metadata->componentName)->toBe('modal')
            ->and($metadata->nameParts)->toBe(['modal'])
            ->and($metadata->isSlot)->toBeFalse();
    });

    it('returns null for non-component names', function (): void {
        $manager = new ComponentManager;
        $metadata = $manager->parseComponentName('div');

        expect($metadata)->toBeNull();
    });

    it('parses custom component type metadata', function (): void {
        $manager = new ComponentManager(withDefaults: false);
        $manager->register('ui:');
        $metadata = $manager->parseComponentName('ui:button');

        expect($metadata)->not->toBeNull()
            ->and($metadata->type)->toBe('ui')
            ->and($metadata->prefix)->toBe('ui:')
            ->and($metadata->componentName)->toBe('button');
    });
});
