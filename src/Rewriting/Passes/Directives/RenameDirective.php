<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Directives;

use Forte\Ast\Document\Document;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;

class RenameDirective implements AstRewriter
{
    /** @var string|callable(string): string */
    private $newName;

    /**
     * @param  string  $pattern  Directive name pattern to match
     * @param  string|callable(string): string  $newName  New name or callback that receives old name
     */
    public function __construct(private readonly string $pattern, string|callable $newName)
    {
        $this->newName = $newName;
    }

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

        $newName = $this->resolveNewName($directive->name());

        $path->replaceWith(
            Builder::directive($newName, $directive->arguments())
        );

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

        $originalName = $directiveBlock->name();
        $newName = $this->resolveNewName($originalName);

        $rendered = $directiveBlock->render();

        $openingPattern = '/^@'.preg_quote($originalName, '/').'(\s*\()?/i';
        $closingPattern = '/@end'.preg_quote($originalName, '/').'$/i';

        $withRenamedOpening = preg_replace(
            $openingPattern,
            '@'.$newName.'$1',
            $rendered
        );

        $withRenamedClosing = preg_replace(
            $closingPattern,
            '@end'.$newName,
            (string) $withRenamedOpening
        );

        $path->replaceWith(Builder::raw((string) $withRenamedClosing));
    }

    private function resolveNewName(string $originalName): string
    {
        if (is_callable($this->newName)) {
            $result = ($this->newName)($originalName);

            return is_string($result) ? $result : $originalName;
        }

        return $this->newName;
    }
}
