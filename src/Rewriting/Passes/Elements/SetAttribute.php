<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\NodePath;

readonly class SetAttribute extends ElementPass
{
    /**
     * @param  string  $pattern  Tag name pattern to match
     * @param  string  $name  Attribute name to set
     * @param  string  $value  Attribute value
     */
    public function __construct(string $pattern, private string $name, private string $value)
    {
        parent::__construct($pattern);
    }

    protected function applyToElement(NodePath $path, ElementNode $element): void
    {
        $path->setAttribute($this->name, $this->value);
    }
}
