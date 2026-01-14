<?php

declare(strict_types=1);

namespace Forte\Ast\Components;

use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Components\ComponentMetadata;
use Illuminate\Support\LazyCollection;

/**
 * @property-read LazyCollection<int, SlotNode> $slots
 * @property-read LazyCollection<int, SlotNode> $namedSlots
 * @property-read LazyCollection<int, Node> $defaultSlot
 * @property-read string $componentName
 * @property-read string|null $slotName
 */
class ComponentNode extends ElementNode
{
    private ?ComponentMetadata $cachedMetadata = null;

    /**
     * Get the component metadata.
     */
    protected function getMetadata(): ComponentMetadata
    {
        if ($this->cachedMetadata !== null) {
            return $this->cachedMetadata;
        }

        $tagName = $this->tagNameText();
        $manager = $this->document->getComponentManager();
        $this->cachedMetadata = $manager->parseComponentName($tagName);

        if ($this->cachedMetadata === null) {
            $this->cachedMetadata = new ComponentMetadata(
                type: 'blade',
                prefix: 'x-',
                componentName: $tagName,
                nameParts: [$tagName],
                isSlot: false,
                slotName: null
            );
        }

        return $this->cachedMetadata;
    }

    /**
     * Get the component type (e.g., 'blade', 'livewire', 'flux').
     */
    public function getType(): string
    {
        return $this->getMetadata()->type;
    }

    /**
     * Get the component prefix (e.g., 'x-', 'livewire:', 'flux:').
     */
    public function getPrefix(): string
    {
        return $this->getMetadata()->prefix;
    }

    /**
     * Get the component name, without a prefix.
     *
     * For 'x-admin.alert', returns 'admin.alert'.
     * For 'x-slot:title', returns 'slot:title'.
     */
    public function getComponentName(): string
    {
        return $this->getMetadata()->componentName;
    }

    /**
     * Get the component name parts.
     *
     * For 'x-admin.card.header', returns ['admin', 'card', 'header'].
     *
     * @return array<string>
     */
    public function getNameParts(): array
    {
        return $this->getMetadata()->nameParts;
    }

    /**
     * Check if this is a slot component.
     */
    public function isSlot(): bool
    {
        return $this->getMetadata()->isSlot;
    }

    /**
     * Get the slot name for named slots (e.g., 'title' from 'x-slot:title').
     */
    public function getSlotName(): ?string
    {
        return $this->getMetadata()->slotName;
    }

    /**
     * Get all slot components.
     *
     * @return iterable<int, SlotNode>
     */
    public function slots(): iterable
    {
        foreach ($this->children() as $child) {
            if ($child instanceof SlotNode) {
                yield $child;
            }
        }
    }

    /**
     * @return array<int, SlotNode>
     */
    public function getSlots(): array
    {
        return iterator_to_array($this->slots());
    }

    /**
     * Get all named slot components.
     *
     * @return iterable<int, SlotNode>
     */
    public function namedSlots(): iterable
    {
        foreach ($this->slots() as $slot) {
            if ($slot->getSlotName() !== null) {
                yield $slot;
            }
        }
    }

    /**
     * Get all named slots as an array.
     *
     * @return array<int, SlotNode>
     */
    public function getNamedSlots(): array
    {
        return iterator_to_array($this->namedSlots());
    }

    /**
     * Get all child nodes that are not slots.
     *
     * @return iterable<int, Node>
     */
    public function defaultSlot(): iterable
    {
        foreach ($this->children() as $child) {
            if ($child instanceof SlotNode) {
                continue;
            }

            yield $child;
        }
    }

    /**
     * Get the default slot content as an array.
     *
     * @return array<int, Node>
     */
    public function getDefaultSlot(): array
    {
        return iterator_to_array($this->defaultSlot());
    }

    /**
     * Get the first slot component with the specified name.
     *
     * @param  string  $name  The slot name to search for
     */
    public function slot(string $name): ?SlotNode
    {
        foreach ($this->slots() as $slot) {
            if ($slot->getSlotName() === $name) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Get all slot components with the specified base name.
     *
     * @param  string  $name  The base slot name to search for
     * @return iterable<int, SlotNode>
     */
    public function slotsNamed(string $name): iterable
    {
        foreach ($this->slots() as $slot) {
            if ($slot instanceof SlotNode && $slot->baseSlotName() === $name) {
                yield $slot;
            }
        }
    }

    /**
     * Get all slots with the specified base name as an array.
     *
     * @param  string  $name  The base slot name to search for
     * @return array<SlotNode>
     */
    public function getSlotsNamed(string $name): array
    {
        return iterator_to_array($this->slotsNamed($name));
    }

    /**
     * Check if this component has multiple slots with the same base name.
     *
     * @param  string  $name  The base slot name to check
     */
    public function hasMultipleSlotsNamed(string $name): bool
    {
        $count = 0;
        foreach ($this->slotsNamed($name) as $_) {
            if (++$count > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all default slot children as a NodeCollection.
     *
     * @return NodeCollection<int, Node>
     */
    public function defaultSlotChildren(): NodeCollection
    {
        return new NodeCollection($this->getDefaultSlot());
    }

    /**
     * Get the rendered content of the default slot.
     */
    public function defaultSlotContent(): string
    {
        $content = '';

        foreach ($this->defaultSlot() as $node) {
            $content .= $node->render();
        }

        return $content;
    }

    /**
     * Check if this component has a slot with the specified name.
     *
     * @param  string  $name  The slot name to check
     */
    public function hasSlot(string $name): bool
    {
        return $this->slot($name) !== null;
    }

    /**
     * Check if this component has any default slot content (non-slot children).
     */
    public function hasDefaultSlot(): bool
    {
        foreach ($this->defaultSlot() as $_) {
            return true;
        }

        return false;
    }

    /**
     * Check if this component has any slots.
     */
    public function hasSlots(): bool
    {
        foreach ($this->slots() as $_) {
            return true;
        }

        return false;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            // @phpstan-ignore-next-line
            'slots' => LazyCollection::make(fn () => $this->slots()),
            // @phpstan-ignore-next-line
            'namedSlots' => LazyCollection::make(fn () => $this->namedSlots()),
            // @phpstan-ignore-next-line
            'defaultSlot' => LazyCollection::make(fn () => $this->defaultSlot()),
            'componentName' => $this->getComponentName(),
            'slotName' => $this->getSlotName(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        $metadata = $this->getMetadata();

        $data['type'] = 'component';

        $data['component_type'] = $metadata->type;
        $data['component_prefix'] = $metadata->prefix;
        $data['component_name'] = $metadata->componentName;
        $data['component_name_parts'] = $metadata->nameParts;
        $data['qualified_name'] = $this->getComponentName();
        $data['is_slot_component'] = $metadata->isSlot;
        $data['slot_name'] = $metadata->slotName;
        $data['has_slots'] = $this->hasSlots();
        $data['has_default_slot'] = $this->hasDefaultSlot();
        $data['slots_summary'] = array_map(
            fn (SlotNode $slot) => [
                'name' => $slot->getSlotName(),
                'is_dynamic' => $slot->isDynamic(),
                'is_multi_value' => $slot->isMultiValue(),
            ],
            $this->getSlots()
        );

        return $data;
    }
}
