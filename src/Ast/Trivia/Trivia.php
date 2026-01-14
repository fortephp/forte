<?php

declare(strict_types=1);

namespace Forte\Ast\Trivia;

use Forte\Support\StringUtilities;

readonly class Trivia
{
    public function __construct(
        public TriviaKind $kind,
        public string $content,
        public int $offset
    ) {}

    /**
     * Count the number of newlines in this trivia token.
     */
    public function getNewlineCount(): int
    {
        return substr_count(
            StringUtilities::normalizeLineEndings($this->content),
            "\n"
        );
    }

    /**
     * Check if this trivia contains multiple newlines.
     */
    public function hasMultipleNewlines(): bool
    {
        return $this->getNewlineCount() >= 2;
    }

    /**
     * Get the byte length of this trivia token.
     */
    public function getLength(): int
    {
        return strlen($this->content);
    }

    /**
     * Get the ending offset of this trivia token.
     */
    public function getEndOffset(): int
    {
        return $this->offset + $this->getLength();
    }

    /**
     * Check if this trivia is empty.
     */
    public function isEmpty(): bool
    {
        return $this->content === '';
    }

    /**
     * Check if this trivia contains only newline characters.
     */
    public function isNewlineOnly(): bool
    {
        return $this->content !== '' && preg_match('/^[\r\n]+$/', $this->content) === 1;
    }

    /**
     * Check if this trivia contains only space characters.
     */
    public function isSpaceOnly(): bool
    {
        return $this->content !== '' && preg_match('/^ +$/', $this->content) === 1;
    }

    /**
     * Check if this trivia contains only tab characters.
     */
    public function isTabOnly(): bool
    {
        return $this->content !== '' && preg_match('/^\t+$/', $this->content) === 1;
    }

    /**
     * Check if this trivia contains a mix of different whitespace types.
     */
    public function isMixedWhitespace(): bool
    {
        if ($this->kind !== TriviaKind::LeadingWhitespace && $this->kind !== TriviaKind::TrailingWhitespace) {
            return false;
        }

        $hasSpace = str_contains($this->content, ' ');
        $hasTab = str_contains($this->content, "\t");
        $hasNewline = $this->getNewlineCount() > 0;

        return ($hasSpace ? 1 : 0) + ($hasTab ? 1 : 0) + ($hasNewline ? 1 : 0) >= 2;
    }

    /**
     * Check if this trivia contains the given substring.
     */
    public function contains(string $needle): bool
    {
        return str_contains($this->content, $needle);
    }
}
