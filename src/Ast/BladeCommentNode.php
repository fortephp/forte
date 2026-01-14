<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDelimitedContent;

class BladeCommentNode extends Node
{
    use HasDelimitedContent;

    /**
     * @return array{0: string, 1: string}
     */
    protected function getDelimiters(): array
    {
        return ['{{--', '--}}'];
    }

    /**
     * Get the trimmed comment text.
     */
    public function text(): string
    {
        return trim($this->content());
    }

    /**
     * Check if the comment has no content.
     */
    public function isEmpty(): bool
    {
        return $this->content() === '';
    }

    public function render(): string
    {
        return $this->getDocumentContent();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'blade_comment';
        $data['inner_content'] = $this->content();
        $data['text'] = $this->text();
        $data['is_empty'] = $this->isEmpty();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
