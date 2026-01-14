<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Elements\Concerns\ManagesTagNameParts;
use Forte\Ast\Node;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use Stringable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class ElementNameNode extends Node implements Stringable
{
    use ManagesTagNameParts;

    private ?int $cachedClosingTagStart = null;

    private ?int $cachedClosingTagEnd = null;

    public function __toString(): string
    {
        return $this->isClosingName() ? $this->renderClosingTag() : $this->name();
    }

    /**
     * Check if this is a closing element name.
     */
    public function isClosingName(): bool
    {
        return $this->kind() === NodeKind::ClosingElementName;
    }

    /**
     * Get the start offset of the full closing tag (including </).
     */
    public function closingTagStartOffset(): ?int
    {
        if (! $this->isClosingName()) {
            return null;
        }

        if ($this->cachedClosingTagStart !== null) {
            return $this->cachedClosingTagStart;
        }

        $flat = $this->flat();
        $tokens = $this->document->getTokens();
        $tokenIdx = $flat['tokenStart'];

        // Go back to find '<' token (skip '/' and any whitespace)
        while ($tokenIdx > 0) {
            $tokenIdx--;
            $type = $tokens[$tokenIdx]['type'];
            if ($type === TokenType::LessThan) {
                return $this->cachedClosingTagStart = $tokens[$tokenIdx]['start'];
            }
            if ($type !== TokenType::Slash && $type !== TokenType::Whitespace) {
                break;
            }
        }

        // Fallback: use this node's token start
        return $this->cachedClosingTagStart = $tokens[$flat['tokenStart']]['start'];
    }

    /**
     * Get the end offset of the full closing tag (including >).
     */
    public function closingTagEndOffset(): ?int
    {
        if (! $this->isClosingName()) {
            return null;
        }

        if ($this->cachedClosingTagEnd !== null) {
            return $this->cachedClosingTagEnd;
        }

        // The parent element's token range includes the closing >
        $parentFlat = $this->document->getFlatNode($this->flat()['parent']);
        $tokens = $this->document->getTokens();
        $lastTokenIdx = $parentFlat['tokenStart'] + $parentFlat['tokenCount'] - 1;

        return $this->cachedClosingTagEnd = $tokens[$lastTokenIdx]['end'];
    }

    /**
     * Get the length of the closing tag, in bytes.
     */
    public function closingTagLength(): ?int
    {
        $start = $this->closingTagStartOffset();
        $end = $this->closingTagEndOffset();

        if ($start === null || $end === null) {
            return null;
        }

        return $end - $start;
    }

    /**
     * Render the full closing tag (e.g., "</div>").
     */
    public function renderClosingTag(): string
    {
        if (! $this->isClosingName()) {
            return '';
        }

        $source = $this->document->source();
        $start = $this->closingTagStartOffset();
        $length = $this->closingTagLength();

        if ($start === null || $length === null) {
            return '';
        }

        return substr($source, $start, $length);
    }

    /**
     * Render the element name.
     */
    protected function renderComposed(): string
    {
        return $this->name();
    }

    /**
     * Get the start offset of the closing tag.
     */
    public function startOffset(): int
    {
        return $this->closingTagStartOffset() ?? parent::startOffset();
    }

    /**
     * Get the end offset of the closing tag.
     */
    public function endOffset(): int
    {
        return $this->closingTagEndOffset() ?? parent::endOffset();
    }

    /**
     * Get the length of the closing tag.
     */
    public function length(): int
    {
        return $this->closingTagLength() ?? 0;
    }

    /**
     * Render the closing tag or tag name.
     */
    public function render(): string
    {
        return $this->isClosingName() ? $this->renderClosingTag() : $this->name();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'element_name';
        $data['name'] = $this->name();
        $data['is_closing_name'] = $this->isClosingName();
        $data['is_complex'] = $this->isComplex();
        $data['is_simple'] = $this->isSimple();
        $data['static_text'] = $this->staticText();

        $data['parts'] = array_map(
            fn (Node $part) => $part->jsonSerialize(),
            $this->getParts()
        );

        if ($this->isClosingName()) {
            $data['closing_tag_start_offset'] = $this->closingTagStartOffset();
            $data['closing_tag_end_offset'] = $this->closingTagEndOffset();
            $data['closing_tag_length'] = $this->closingTagLength();
            $data['closing_tag_content'] = $this->renderClosingTag();
        }

        return $data;
    }
}
