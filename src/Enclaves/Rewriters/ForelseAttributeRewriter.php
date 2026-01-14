<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\StringUtilities;

class ForelseAttributeRewriter extends Visitor
{
    /**
     * @param  string  $prefix  The attribute prefix to use
     */
    public function __construct(
        protected string $prefix = '#'
    ) {}

    public function enter(NodePath $path): void
    {
        $elem = $path->asElement();
        if ($elem === null) {
            return;
        }

        $forelseAttr = $this->prefix.'forelse';
        $emptyAttr = $this->prefix.'empty';

        if ($elem->hasAttribute($forelseAttr)) {
            $this->handleForelse($path, $elem, $forelseAttr);

            return;
        }

        if ($elem->hasAttribute($emptyAttr)) {
            $this->handleEmpty($path, $elem, $emptyAttr);

            return;
        }
    }

    protected function handleForelse(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $expression = $elem->getAttribute($attrName);
        $expression = $this->normalizeExpression($expression);

        $path->removeAttribute($attrName)
            ->insertBefore(Builder::directive('forelse', "({$expression})"));

        if (! $this->hasEmptyBranch($path)) {
            // Only add @endforelse if there's no #empty branch
            $path->insertAfter(Builder::directive('endforelse'));
        }
    }

    protected function handleEmpty(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $path->removeAttribute($attrName)
            ->insertBefore(Builder::directive('empty'))
            ->insertAfter(Builder::directive('endforelse'));
    }

    /**
     * Check if the current node has an #empty sibling following it.
     */
    protected function hasEmptyBranch(NodePath $path): bool
    {
        $next = $path->nextSibling();

        $siblings = $path->parentPath() !== null
            ? $path->parentPath()->node()->getChildren()
            : $path->document()->getChildren();

        $indexById = [];
        foreach ($siblings as $i => $sibling) {
            $indexById[spl_object_id($sibling)] = $i;
        }

        while ($next !== null && $this->isWhitespaceText($next)) {
            $id = spl_object_id($next);

            if (! isset($indexById[$id])) {
                $next = null;
                break;
            }

            $nextIndex = $indexById[$id] + 1;
            $next = $siblings[$nextIndex] ?? null;
        }

        return $next instanceof ElementNode
            && $next->hasAttribute($this->prefix.'empty');
    }

    protected function normalizeExpression(?string $expression): string
    {
        if ($expression === null) {
            return '';
        }

        return trim(StringUtilities::unwrapParentheses($expression));
    }

    protected function isWhitespaceText(mixed $node): bool
    {
        if (! $node instanceof TextNode) {
            return false;
        }

        return trim($node->getDocumentContent()) === '';
    }
}
