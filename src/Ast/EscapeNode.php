<?php

declare(strict_types=1);

namespace Forte\Ast;

class EscapeNode extends Node
{
    /**
     * Get the escape character content.
     */
    public function content(): string
    {
        return '@';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'escape';
        $data['escaped_content'] = $this->content();

        return $data;
    }
}
