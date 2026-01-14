<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\Attributes;

trait ManagesElementAttributes
{
    /**
     * Get the element's attributes.
     */
    public function attributes(): Attributes
    {
        if ($this->cachedAttributes !== null) {
            return $this->cachedAttributes;
        }

        return $this->cachedAttributes = new Attributes($this->document, $this->index);
    }

    /**
     * @return Attribute[]
     */
    public function getAttributes(): array
    {
        return $this->attributes()->all();
    }

    /**
     * Get an attribute value by name if it exists.
     */
    public function getAttribute(string $name): ?string
    {
        return $this->attributes()->find($name)?->valueText();
    }

    /**
     * Check if an attribute exists.
     */
    public function hasAttribute(string $name): bool
    {
        return $this->attributes()->has($name);
    }

    /**
     * Get the "class" attribute value if it exists.
     */
    public function getClass(): ?string
    {
        return $this->getAttribute('class');
    }

    /**
     * Get the "id" attribute value if it exists.
     */
    public function getId(): ?string
    {
        return $this->getAttribute('id');
    }
}
