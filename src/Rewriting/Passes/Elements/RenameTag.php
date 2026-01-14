<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Document\Document;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;

class RenameTag implements AstRewriter
{
    /** @var string|callable(string): string */
    private $newName;

    /**
     * @param  string  $pattern  Tag name pattern to match
     * @param  string|callable(string): string  $newName  New tag name or callback to generate it
     */
    public function __construct(private readonly string $pattern, string|callable $newName)
    {
        $this->newName = $newName;
    }

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

                    if (is_callable($this->newName)) {
                        $result = ($this->newName)($element->tagNameText());
                        $newName = is_string($result) ? $result : $element->tagNameText();
                    } else {
                        $newName = $this->newName;
                    }

                    $path->renameTag($newName);
                }
            ))
            ->rewrite($doc);
    }
}
