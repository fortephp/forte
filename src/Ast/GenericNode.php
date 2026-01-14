<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Parser\NodeKind;

class GenericNode extends Node
{
    /**
     * Get a descriptive name for this node's kind.
     */
    public function kindName(): string
    {
        return NodeKind::name($this->kind());
    }

    /**
     * Check if this is a fragment node.
     */
    public function isFragment(): bool
    {
        return $this->kind() === NodeKind::Fragment;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'generic';
        $data['node_kind_name'] = $this->kindName();
        $data['is_fragment'] = $this->isFragment();

        return $data;
    }
}
