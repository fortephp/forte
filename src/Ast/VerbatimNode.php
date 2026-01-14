<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDelimitedContent;

class VerbatimNode extends Node
{
    use HasDelimitedContent;

    /**
     * @return array{0: string, 1: string}
     */
    protected function getDelimiters(): array
    {
        return ['@verbatim', '@endverbatim'];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'verbatim';
        $data['inner_content'] = $this->content();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
