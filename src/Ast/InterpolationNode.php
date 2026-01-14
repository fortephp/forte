<?php

declare(strict_types=1);

namespace Forte\Ast;

class InterpolationNode extends TextNode
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'interpolation';

        return $data;
    }
}
