<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Trivia\Trivia;
use Forte\Ast\Trivia\TriviaKind;
use Forte\Ast\Trivia\TriviaParser;

class TextNode extends Node
{
    /** @var array<int, Trivia>|null */
    private ?array $cachedTrivia = null;

    /**
     * Get the text content.
     */
    public function getContent(): string
    {
        return $this->getDocumentContent();
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
        $data['is_whitespace'] = $this->isWhitespace();
        $data['has_significant_content'] = $this->hasSignificantContent();
        $data['trimmed_content'] = $this->getTrimmedContent();
        $data['leading_newlines'] = $this->countLeadingNewlines();
        $data['trailing_newlines'] = $this->countTrailingNewlines();

        return $data;
    }
}
