<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Node;

class BogusCommentNode extends Node
{
    private ?string $cachedContent = null;

    /**
     * Get the content of the bogus comment, without delimiters.
     */
    public function content(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        $raw = $this->getDocumentContent();

        if (str_starts_with($raw, '<?')) {
            $raw = substr($raw, 2);
        } elseif (str_starts_with($raw, '<!-')) {
            $raw = substr($raw, 3);
        } elseif (str_starts_with($raw, '<!')) {
            $raw = substr($raw, 2);
        } elseif (str_starts_with($raw, '<')) {
            $raw = substr($raw, 1);
        }

        if (str_ends_with($raw, '?>')) {
            $raw = substr($raw, 0, -2);
        } elseif (str_ends_with($raw, '>')) {
            $raw = substr($raw, 0, -1);
        }

        return $this->cachedContent = $raw;
    }

    /**
     * Check if the bogus comment has a proper closing character.
     */
    public function hasClose(): bool
    {
        $raw = $this->getDocumentContent();

        return str_ends_with($raw, '?>') || str_ends_with($raw, '>');
    }

    /**
     * Check if this bogus comment is empty.
     */
    public function isEmpty(): bool
    {
        return trim($this->content()) === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'bogus_comment';
        $data['inner_content'] = $this->content();
        $data['is_empty'] = $this->isEmpty();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
