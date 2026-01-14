<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDelimitedContent;
use Forte\Ast\Elements\Attributes;

class XmlDeclarationNode extends Node
{
    use HasDelimitedContent;

    private ?Attributes $cachedAttributes = null;

    /**
     * @return array{0: string, 1: string}
     */
    protected function getDelimiters(): array
    {
        return ['<?xml', '?>'];
    }

    public function attributes(): Attributes
    {
        if ($this->cachedAttributes !== null) {
            return $this->cachedAttributes;
        }

        $this->cachedAttributes = new Attributes($this->document, $this->index);

        return $this->cachedAttributes;
    }

    /**
     * Get the XML version (e.g., "1.0", "1.1").
     */
    public function version(): ?string
    {
        return $this->attributes()->find('version')?->valueText();
    }

    /**
     * Get the encoding (e.g., "UTF-8", "ISO-8859-1").
     */
    public function encoding(): ?string
    {
        return $this->attributes()->find('encoding')?->valueText();
    }

    /**
     * Get the standalone value (e.g., "yes", "no").
     */
    public function standalone(): ?string
    {
        return $this->attributes()->find('standalone')?->valueText();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'xml_declaration';
        $data['version'] = $this->version();
        $data['encoding'] = $this->encoding();
        $data['standalone'] = $this->standalone();
        $data['has_close'] = $this->hasClose();
        $data['attributes'] = array_map(
            fn ($attr) => $attr->jsonSerialize(),
            $this->attributes()->all()
        );

        return $data;
    }
}
