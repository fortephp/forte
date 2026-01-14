<?php

declare(strict_types=1);

namespace Forte\Components;

use Illuminate\Support\Str;

class ComponentManager
{
    /**
     * @var array<string>
     */
    private array $prefixes = [];

    public function __construct(bool $withDefaults = true)
    {
        if ($withDefaults) {
            $this->prefixes = ['x-', 'livewire:', 'flux:'];
        }
    }

    /**
     * Register a new component prefix (e.g. `x-`, `livewire:`)
     */
    public function register(string $prefix): void
    {
        if (in_array($prefix, $this->prefixes, true)) {
            return;
        }

        $this->prefixes[] = $prefix;
    }

    /**
     * Check if the given name refers to a registered component
     */
    public function isComponent(string $name): bool
    {
        return Str::startsWith($name, $this->prefixes);
    }

    /**
     * Get the registered prefix that matches the given component/tag name.
     */
    public function getMatchingPrefix(string $name): ?string
    {
        foreach ($this->prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * Parse a component/tag name into structured metadata
     */
    public function parseComponentName(string $name): ?ComponentMetadata
    {
        $prefix = $this->getMatchingPrefix($name);

        if ($prefix === null) {
            return null;
        }

        $componentName = substr($name, strlen($prefix));

        $type = rtrim($prefix, ':-');
        if ($type === 'x') {
            $type = 'blade';
        }

        $isSlot = str_starts_with($componentName, 'slot');
        $slotName = null;
        $isDynamicSlot = false;
        $isMultiValueSlot = false;
        $baseSlotName = null;

        if ($isSlot) {
            if (str_starts_with($componentName, 'slot:')) {
                $slotName = substr($componentName, 5);
            } elseif (str_starts_with($componentName, 'slot[')) {
                $slotName = $this->extractBracketContent($componentName, 5);
            }

            if ($slotName !== null && $slotName !== '') {
                if (str_starts_with($slotName, '[') && str_ends_with($slotName, ']')) {
                    $isDynamicSlot = true;
                    $baseSlotName = substr($slotName, 1, -1);
                } elseif (str_ends_with($slotName, '[]')) {
                    $isMultiValueSlot = true;
                    $baseSlotName = substr($slotName, 0, -2);
                } else {
                    $baseSlotName = $slotName;
                }
            }
        }

        $nameParts = preg_split('/[.:]/', $componentName);

        return new ComponentMetadata(
            type: $type,
            prefix: $prefix,
            componentName: $componentName,
            nameParts: $nameParts ?: [],
            isSlot: $isSlot,
            slotName: $slotName,
            isDynamicSlot: $isDynamicSlot,
            isMultiValueSlot: $isMultiValueSlot,
            baseSlotName: $baseSlotName,
        );
    }

    /**
     * Extract content from brackets with balanced bracket matching.
     */
    private function extractBracketContent(string $componentName, int $start): ?string
    {
        $len = strlen($componentName);
        $depth = 0;
        $end = $start;

        for ($i = $start; $i < $len; $i++) {
            $char = $componentName[$i];
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
                $depth--;
            }
        }

        if ($end > $start) {
            return substr($componentName, $start, $end - $start);
        }

        return null;
    }

    /**
     * Get the list of registered component prefixes
     *
     * @return array<string>
     */
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }
}
