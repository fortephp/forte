<?php

declare(strict_types=1);

namespace Forte\Parser\OptionalTags;

readonly class OptionalTagHandler
{
    public function __construct(
        private OptionalTagConfig $config
    ) {}

    /**
     * Check if an element is in its valid parent context for optional tag rules.
     */
    public function isInValidParentContext(string $elementName, ?string $parentElement): bool
    {
        $conditions = $this->config->getClosingConditions($elementName);
        if ($conditions === null) {
            return false;
        }

        $hasParentRules = isset($conditions['auto_close_at_parent_end']);
        $hasSiblingRules = isset($conditions['auto_close_when_sibling']);

        // Guard against self-nesting and missing parent where required.
        if ($parentElement === $elementName || $parentElement === null) {
            // When a parent is required, fail fast. When not
            // required, we still need to avoid self-nesting.
            if ($hasParentRules || $parentElement === $elementName) {
                return false;
            }
        }

        // Parent-end rules (e.g., dt/dd/li/option/optgroup/tfoot)
        if ($hasParentRules) {
            $validParents = $conditions['auto_close_at_parent_end'] ?? null;

            return is_array($validParents) && in_array($parentElement, $validParents, true);
        }

        // Sibling-only rules (e.g., <p>), parent must be present and not self
        if ($hasSiblingRules) {
            return $parentElement !== null;
        }

        return false;
    }

    /**
     * Check if an element should auto-close based on the current parsing context.
     */
    public function shouldAutoCloseElement(
        string $elementName,
        ?string $nextElement,
        ?string $parentElement,
        bool $atParentEnd
    ): bool {
        if (! $this->config->canOmitClosingTag($elementName)) {
            return false;
        }

        if ($nextElement !== null && $this->config->shouldAutoCloseOnSibling($elementName, $nextElement)) {
            return true;
        }

        if ($atParentEnd && $this->config->shouldAutoCloseAtParentEnd($elementName, $parentElement)) {
            return true;
        }

        return false;
    }

    /**
     * Get the reason why an element was auto-closed.
     */
    public function getAutoCloseReason(
        string $elementName,
        ?string $nextElement,
        bool $atParentEnd
    ): string {
        if ($nextElement !== null && $this->config->shouldAutoCloseOnSibling($elementName, $nextElement)) {
            return "next_sibling:{$nextElement}";
        }

        if ($atParentEnd) {
            return 'parent_end';
        }

        return 'eof';
    }
}
