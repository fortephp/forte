<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Directives;

use Forte\Ast\Document\Document;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\Builders\ElementBuilder;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;

readonly class WrapDirective implements AstRewriter
{
    /**
     * @param  string  $pattern  Directive name pattern to match
     * @param  string  $wrapperTag  Tag name for the wrapper element
     * @param  array<string, string>  $wrapperAttributes  Attributes for the wrapper element
     */
    public function __construct(private string $pattern, private string $wrapperTag, private array $wrapperAttributes = []) {}

    public function rewrite(Document $doc): Document
    {
        return (new Rewriter)
            ->addVisitor(
                new CallbackVisitor(
                    enter: function (NodePath $path): void {
                        if ($this->handleInlineDirective($path)) {
                            return;
                        }

                        $this->handleBlockDirective($path);
                    }
                )
            )
            ->rewrite($doc);
    }

    private function handleInlineDirective(NodePath $path): bool
    {
        if (! $directive = $path->asDirective()) {
            return false;
        }

        if (! $directive->is($this->pattern)) {
            return true;
        }

        $path->wrapWith($this->buildWrapper());

        return true;
    }

    private function handleBlockDirective(NodePath $path): void
    {
        if (! $directiveBlock = $path->asDirectiveBlock()) {
            return;
        }

        if (! $directiveBlock->is($this->pattern)) {
            return;
        }

        $path->wrapWith($this->buildWrapper());
    }

    private function buildWrapper(): ElementBuilder
    {
        $wrapper = Builder::element($this->wrapperTag);

        foreach ($this->wrapperAttributes as $name => $value) {
            $wrapper->attr($name, $value);
        }

        return $wrapper;
    }
}
