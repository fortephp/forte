<?php

declare(strict_types=1);

use Forte\Ast\Components\SlotNode;

describe('SlotNode', function (): void {
    describe('basic slot types', function (): void {
        it('creates SlotNode for x-slot', function (): void {
            $doc = $this->parse('<x-card><x-slot>Content</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0])->toBeInstanceOf(SlotNode::class)
                ->and($slots[0]->isDynamic())->toBeFalse()
                ->and($slots[0]->isMultiValue())->toBeFalse()
                ->and($slots[0]->baseSlotName())->toBeNull();
        });

        it('creates SlotNode for x-slot:name', function (): void {
            $doc = $this->parse('<x-card><x-slot:header>Header</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0])->toBeInstanceOf(SlotNode::class)
                ->and($slots[0]->getSlotName())->toBe('header')
                ->and($slots[0]->baseSlotName())->toBe('header')
                ->and($slots[0]->isDynamic())->toBeFalse()
                ->and($slots[0]->isMultiValue())->toBeFalse();
        });
    });

    describe('bracket-based slot names', function (): void {
        it('parses slot with bracket-based name', function (): void {
            $doc = $this->parse('<x-card><x-slot[header]>Header</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->getSlotName())->toBe('header')
                ->and($slots[0]->baseSlotName())->toBe('header')
                ->and($slots[0]->isDynamic())->toBeFalse()
                ->and($slots[0]->isMultiValue())->toBeFalse();
        });

        it('parses slot with numeric bracket name', function (): void {
            $doc = $this->parse('<x-card><x-slot[12]>Content</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->getSlotName())->toBe('12')
                ->and($slots[0]->baseSlotName())->toBe('12');
        });

        it('parses slot with dots in bracket name', function (): void {
            $doc = $this->parse('<x-card><x-slot[user.name]>Content</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->getSlotName())->toBe('user.name')
                ->and($slots[0]->baseSlotName())->toBe('user.name');
        });

        it('parses slot with nested brackets (array access)', function (): void {
            $doc = $this->parse('<x-card><x-slot[items[0]]>Content</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->getSlotName())->toBe('items[0]')
                ->and($slots[0]->baseSlotName())->toBe('items[0]');
        });
    });

    describe('dynamic slots', function (): void {
        it('detects dynamic slot with colon-bracket syntax', function (): void {
            $doc = $this->parse('<x-card><x-slot:[$name]>Content</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->isDynamic())->toBeTrue()
                ->and($slots[0]->isMultiValue())->toBeFalse()
                ->and($slots[0]->dynamicExpression())->toBe('$name')
                ->and($slots[0]->baseSlotName())->toBe('$name');
        });

        it('detects dynamic slot with expression', function (): void {
            $doc = $this->parse('<x-card><x-slot:[items]>Content</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->isDynamic())->toBeTrue()
                ->and($slots[0]->dynamicExpression())->toBe('items')
                ->and($slots[0]->baseSlotName())->toBe('items');
        });

        it('returns null dynamicExpression for non-dynamic slots', function (): void {
            $doc = $this->parse('<x-card><x-slot:header>Header</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0];

            expect($slot->dynamicExpression())->toBeNull();
        });
    });

    describe('variadic slots', function (): void {
        it('detects variadic slot syntax', function (): void {
            $doc = $this->parse('<x-card><x-slot:items[]>Item</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();
            $slots = $card->getSlots();

            expect($slots)->toHaveCount(1)
                ->and($slots[0]->isMultiValue())->toBeTrue()
                ->and($slots[0]->isDynamic())->toBeFalse()
                ->and($slots[0]->baseSlotName())->toBe('items')
                ->and($slots[0]->getSlotName())->toBe('items[]');
        });

        it('handles multiple variadic slots with same base name', function (): void {
            $template = <<<'BLADE'
<x-list>
    <x-slot:items[]>Item 1</x-slot>
    <x-slot:items[]>Item 2</x-slot>
    <x-slot:items[]>Item 3</x-slot>
</x-list>
BLADE;

            $list = $this->parse($template)->firstChild()->asComponent();

            expect($list->hasMultipleSlotsNamed('items'))->toBeTrue();

            $items = $list->getSlotsNamed('items');
            expect($items)->toHaveCount(3);

            foreach ($items as $item) {
                expect($item->isMultiValue())->toBeTrue()
                    ->and($item->baseSlotName())->toBe('items');
            }
        });

        it('slotsNamed returns slots by base name', function (): void {
            $template = <<<'BLADE'
<x-card>
    <x-slot:header>Header</x-slot>
    <x-slot:items[]>Item 1</x-slot>
    <x-slot:items[]>Item 2</x-slot>
    <x-slot:footer>Footer</x-slot>
</x-card>
BLADE;

            $card = $this->parse($template)->firstChild()->asComponent();

            expect($card->getSlotsNamed('items'))->toHaveCount(2)
                ->and($card->getSlotsNamed('header'))->toHaveCount(1)
                ->and($card->getSlotsNamed('footer'))->toHaveCount(1)
                ->and($card->getSlotsNamed('nonexistent'))->toHaveCount(0);
        });

        it('hasMultipleSlotsNamed returns false for single slot', function (): void {
            $doc = $this->parse('<x-card><x-slot:header>Header</x-slot></x-card>');
            $card = $doc->firstChild()->asComponent();

            expect($card->hasMultipleSlotsNamed('header'))->toBeFalse()
                ->and($card->hasMultipleSlotsNamed('nonexistent'))->toBeFalse();
        });
    });

    describe('closing tag matching', function (): void {
        it('bracket slot can close with plain x-slot', function (): void {
            $template = '<x-card><x-slot[header]>Header</x-slot></x-card>';
            $doc = $this->parse($template);

            expect($doc->render())->toBe($template);

            $slot = $doc->firstChild()->asComponent()->getSlots()[0];
            expect($slot->isPaired())->toBeTrue()
                ->and($slot->closingTag()->name())->toBe('x-slot');
        });

        it('dynamic slot can close with plain x-slot', function (): void {
            $template = '<x-card><x-slot:[$name]>Content</x-slot></x-card>';
            $doc = $this->parse($template);

            expect($doc->render())->toBe($template);

            $slot = $doc->firstChild()->asComponent()->getSlots()[0];
            expect($slot->isPaired())->toBeTrue();
        });

        it('variadic slot can close with plain x-slot', function (): void {
            $template = '<x-card><x-slot:items[]>Item</x-slot></x-card>';
            $doc = $this->parse($template);

            expect($doc->render())->toBe($template);

            $slot = $doc->firstChild()->asComponent()->getSlots()[0];
            expect($slot->isPaired())->toBeTrue();
        });
    });

    describe('SlotNode properties', function (): void {
        it('has magic property access', function (): void {
            $doc = $this->parse('<x-card><x-slot:items[]>Item</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0]->asSlot();

            expect($slot->isDynamic())->toBeFalse()
                ->and($slot->isMultiValue())->toBeTrue()
                ->and($slot->baseSlotName())->toBe('items');
        });

        it('jsonSerialize includes slot metadata', function (): void {
            $doc = $this->parse('<x-card><x-slot:items[]>Item</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0];
            $json = $slot->jsonSerialize();

            expect($json['slot_is_dynamic'])->toBeFalse()
                ->and($json['slot_is_multi_value'])->toBeTrue()
                ->and($json['slot_base_name'])->toBe('items');
        });

        it('jsonSerialize includes dynamic expression for dynamic slots', function (): void {
            $doc = $this->parse('<x-card><x-slot:[$name]>Content</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0];
            $json = $slot->jsonSerialize();

            expect($json['slot_is_dynamic'])->toBeTrue()
                ->and($json['slot_dynamic_expression'])->toBe('$name');
        });
    });

    describe('edge cases', function (): void {
        it('handles empty brackets gracefully', function (): void {
            $doc = $this->parse('<x-card><x-slot[]>Content</x-slot></x-card>');

            expect($doc->render())->toBe('<x-card><x-slot[]>Content</x-slot></x-card>');
        });

        it('handles slot with variable in brackets', function (): void {
            $doc = $this->parse('<x-card><x-slot[$variable]>Content</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0];

            expect($slot->getSlotName())->toBe('$variable')
                ->and($slot->isDynamic())->toBeFalse();
        });

        it('handles slot with hyphenated name in brackets', function (): void {
            $doc = $this->parse('<x-card><x-slot[foo-bar]>Content</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0];

            expect($slot->getSlotName())->toBe('foo-bar')
                ->and($slot->baseSlotName())->toBe('foo-bar');
        });

        it('handles slot with underscored name', function (): void {
            $doc = $this->parse('<x-card><x-slot[some_thing]>Content</x-slot></x-card>');
            $slot = $doc->firstChild()->asComponent()->getSlots()[0];

            expect($slot->getSlotName())->toBe('some_thing')
                ->and($slot->baseSlotName())->toBe('some_thing');
        });
    });

    describe('render reconstruction', function (): void {
        it('reconstructs basic x-slot', function (): void {
            $template = '<x-card><x-slot>Default content</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs x-slot:name (colon-based)', function (): void {
            $template = '<x-card><x-slot:header>Header content</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs x-slot[name] (bracket-based)', function (): void {
            $template = '<x-card><x-slot[header]>Header content</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs x-slot[$variable] (variable in brackets)', function (): void {
            $template = '<x-card><x-slot[$variable]>Dynamic content</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs x-slot:[$name] (dynamic slot)', function (): void {
            $template = '<x-card><x-slot:[$name]>Dynamic slot content</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs x-slot:[items] (dynamic expression)', function (): void {
            $template = '<x-card><x-slot:[items]>Expression content</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs x-slot:items[] (variadic slot)', function (): void {
            $template = '<x-card><x-slot:items[]>Variadic item</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs numeric bracket slot', function (): void {
            $template = '<x-card><x-slot[12]>Numeric slot</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs dot-notation bracket slot', function (): void {
            $template = '<x-card><x-slot[user.name]>Dot notation</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs nested brackets slot (array access)', function (): void {
            $template = '<x-card><x-slot[items[0]]>Nested brackets</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs multiple variadic slots', function (): void {
            $template = <<<'BLADE'
<x-list>
    <x-slot:items[]>Item 1</x-slot>
    <x-slot:items[]>Item 2</x-slot>
    <x-slot:items[]>Item 3</x-slot>
</x-list>
BLADE;
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs mixed slot types', function (): void {
            $template = <<<'BLADE'
<x-card>
    <x-slot:header>Header</x-slot>
    <x-slot[sidebar]>Sidebar</x-slot>
    <x-slot:items[]>Item 1</x-slot>
    <x-slot:items[]>Item 2</x-slot>
    <x-slot:[$dynamic]>Dynamic</x-slot>
    <x-slot:footer>Footer</x-slot>
</x-card>
BLADE;
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs slots with attributes', function (): void {
            $template = '<x-card><x-slot:header class="font-bold" :id="$headerId">Header</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs self-closing slot', function (): void {
            $template = '<x-card><x-slot:header /></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs slot with nested components', function (): void {
            $template = '<x-card><x-slot:content><x-button>Click me</x-button></x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs slot with Blade directives', function (): void {
            $template = '<x-card><x-slot:content>@if($show)Content@endif</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs slot with Blade echo', function (): void {
            $template = '<x-card><x-slot:content>{{ $content }}</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs hyphenated bracket slot', function (): void {
            $template = '<x-card><x-slot[foo-bar]>Hyphenated</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs underscored bracket slot', function (): void {
            $template = '<x-card><x-slot[some_thing]>Underscored</x-slot></x-card>';
            expect($this->parse($template)->render())->toBe($template);
        });

        it('reconstructs complex nested slot content', function (): void {
            $template = <<<'BLADE'
<x-modal>
    <x-slot:title>
        <h1>{{ $title }}</h1>
    </x-slot>
    <x-slot:body>
        <div class="content">
            @foreach($items as $item)
                <x-item :data="$item" />
            @endforeach
        </div>
    </x-slot>
    <x-slot:footer>
        <x-button @click="close">Close</x-button>
    </x-slot>
</x-modal>
BLADE;
            expect($this->parse($template)->render())->toBe($template);
        });
    });
});
