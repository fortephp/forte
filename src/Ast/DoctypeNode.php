<?php

declare(strict_types=1);

namespace Forte\Ast;

class DoctypeNode extends Node
{
    /**
     * Get the doctype declaration type.
     */
    public function type(): string
    {
        $content = strtolower($this->getDocumentContent());

        if (str_contains($content, 'html')) {
            return 'html';
        }
        if (str_contains($content, 'xml')) {
            return 'xml';
        }
        if (str_contains($content, 'xhtml')) {
            return 'xhtml';
        }

        return 'unknown';
    }

    public function isHtml5(): bool
    {
        $content = strtolower(trim($this->getDocumentContent()));

        return $content === '<!doctype html>';
    }

    public function isXhtml(): bool
    {
        return str_contains(strtolower($this->getDocumentContent()), 'xhtml');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'doctype';
        $data['doctype_type'] = $this->type();
        $data['is_html5'] = $this->isHtml5();
        $data['is_xhtml'] = $this->isXhtml();

        return $data;
    }
}
