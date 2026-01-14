<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Node;

class CommentNode extends Node
{
    private ?string $cachedContent = null;

    /**
     * Get the comment content (without <!-- and -->).
     */
    public function content(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        $raw = $this->getDocumentContent();

        if (str_starts_with($raw, '<!--')) {
            $raw = substr($raw, 4);
        }

        if (str_ends_with($raw, '-->')) {
            $raw = substr($raw, 0, -3);
        }

        $this->cachedContent = $raw;

        return $this->cachedContent;
    }

    /**
     * Check if this is an empty comment.
     */
    public function isEmpty(): bool
    {
        return trim($this->content()) === '';
    }

    /**
     * Check if the comment has a proper closing sequence.
     */
    public function hasClose(): bool
    {
        return $this->document->hasClosingDelimiter($this->index);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'html_comment';
        $data['inner_content'] = $this->content();
        $data['is_empty'] = $this->isEmpty();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
