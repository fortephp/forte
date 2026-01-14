<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

trait HasDelimitedContent
{
    private ?string $cachedContent = null;

    /**
     * Get the content without delimiters.
     */
    public function content(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        $raw = $this->getDocumentContent();
        [$open, $close] = $this->getDelimiters();

        if (str_starts_with((string) $raw, (string) $open)) {
            $raw = substr((string) $raw, strlen((string) $open));
        }

        if ($close !== null && str_ends_with((string) $raw, $close)) {
            $raw = substr((string) $raw, 0, -strlen($close));
        }

        return $this->cachedContent = $raw;
    }

    /**
     * Check if the node has its closing delimiter.
     */
    public function hasClose(): bool
    {
        return $this->document->hasClosingDelimiter($this->index);
    }

    /**
     * Check if this node is empty.
     *
     * An empty node contains no content or whitespace-only content.
     */
    public function isEmpty(): bool
    {
        return trim($this->content()) === '';
    }

    /**
     * Get the opening and closing delimiters for this node type.
     *
     * @return array{0: string, 1: string|null} [opening, closing]
     */
    abstract protected function getDelimiters(): array;
}
