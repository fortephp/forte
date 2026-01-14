<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\NodePath;

readonly class UnwrapElements extends ElementPass
{
    protected function applyToElement(NodePath $path, ElementNode $element): void
    {
        $path->unwrap();
    }
}
