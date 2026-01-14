<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Parser\NodeKind;

trait ProcessesElementAutoClosing
{
    /**
     * Auto-close open elements that should close when a sibling element appears.
     *
     * @param  string  $newElementTag  The new element being opened (lowercase)
     */
    protected function autoCloseElementsForSibling(string $newElementTag): void
    {
        if ($newElementTag === '') {
            return;
        }

        $validParents = $this->validParentsFor($newElementTag);

        if (! empty($validParents)) {
            $containerIndex = $this->findNearestValidContainer($validParents);

            if ($containerIndex >= 0) {
                $this->autoCloseElementsBetween($containerIndex, $newElementTag);

                return;
            }

            // Valid parents were specified but none found: be lenient, allow nesting.
            return;
        }

        // No specific container requirements: only check the immediate parent.
        $this->autoCloseImmediateParent($newElementTag);
    }

    protected function autoCloseElementsBetween(int $containerIndex, string $newElementTag): void
    {
        $closeUpToIndex = -1;
        $openCount = count($this->openElements);

        // Walk downward from the top to (but not including) the container.
        for ($i = $openCount - 1; $i > $containerIndex; $i--) {
            $elementIdx = $this->openElements[$i];

            if (! $this->isElementNode($elementIdx)) {
                continue;
            }

            $tagName = $this->tagNameAt($elementIdx);
            if ($tagName === '') {
                continue;
            }

            $parentTagName = $this->parentTagNameOf($elementIdx);

            if ($this->optionalTagHandler->shouldAutoCloseElement(
                $tagName,
                $newElementTag,
                $parentTagName,
                false
            )) {
                // Keep the deepest element that needs closing.
                $closeUpToIndex = $i;
            }
        }

        if ($closeUpToIndex >= 0) {
            while (count($this->openElements) > $closeUpToIndex) {
                $poppedIdx = array_pop($this->openElements);
                $this->cleanupTagNameStack($poppedIdx);
            }
        }
    }

    protected function autoCloseImmediateParent(string $newElementTag): void
    {
        if (count($this->openElements) <= 1) {
            return;
        }

        $currentIdx = end($this->openElements);
        if (! $this->isElementNode($currentIdx)) {
            return;
        }

        $currentTagName = $this->tagNameAt($currentIdx);
        if ($currentTagName === '') {
            return;
        }

        $parentTagName = $this->parentTagNameOf($currentIdx);

        // If the current element is not in a valid parent context, allow nesting (be lenient).
        if (! $this->optionalTagHandler->isInValidParentContext($currentTagName, $parentTagName)) {
            return;
        }

        if ($this->optionalTagHandler->shouldAutoCloseElement(
            $currentTagName,
            $newElementTag,
            $parentTagName,
            false
        )) {
            array_pop($this->openElements);
            $this->cleanupTagNameStack($currentIdx);
        }
    }

    protected function closeRemainingElements(): void
    {
        $totalTokens = count($this->tokens);

        foreach ($this->openElements as $elementIdx) {
            if ($elementIdx === 0) {
                continue; // Root node
            }

            $node = &$this->nodes[$elementIdx];
            $tokenStart = $node['tokenStart'];
            $node['tokenCount'] = $totalTokens - $tokenStart;
        }
    }

    /**
     * @return list<string>|null
     */
    protected function validParentsFor(string $tag): ?array
    {
        $conditions = $this->optionalTagConfig->getClosingConditions($tag);
        $validParents = $conditions['auto_close_at_parent_end'] ?? null;

        return is_array($validParents) ? array_values($validParents) : null;
    }

    /**
     * @param  list<string>  $validParents
     */
    protected function findNearestValidContainer(array $validParents): int
    {
        for ($i = count($this->openElements) - 1; $i > 0; $i--) {
            $elementIdx = $this->openElements[$i];

            if (! $this->isElementNode($elementIdx)) {
                continue;
            }

            $tagName = $this->tagNameAt($elementIdx);
            if ($tagName === '') {
                continue;
            }

            if (in_array($tagName, $validParents, true)) {
                return $i;
            }
        }

        return -1;
    }

    protected function isElementNode(int $idx): bool
    {
        return isset($this->nodes[$idx]) && $this->nodes[$idx]['kind'] === NodeKind::Element;
    }

    /**
     * Get the tag name for node index, or '' if missing.
     */
    protected function tagNameAt(int $idx): string
    {
        return $this->tagNames[$idx] ?? '';
    }

    /**
     * Get the parent tag name for a node index, or null if none/invalid.
     */
    protected function parentTagNameOf(int $idx): ?string
    {
        $parentIdx = $this->nodes[$idx]['parent'] ?? null;
        if (! is_int($parentIdx) || $parentIdx <= 0 || ! isset($this->nodes[$parentIdx])) {
            return null;
        }

        $parentNode = $this->nodes[$parentIdx];
        if ($parentNode['kind'] !== NodeKind::Element) {
            return null;
        }

        return $this->tagNames[$parentIdx] ?? null;
    }
}
