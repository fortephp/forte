<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Concerns\HasDelimitedContent;
use Forte\Ast\Node;

class CdataNode extends Node
{
    use HasDelimitedContent;

    /**
     * @return array{0: string, 1: string}
     */
    protected function getDelimiters(): array
    {
        return ['<![CDATA[', ']]>'];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'cdata';
        $data['inner_content'] = $this->content();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
