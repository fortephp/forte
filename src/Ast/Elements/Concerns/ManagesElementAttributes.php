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
     * Get an attribute node by name if it exists.
     */
    public function attribute(string $name): ?Attribute
    {
        return $this->attributes()->firstNamed($name);
    }

    /**
     * Get an attribute value by name if it exists.
     */
    public function getAttribute(string $name): ?string
    {
        return $this->attributes()->find($name)?->valueText();
    }

    /**
     * Get a browser-decoded static attribute value.
     *
     * Complex, bound, shorthand, and expression values return null because
     * their runtime value cannot be known from the template alone.
     */
    public function staticAttributeValue(string $name): ?string
    {
        return $this->attribute($name)?->staticValue();
    }

    /**
     * Get a lower-cased browser-decoded static attribute value.
     */
    public function staticAttributeValueLower(string $name): ?string
    {
        return $this->attribute($name)?->staticValueLower();
    }

    /**
     * Get a browser-decoded attribute value as HTML space-separated tokens.
     *
     * @return list<string>
     */
    public function attributeTokens(string $name): array
    {
        return $this->attribute($name)?->tokens() ?? [];
    }

    /**
     * Get lower-cased HTML space-separated attribute tokens.
     *
     * @return list<string>
     */
    public function attributeTokensLower(string $name): array
    {
        return $this->attribute($name)?->tokensLower() ?? [];
    }

    /**
     * Get HTML space-separated tokens when an attribute is fully static.
     *
     * @return list<string>|null
     */
    public function staticAttributeTokens(string $name): ?array
    {
        return $this->attribute($name)?->staticTokens();
    }

    /**
     * Get lower-cased HTML space-separated tokens when fully static.
     *
     * @return list<string>|null
     */
    public function staticAttributeTokensLower(string $name): ?array
    {
        return $this->attribute($name)?->staticTokensLower();
    }

    /**
     * Check if an attribute exists.
     */
    public function hasAttribute(string $name): bool
    {
        return $this->attributes()->has($name);
    }

    /**
     * Check whether at least one named attribute exists.
     *
     * @param  string|array<string>  $names
     */
    public function hasAnyAttribute(string|array $names): bool
    {
        return $this->attributes()->hasAny($names);
    }

    /**
     * Check whether every named attribute exists.
     *
     * @param  string|array<string>  $names
     */
    public function hasAllAttributes(string|array $names): bool
    {
        return $this->attributes()->hasAll($names);
    }

    /**
     * Check whether a named attribute depends on runtime template evaluation.
     */
    public function attributeIsDynamic(string $name): bool
    {
        return $this->attribute($name)?->isDynamic() ?? false;
    }

    /**
     * Check whether a literal attribute is guaranteed to be present even when
     * its value contains runtime template output.
     */
    public function hasUnconditionallyPresentAttribute(string $name): bool
    {
        return $this->attribute($name)?->isUnconditionallyPresent() ?? false;
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
