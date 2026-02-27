<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\NodePath;

interface AttributeDirective
{
    /**
     * Check if this directive handles the given attribute.
     */
    public function matches(Attribute $attr): bool;

    /**
     * Process the matched attribute on the element.
     */
    public function apply(NodePath $path, ElementNode $elem, Attribute $attr): void;
}
