<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Node;
use Stringable;

class StrayClosingTagNode extends Node implements Stringable
{
    private ?string $cachedTagName = null;

    /**
     * Get the tag name as a string.
     */
    public function tagNameText(): string
    {
        if ($this->cachedTagName !== null) {
            return $this->cachedTagName;
        }

        return $this->cachedTagName = $this->extractTagName();
    }

    public function __toString(): string
    {
        return $this->getDocumentContent();
    }

    /**
     * Get the start offset of the closing tag, including the closing position.
     */
    public function tagStartOffset(): int
    {
        return $this->startOffset();
    }

    /**
     * Get the end offset of the closing tag, including the closing position.
     */
    public function tagEndOffset(): int
    {
        return $this->endOffset();
    }

    /**
     * Get the length of the closing tag, in bytes.
     */
    public function tagLength(): int
    {
        return $this->tagEndOffset() - $this->tagStartOffset();
    }

    /**
     * Extract the tag name from the token stream.
     */
    private function extractTagName(): string
    {
        $flat = $this->flat();
        $tokens = $this->document->getTokens();
        $source = $this->document->source();

        $tokenStart = $flat['tokenStart'];
        $tagNameTokenCount = $flat['data'] ?? 1;

        // Skip </ tokens (first 2 tokens) to get to the tag name
        $tagNameStartIdx = $tokenStart + 2;
        $tagNameEndIdx = $tagNameStartIdx + $tagNameTokenCount - 1;

        if ($tagNameStartIdx >= count($tokens)) {
            return '';
        }

        $startToken = $tokens[$tagNameStartIdx];
        $endToken = $tokens[min($tagNameEndIdx, count($tokens) - 1)];

        return substr($source, $startToken['start'], $endToken['end'] - $startToken['start']);
    }

    /**
     * Render the closing tag.
     */
    protected function renderComposed(): string
    {
        return $this->getDocumentContent();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'stray_closing_tag';
        $data['name'] = $this->tagNameText();
        $data['tag_start_offset'] = $this->tagStartOffset();
        $data['tag_end_offset'] = $this->tagEndOffset();
        $data['tag_length'] = $this->tagLength();

        return $data;
    }
}
