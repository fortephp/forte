<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Directives;

use Forte\Ast\Document\Document;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;

readonly class RemoveDirective implements AstRewriter
{
    /**
     * @param  string  $pattern  Directive name pattern to match
     */
    public function __construct(private string $pattern) {}

    public function rewrite(Document $doc): Document
    {
        return (new Rewriter)
            ->addVisitor(new CallbackVisitor(
                enter: function (NodePath $path): void {
                    if ($directive = $path->asDirective()) {
                        if ($directive->is($this->pattern)) {
                            $path->remove();
                        }

                        return;
                    }

                    if ($directiveBlock = $path->asDirectiveBlock()) {
                        if ($directiveBlock->is($this->pattern)) {
                            $path->remove();
                        }
                    }
                }
            ))
            ->rewrite($doc);
    }
}
