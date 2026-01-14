<?php

declare(strict_types=1);

namespace Forte\Ast;

class PhpTagNode extends Node
{
    private ?string $cachedContent = null;

    /**
     * Get the PHP code inside the tags.
     */
    public function code(): string
    {
        return trim($this->content());
    }

    /**
     * Get the raw content inside the PHP tags.
     */
    public function content(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        $content = $this->getDocumentContent();

        if (str_starts_with($content, '<?php')) {
            $content = substr($content, 5);
        } elseif (str_starts_with($content, '<?=')) {
            $content = substr($content, 3);
        } elseif (str_starts_with($content, '<?')) {
            $content = substr($content, 2);
        }

        if (str_ends_with($content, '?>')) {
            $content = substr($content, 0, -2);
        }

        $this->cachedContent = $content;

        return $this->cachedContent;
    }

    /**
     * Check if this is a short echo tag (<?= ?>).
     */
    public function isShortEcho(): bool
    {
        return str_starts_with($this->getDocumentContent(), '<?=');
    }

    /**
     * Check if this is a full PHP tag (<?php ...?>).
     */
    public function isPhpTag(): bool
    {
        return str_starts_with($this->getDocumentContent(), '<?php');
    }

    /**
     * Get the PHP type: 'php' or 'echo'.
     */
    public function phpType(): string
    {
        return $this->isShortEcho() ? 'echo' : 'php';
    }

    /**
     * Check if this PHP tag has a closing tag.
     */
    public function hasClose(): bool
    {
        return str_ends_with($this->getDocumentContent(), '?>');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'php_tag';
        $data['php_type'] = $this->phpType();
        $data['code'] = $this->code();
        $data['inner_content'] = $this->content();
        $data['is_short_echo'] = $this->isShortEcho();
        $data['is_php_tag'] = $this->isPhpTag();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
