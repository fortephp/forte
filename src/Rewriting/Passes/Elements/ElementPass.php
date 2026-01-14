<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;

abstract readonly class ElementPass implements AstRewriter
{
    public function __construct(protected string $pattern) {}

    /**
     * Apply the transformation to a matching element.
     */
    abstract protected function applyToElement(NodePath $path, ElementNode $element): void;

    public function rewrite(Document $doc): Document
    {
        return (new Rewriter)
            ->addVisitor(new CallbackVisitor(
                enter: function (NodePath $path): void {
                    if (! $element = $path->asElement()) {
                        return;
                    }

                    if (! $element->is($this->pattern)) {
                        return;
                    }

                    $this->applyToElement($path, $element);
                }
            ))
            ->rewrite($doc);
    }
}
