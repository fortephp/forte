<?php

declare(strict_types=1);

namespace Forte\Tests\Support\Transformers;

use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;

class PackageMarkerVisitor extends Visitor
{
    private bool $markerAdded = false;

    public function leave(NodePath $path): void
    {
        if ($this->markerAdded) {
            return;
        }

        if ($path->isRoot() && $path->nextSibling() === null) {
            $path->insertAfter(' [PKG]');
            $this->markerAdded = true;
        }
    }
}
