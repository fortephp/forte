<?php

declare(strict_types=1);

use Forte\Ast\Components\ComponentNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\ElementNode;
use Illuminate\Support\LazyCollection;

describe('Component Node', function (): void {

    dataset('component_types', [
        'blade component' => [
            'template' => '<x-alert />',
            'type' => 'blade',
            'prefix' => 'x-',
            'name' => 'alert',
        ],
        'livewire component' => [
            'template' => '<livewire:counter />',
            'type' => 'livewire',
            'prefix' => 'livewire:',
            'name' => 'counter',
        ],
        'flux component' => [
            'template' => '<flux:modal />',
            'type' => 'flux',
            'prefix' => 'flux:',
            'name' => 'modal',
        ],
    ]);

    test('component type and prefix detection', function (string $template, string $type, string $prefix, string $name): void {
        $component = $this->parse($template)->firstChild()->asComponent();

        expect($component->getType())->toBe($type)
            ->and($component->getPrefix())->toBe($prefix)
            ->and($component->componentName)->toBe($name);
    })->with('component_types');

    dataset('qualified_names', [
        'simple name' => ['<x-alert />', 'alert', ['alert']],
        'dotted name' => ['<x-admin.card />', 'admin.card', ['admin', 'card']],
        'deeply nested' => ['<x-admin.card.header />', 'admin.card.header', ['admin', 'card', 'header']],
    ]);

    test('qualified name and name parts', function (string $template, string $qualifiedName, array $parts): void {
        $component = $this->parse($template)->firstChild()->asComponent();

        expect($component->getComponentName())->toBe($qualifiedName)
            ->and($component->getNameParts())->toBe($parts);
    })->with('qualified_names');

    dataset('slot_detection', [
        'regular component' => ['<x-alert />', false, null],
        'unnamed slot' => ['<x-slot></x-slot>', true, null],
        'named slot' => ['<x-slot:title></x-slot:title>', true, 'title'],
        'named slot header' => ['<x-slot:header></x-slot:header>', true, 'header'],
    ]);

    test('slot detection and naming', function (string $template, bool $isSlot, ?string $slotName): void {
        $component = $this->parse($template)->firstChild()->asComponent();

        expect($component->isSlot())->toBe($isSlot)
            ->and($component->getSlotName())->toBe($slotName);
    })->with('slot_detection');

    test('component extends ElementNode', function (): void {
        $component = $this->parse('<x-alert />')->firstChild();

        expect($component)->toBeInstanceOf(ElementNode::class)
            ->and($component)->toBeInstanceOf(ComponentNode::class);
    });

    test('paired components have closing tag', function (): void {
        $component = $this->parse('<x-card></x-card>')->firstChild()->asComponent();

        expect($component->isPaired())->toBeTrue()
            ->and($component->closingTag()->name())->toBe('x-card');
    });

    test('jsonSerialize includes component metadata', function (): void {
        $component = $this->parse('<x-admin.card />')->firstChild()->asComponent();
        $json = $component->jsonSerialize();

        expect($json)->toBeArray()
            ->and($json['component_type'])->toBe('blade')
            ->and($json['component_prefix'])->toBe('x-')
            ->and($json['component_name'])->toBe('admin.card')
            ->and($json['component_name_parts'])->toBe(['admin', 'card'])
            ->and($json['is_slot_component'])->toBeFalse()
            ->and($json['slot_name'])->toBeNull();
    });

    test('jsonSerialize includes slot metadata for named slots', function (): void {
        $component = $this->parse('<x-slot:header></x-slot:header>')->firstChild()->asComponent();
        $json = $component->jsonSerialize();

        expect($json['is_slot_component'])->toBeTrue()
            ->and($json['slot_name'])->toBe('header');
    });

    test('slots returns all slot components', function (): void {
        $template = <<<'BLADE'
<x-card>
    <x-slot:header>Header</x-slot:header>
    <p>Body</p>
    <x-slot:footer>Footer</x-slot:footer>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $slots = $component->slots;

        expect($slots)->toBeInstanceOf(LazyCollection::class)
            ->and($slots)->toHaveCount(2)
            ->and($slots->first()->getSlotName())->toBe('header')
            ->and($slots->last()->getSlotName())->toBe('footer');
    });

    test('slots is empty for component without slots', function (): void {
        $component = $this->parse('<x-card><p>Just content</p></x-card>')->firstChild()->asComponent();

        expect($component->slots)->toBeEmpty();
    });

    test('namedSlots returns only named slots', function (): void {
        $template = <<<'BLADE'
<x-card>
    <x-slot>Default</x-slot>
    <x-slot:header>Header</x-slot:header>
    <x-slot:footer>Footer</x-slot:footer>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $namedSlots = $component->namedSlots;

        expect($namedSlots)->toBeInstanceOf(LazyCollection::class)
            ->and($namedSlots)->toHaveCount(2)
            ->and($namedSlots->map(fn ($s) => $s->getSlotName())->all())->toBe(['header', 'footer']);
    });

    test('slot method returns first slot with given name', function (): void {
        $template = <<<'BLADE'
<x-card>
    <x-slot:header>Header 1</x-slot:header>
    <x-slot:header>Header 2</x-slot:header>
    <x-slot:footer>Footer</x-slot:footer>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();

        $header = $component->slot('header');
        expect($header)->toBeInstanceOf(ComponentNode::class)
            ->and($header->getSlotName())->toBe('header')
            ->and($header->getChildren()[0]->getDocumentContent())->toContain('Header 1');

        $footer = $component->slot('footer');
        expect($footer)->toBeInstanceOf(ComponentNode::class)
            ->and($footer->getSlotName())->toBe('footer')
            ->and($component->slot('sidebar'))->toBeNull();
    });

    test('hasSlot and hasSlots methods', function (): void {
        $component = $this->parse('<x-card><x-slot:header>Header</x-slot:header></x-card>')
            ->firstChild()->asComponent();

        expect($component->hasSlots())->toBeTrue()
            ->and($component->hasSlot('header'))->toBeTrue()
            ->and($component->hasSlot('footer'))->toBeFalse();
    });

    test('hasSlots returns false when no slots exist', function (): void {
        $component = $this->parse('<x-card><p>Just content</p></x-card>')->firstChild()->asComponent();

        expect($component->hasSlots())->toBeFalse();
    });

    test('slots supports filtering and mapping', function (): void {
        $template = <<<'BLADE'
<x-card>
    <x-slot:header>Header</x-slot:header>
    <x-slot:sidebar>Sidebar</x-slot:sidebar>
    <x-slot:footer>Footer</x-slot:footer>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $slotNames = $component->slots->map(fn ($slot) => $slot->getSlotName())->all();

        expect($slotNames)->toBe(['header', 'sidebar', 'footer']);

        $filteredCount = $component->slots
            ->filter(fn ($slot) => str_contains($slot->getSlotName() ?? '', 'er'))
            ->count();

        expect($filteredCount)->toBe(2);
    });

    test('defaultSlot returns non-slot children', function (): void {
        $template = <<<'BLADE'
<x-card>
    <p>Paragraph 1</p>
    <x-slot:header>Header</x-slot:header>
    <div>Div content</div>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $defaultSlots = $component->defaultSlot;

        expect($defaultSlots)
            ->toBeInstanceOf(LazyCollection::class)
            ->and($defaultSlots->filter(fn ($node) => $node instanceof ElementNode && ! ($node instanceof ComponentNode)))->toHaveCount(2);
    });

    test('defaultSlotChildren returns NodeCollection', function (): void {
        $template = <<<'BLADE'
<x-card>
    <p>Paragraph</p>
    <x-slot:header>Header</x-slot:header>
    <div>Content</div>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $defaultChildren = $component->defaultSlotChildren();

        expect($defaultChildren)->toBeInstanceOf(NodeCollection::class)
            ->and($defaultChildren->elements())->toHaveCount(2);
    });

    test('defaultSlotContent returns rendered HTML excluding slots', function (): void {
        $template = <<<'BLADE'
<x-card>
    <p>Hello</p>
    <x-slot:header>Header</x-slot:header>
    <div>World</div>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $content = $component->defaultSlotContent();

        expect($content)->toContain('<p>Hello</p>')
            ->and($content)->toContain('<div>World</div>')
            ->and($content)->not->toContain('<x-slot:header>');
    });

    dataset('has_default_slot', [
        'with element content' => [
            '<x-card><p>Content</p><x-slot:header>Header</x-slot:header></x-card>',
            true,
        ],
        'only slots no whitespace' => [
            '<x-card><x-slot:header>Header</x-slot:header><x-slot:footer>Footer</x-slot:footer></x-card>',
            false,
        ],
        'slots with whitespace' => [
            "<x-card>\n    <x-slot:header>Header</x-slot:header>\n</x-card>",
            true,
        ],
        'text content before slot' => [
            "<x-card>\n    I am default content\n    <x-slot:header>Header</x-slot:header>\n</x-card>",
            true,
        ],
    ]);

    test('hasDefaultSlot detection', function (string $template, bool $expected): void {
        $component = $this->parse($template)->firstChild()->asComponent();

        expect($component->hasDefaultSlot())->toBe($expected);
    })->with('has_default_slot');

    test('default slot includes content interleaved with slots', function (): void {
        $template = <<<'BLADE'
<x-card>
   Default content
    <x-slot:header>Header</x-slot:header>
    <x-slot:footer>Footer</x-slot:footer>
i am also part of the default content.
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();
        $content = $component->defaultSlotContent();

        expect($content)->toContain('Default content')
            ->and($content)->toContain('i am also part of the default content.')
            ->and($content)->not->toContain('Header')
            ->and($content)->not->toContain('Footer');
    });

    test('component with mixed content and slots', function (): void {
        $template = <<<'BLADE'
<x-card>
    <x-slot:header>Header</x-slot:header>
    <p>Body paragraph</p>
    <div>Body div</div>
    <x-slot:footer>Footer</x-slot:footer>
</x-card>
BLADE;

        $component = $this->parse($template)->firstChild()->asComponent();

        expect($component->hasSlots())->toBeTrue()
            ->and($component->slots)->toHaveCount(2)
            ->and($component->hasSlot('header'))->toBeTrue()
            ->and($component->hasSlot('footer'))->toBeTrue()
            ->and($component->hasDefaultSlot())->toBeTrue();

        $defaultContent = $component->defaultSlotContent();
        expect($defaultContent)->toContain('<p>Body paragraph</p>')
            ->and($defaultContent)->toContain('<div>Body div</div>');
    });

    test('x-slot closing tag matches x-slot:name opening tag', function (): void {
        $template = <<<'BLADE'
<x-alert>
    <x-slot:title>
        Custom Title
    </x-slot>
</x-alert>
BLADE;

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $alert = $doc->firstChild()->asComponent();
        expect($alert)->toBeInstanceOf(ComponentNode::class);

        $alertChildren = $alert->getChildren();
        expect($alertChildren)->toHaveCount(3);

        $slot = $alertChildren[1]->asComponent();
        expect($slot->tagNameText())->toBe('x-slot:title')
            ->and($slot->isPaired())->toBeTrue()
            ->and($slot->closingTag()->name())->toBe('x-slot')
            ->and($slot->hasSyntheticClosing())->toBeFalse();
    });
});
