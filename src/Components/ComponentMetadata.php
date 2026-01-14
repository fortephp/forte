<?php

declare(strict_types=1);

namespace Forte\Components;

readonly class ComponentMetadata
{
    /**
     * @param  array<string>  $nameParts
     */
    public function __construct(
        public string $type,
        public string $prefix,
        public string $componentName,
        public array $nameParts,
        public bool $isSlot,
        public ?string $slotName,
        public bool $isDynamicSlot = false,
        public bool $isMultiValueSlot = false,
        public ?string $baseSlotName = null,
    ) {}
}
