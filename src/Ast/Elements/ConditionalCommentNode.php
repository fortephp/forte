<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Node;

class ConditionalCommentNode extends Node
{
    private ?string $cachedCondition = null;

    /**
     * Get the condition expression (e.g., "IE", "lt IE 9", "gte IE 8").
     */
    public function condition(): string
    {
        if ($this->cachedCondition !== null) {
            return $this->cachedCondition;
        }

        $content = $this->getDocumentContent();

        // Match <!--[if CONDITION]> or <!--[if CONDITION]><!-->
        if (preg_match('/<!--\[if\s+([^\]]+)\]/', $content, $matches)) {
            $this->cachedCondition = trim($matches[1]);

            return $this->cachedCondition;
        }

        $this->cachedCondition = '';

        return $this->cachedCondition;
    }

    /**
     * Check if this is a downlevel-hidden conditional comment.
     */
    public function isDownlevelHidden(): bool
    {
        return str_contains($this->getDocumentContent(), '><!-->');
    }

    /**
     * Check if this is a downlevel-revealed conditional comment.
     */
    public function isDownlevelRevealed(): bool
    {
        return ! $this->isDownlevelHidden();
    }

    /**
     * Get the content between the conditional markers.
     */
    public function content(): string
    {
        $raw = $this->getDocumentContent();

        if (preg_match('/<!--\[if[^\]]*\]>(?:<!-->)?/', $raw, $matches)) {
            $raw = substr($raw, strlen($matches[0]));
        }

        if (str_ends_with($raw, '<!--<![endif]-->')) {
            $raw = substr($raw, 0, -strlen('<!--<![endif]-->'));
        } elseif (str_ends_with($raw, '<![endif]-->')) {
            $raw = substr($raw, 0, -strlen('<![endif]-->'));
        }

        return $raw;
    }

    /**
     * Check if this conditional comment is properly closed.
     */
    public function hasClose(): bool
    {
        $content = $this->getDocumentContent();

        return str_contains($content, '<![endif]-->');
    }

    /**
     * Check if this conditional comment is empty.
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

        $data['type'] = 'conditional_comment';
        $data['condition'] = $this->condition();
        $data['inner_content'] = $this->content();
        $data['is_downlevel_hidden'] = $this->isDownlevelHidden();
        $data['is_downlevel_revealed'] = $this->isDownlevelRevealed();
        $data['is_empty'] = $this->isEmpty();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
