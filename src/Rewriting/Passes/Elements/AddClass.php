<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\NodePath;

readonly class AddClass extends ElementPass
{
    /**
     * @param  string  $pattern  Tag name pattern to match
     * @param  string  $class  Class name to add
     */
    public function __construct(string $pattern, private string $class)
    {
        parent::__construct($pattern);
    }

    protected function applyToElement(NodePath $path, ElementNode $element): void
    {
        $path->addClass($this->class);
    }
}
