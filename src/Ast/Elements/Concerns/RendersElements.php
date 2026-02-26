<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

trait RendersElements
{
    /**
     * Render the element with custom children content substituted.
     *
     * Preserves the source-faithful opening and closing tags,
     * but replaces children with the given string.
     */
    public function renderWithChildren(string $childContent): string
    {
        if ($this->isSelfClosing() || $this->isVoid()) {
            return $this->render();
        }

        return $this->renderOpeningTag().$childContent.$this->renderClosingTag();
    }

    /**
     * Render the opening tag, including attributes.
     */
    public function renderOpeningTag(): string
    {
        if ($this->isSelfClosing()) {
            return $this->render();
        }

        $meta = $this->document->getSyntheticMeta($this->index);
        if ($meta !== null && isset($meta['tagName'])) {
            return $this->renderSyntheticOpeningTag($meta);
        }

        $startOffset = $this->startOffset();

        if ($startOffset >= 0) {
            return $this->document->getSourceSlice(
                $startOffset,
                $this->document->findOpeningTagEndPosition($this->index)
            );
        }

        $tagName = $this->tagNameText();
        $result = '<'.$tagName;

        $attrContent = $this->getAttributeSourceContent();
        $result .= $attrContent;

        if (! str_ends_with((string) $attrContent, '>')) {
            $result .= '>';
        }

        return $result;
    }

    /**
     * Render the closing tag (e.g., `</div>`).
     *
     * Returns an empty string for self-closing, void, or unpaired elements.
     */
    public function renderClosingTag(): string
    {
        if ($this->isSelfClosing() || $this->isVoid() || ! $this->isPaired()) {
            return '';
        }

        $closingTag = $this->closingTag();

        if ($closingTag !== null) {
            return $closingTag->renderClosingTag();
        }

        return '</'.$this->tagNameText().'>';
    }

    /**
     * Render by composing children, preserving element structure.
     */
    protected function renderComposed(): string
    {
        $meta = $this->document->getSyntheticMeta($this->index);
        if ($meta !== null && isset($meta['tagName'])) {
            return $this->renderSyntheticElement($meta);
        }

        $result = '';

        $tagName = $this->tagNameText();
        $result .= '<'.$tagName;

        $attrContent = $this->getAttributeSourceContent();
        $result .= $attrContent;

        if ($this->isSelfClosing()) {
            if (! str_ends_with((string) $attrContent, '/>')) {
                $result .= ' />';
            }

            return $result;
        }

        if (! str_ends_with((string) $attrContent, '>')) {
            $result .= '>';
        }

        foreach ($this->children() as $child) {
            $result .= $child->render();
        }

        if ($this->isPaired()) {
            $closingTag = $this->closingTag();
            $closingName = $closingTag?->name() ?? $tagName;
            $result .= '</'.$closingName.'>';
        }

        return $result;
    }

    /**
     * Render a synthetic element using metadata.
     *
     * @param  array<string, mixed>  $meta  The synthetic metadata
     */
    private function renderSyntheticElement(array $meta): string
    {
        $tagName = isset($meta['tagName']) && is_string($meta['tagName']) ? $meta['tagName'] : '';
        /** @var list<array{0: string, 1: string|true}> $attributes */
        $attributes = $meta['attributes'] ?? [];
        $selfClosing = (bool) ($meta['selfClosing'] ?? false);
        $void = (bool) ($meta['void'] ?? false);

        $result = '<'.$tagName;

        foreach ($attributes as [$name, $value]) {
            if ($value === true) {
                $result .= ' '.$name;
            } else {
                $result .= ' '.$name.'="'.$value.'"';
            }
        }

        if ($selfClosing) {
            return $result.' />';
        }

        $result .= '>';

        if ($void) {
            return $result;
        }

        foreach ($this->children() as $child) {
            $result .= $child->render();
        }

        return $result.('</'.$tagName.'>');
    }

    /**
     * Render synthetic opening tag from metadata.
     *
     * @param  array<string, mixed>  $meta
     */
    private function renderSyntheticOpeningTag(array $meta): string
    {
        $tagName = isset($meta['tagName']) && is_string($meta['tagName']) ? $meta['tagName'] : '';
        /** @var list<array{0: string, 1: string|true}> $attributes */
        $attributes = $meta['attributes'] ?? [];
        $selfClosing = (bool) ($meta['selfClosing'] ?? false);

        $result = '<'.$tagName;

        foreach ($attributes as [$name, $value]) {
            if ($value === true) {
                $result .= ' '.$name;
            } else {
                $result .= ' '.$name.'="'.$value.'"';
            }
        }

        if ($selfClosing) {
            return $result.' />';
        }

        return $result.'>';
    }

    /**
     * Get the attribute source content.
     */
    private function getAttributeSourceContent(): string
    {
        $flat = $this->flat();
        $source = $this->document->source();

        $tokenStart = $flat['tokenStart'];
        if ($tokenStart < 0) {
            // Synthetic element
            return '';
        }

        $tagNameEndOffset = $this->findTagNameEndOffset();
        $openingTagEndPos = $this->findOpeningTagEndPosition();

        $closeOffset = $openingTagEndPos - 1;
        while ($closeOffset > $tagNameEndOffset && $source[$closeOffset - 1] === '/') {
            $closeOffset--;
        }

        if ($closeOffset <= $tagNameEndOffset) {
            return '';
        }

        return substr((string) $source, $tagNameEndOffset, $closeOffset - $tagNameEndOffset);
    }
}
