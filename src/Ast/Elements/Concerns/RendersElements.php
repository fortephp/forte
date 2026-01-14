<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

trait RendersElements
{
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
        /** @var array<string, string|true> $attributes */
        $attributes = $meta['attributes'] ?? [];
        $selfClosing = (bool) ($meta['selfClosing'] ?? false);
        $void = (bool) ($meta['void'] ?? false);

        $result = '<'.$tagName;

        foreach ($attributes as $name => $value) {
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
