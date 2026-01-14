<?php

declare(strict_types=1);

namespace Forte\Parser\Errors;

use Stringable;

readonly class ParseError implements Stringable
{
    public function __construct(
        public ParseErrorKind $kind,
        public string $message,
        public int $offset,
        public ?int $endOffset = null,
        public ?string $context = null
    ) {}

    /**
     * Get the error range end.
     */
    public function end(): int
    {
        return $this->endOffset ?? ($this->offset + 1);
    }

    /**
     * Create an unclosed element error.
     */
    public static function unclosedElement(string $tagName, int $offset, ?int $endOffset = null): self
    {
        return new self(
            ParseErrorKind::UnclosedElement,
            "Unclosed element: <{$tagName}>",
            $offset,
            $endOffset,
            $tagName
        );
    }

    /**
     * Create an unclosed directive block error.
     */
    public static function unclosedDirective(string $name, int $offset, ?int $endOffset = null): self
    {
        return new self(
            ParseErrorKind::UnclosedDirective,
            "Unclosed directive block: @{$name}",
            $offset,
            $endOffset,
            $name
        );
    }

    /**
     * Create a mismatched closing tag error.
     */
    public static function mismatchedClosingTag(string $expected, string $found, int $offset): self
    {
        return new self(
            ParseErrorKind::MismatchedClosingTag,
            "Expected </{$expected}> but found </{$found}>",
            $offset,
            null,
            "{$expected}:{$found}"
        );
    }

    /**
     * Create an unexpected closing tag error.
     */
    public static function unexpectedClosingTag(string $tagName, int $offset): self
    {
        return new self(
            ParseErrorKind::UnexpectedClosingTag,
            "Unexpected closing tag: </{$tagName}>",
            $offset,
            null,
            $tagName
        );
    }

    /**
     * Create an invalid nesting error.
     */
    public static function invalidNesting(string $message, int $offset): self
    {
        return new self(
            ParseErrorKind::InvalidNesting,
            $message,
            $offset
        );
    }

    /**
     * Format the error as a string.
     */
    public function __toString(): string
    {
        $range = $this->endOffset !== null
            ? "{$this->offset}-{$this->endOffset}"
            : (string) $this->offset;

        return "ParseError({$range}): [{$this->kind->name}] {$this->message}";
    }
}
