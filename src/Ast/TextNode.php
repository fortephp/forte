<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Trivia\Trivia;
use Forte\Ast\Trivia\TriviaKind;
use Forte\Ast\Trivia\TriviaParser;
use Forte\Support\HtmlCharacterReferences;

class TextNode extends Node
{
    private const RAW_TEXT_ELEMENTS = [
        'iframe' => true,
        'noembed' => true,
        'noframes' => true,
        'plaintext' => true,
        'script' => true,
        'style' => true,
        'xmp' => true,
    ];

    /** @var array<int, Trivia>|null */
    private ?array $cachedTrivia = null;

    private ?string $cachedDecodedContent = null;

    /**
     * Get the text content.
     */
    public function getContent(): string
    {
        return $this->getDocumentContent();
    }

    /**
     * Get text with HTML character references decoded.
     *
     * This is intended for data and RCDATA semantics. Raw-text element consumers
     * should continue to use getContent().
     */
    public function getDecodedContent(): string
    {
        return $this->cachedDecodedContent ??= HtmlCharacterReferences::decodeText($this->getContent());
    }

    /**
     * Get the text content as exposed by an HTML document tree.
     *
     * Character references are decoded in data and RCDATA, but remain literal in
     * raw-text elements such as script and style.
     */
    public function getSemanticContent(): string
    {
        $element = $this->closestOfType(ElementNode::class);
        if ($element !== null && isset(self::RAW_TEXT_ELEMENTS[strtolower($element->tagNameText())])) {
            return $this->getContent();
        }

        return $this->getDecodedContent();
    }

    /**
     * Check if this text is only whitespace.
     */
    public function isWhitespace(): bool
    {
        return trim($this->getContent()) === '';
    }

    /**
     * Check if this text contains non-whitespace content.
     */
    public function hasSignificantContent(): bool
    {
        return ! $this->isWhitespace();
    }

    /**
     * Get the trimmed content.
     */
    public function getTrimmedContent(): string
    {
        return trim($this->getContent());
    }

    /**
     * Get trivia tokens for this text content.
     *
     * @return array<int, Trivia>
     */
    public function getTrivia(): array
    {
        if ($this->cachedTrivia !== null) {
            return $this->cachedTrivia;
        }

        return $this->cachedTrivia = TriviaParser::parse($this->getContent());
    }

    /**
     * Count leading newlines in the text.
     */
    public function countLeadingNewlines(): int
    {
        $trivia = $this->getTrivia();
        if (empty($trivia)) {
            return 0;
        }

        $first = $trivia[0];
        if ($first->kind !== TriviaKind::LeadingWhitespace) {
            return 0;
        }

        return $first->getNewlineCount();
    }

    /**
     * Count trailing newlines in the text.
     */
    public function countTrailingNewlines(): int
    {
        $trivia = $this->getTrivia();
        if (empty($trivia)) {
            return 0;
        }

        $last = end($trivia);
        if ($last->kind !== TriviaKind::TrailingWhitespace) {
            return 0;
        }

        return $last->getNewlineCount();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'text';
        $data['text'] = $this->getContent();
        $data['decoded_text'] = $this->getDecodedContent();
        $data['semantic_text'] = $this->getSemanticContent();
        $data['is_whitespace'] = $this->isWhitespace();
        $data['has_significant_content'] = $this->hasSignificantContent();
        $data['trimmed_content'] = $this->getTrimmedContent();
        $data['leading_newlines'] = $this->countLeadingNewlines();
        $data['trailing_newlines'] = $this->countTrailingNewlines();

        return $data;
    }
}
