<?php

declare(strict_types=1);

namespace Forte\Rewriting;

abstract class Visitor implements RewriteVisitor
{
    public function enter(NodePath $path): void {}

    public function leave(NodePath $path): void {}
}
