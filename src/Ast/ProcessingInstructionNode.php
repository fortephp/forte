<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDelimitedContent;

class ProcessingInstructionNode extends Node
{
    use HasDelimitedContent;

    /**
     * @return array{0: string, 1: string}
     */
    protected function getDelimiters(): array
    {
        return ['<?', '?>'];
    }

    public function target(): string
    {
        $content = $this->content();
        $spacePos = strpos($content, ' ');

        if ($spacePos === false) {
            return $content;
        }

        return substr($content, 0, $spacePos);
    }

    public function data(): string
    {
        $content = $this->content();
        $spacePos = strpos($content, ' ');

        if ($spacePos === false) {
            return '';
        }

        return ltrim(substr($content, $spacePos + 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'processing_instruction';
        $data['target'] = $this->target();
        $data['pi_data'] = $this->data();
        $data['inner_content'] = $this->content();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
