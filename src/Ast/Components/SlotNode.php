<?php

declare(strict_types=1);

namespace Forte\Ast\Components;

class SlotNode extends ComponentNode
{
    /**
     * Check if this is a dynamic slot.
     *
     * Examples: <x-slot:[items]>, <x-slot:[$name]>
     */
    public function isDynamic(): bool
    {
        return $this->getMetadata()->isDynamicSlot;
    }

    /**
     * Check if this is a multi-value/collection slot.
     *
     * Examples: <x-slot:items[]>, <x-slot:actions[]>
     */
    public function isMultiValue(): bool
    {
        return $this->getMetadata()->isMultiValueSlot;
    }

    /**
     * Get the slot's base slot name.
     *
     * For <x-slot:items[]>, returns "items"
     * For <x-slot:header>, returns "header"
     * For <x-slot:[items]>, returns "items" (the expression)
     */
    public function baseSlotName(): ?string
    {
        return $this->getMetadata()->baseSlotName;
    }

    /**
     * Get the slot's dynamic expression.
     *
     * For <x-slot:[items]>, returns "items"
     * For <x-slot:[$name]>, returns "$name"
     * Returns null for non-dynamic slots.
     */
    public function dynamicExpression(): ?string
    {
        if (! $this->isDynamic()) {
            return null;
        }

        return $this->getMetadata()->baseSlotName;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'slot';
        $data['slot_is_dynamic'] = $this->isDynamic();
        $data['slot_is_multi_value'] = $this->isMultiValue();
        $data['slot_base_name'] = $this->baseSlotName();
        $data['slot_dynamic_expression'] = $this->dynamicExpression();

        return $data;
    }
}
