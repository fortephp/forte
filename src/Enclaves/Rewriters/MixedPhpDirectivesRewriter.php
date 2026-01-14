<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\DirectiveNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\StringUtilities;
use Illuminate\Support\Str;

class MixedPhpDirectivesRewriter extends Visitor
{
    public function enter(NodePath $path): void
    {
        $node = $path->node();

        if (! $node instanceof DirectiveNode) {
            return;
        }

        if ($node->nameText() !== 'php' || ! $node->hasArguments()) {
            return;
        }

        $content = trim(StringUtilities::unwrapParentheses($node->arguments() ?? ''));
        $content = Str::finish($content, ';');

        $path->replaceWith(Builder::phpTag(' '.$content));
    }
}
