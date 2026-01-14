<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDelimitedContent;

class PhpBlockNode extends Node
{
    use HasDelimitedContent;

    private ?string $cachedCode = null;

    /**
     * @return array{0: string, 1: string}
     */
    protected function getDelimiters(): array
    {
        return ['@php', '@endphp'];
    }

    /**
     * Get the PHP code inside the block.
     */
    public function code(): string
    {
        if ($this->cachedCode !== null) {
            return $this->cachedCode;
        }

        $this->cachedCode = trim($this->content());

        return $this->cachedCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'php_block';
        $data['code'] = $this->code();
        $data['inner_content'] = $this->content();
        $data['has_close'] = $this->hasClose();

        return $data;
    }
}
