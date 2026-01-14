<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\StringUtilities;

class ConditionalAttributesRewriter extends Visitor
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

        $ifAttr = $this->prefix.'if';
        $elseIfAttr = $this->prefix.'else-if';
        $elseAttr = $this->prefix.'else';

        if ($elem->hasAttribute($ifAttr)) {
            $this->handleIf($path, $elem, $ifAttr);

            return;
        }

        if ($elem->hasAttribute($elseIfAttr)) {
            $this->handleElseIf($path, $elem, $elseIfAttr);

            return;
        }

        if ($elem->hasAttribute($elseAttr)) {
            $this->handleElse($path, $elem, $elseAttr);

            return;
        }
    }

    protected function handleIf(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $condition = $elem->getAttribute($attrName);
        $condition = $this->normalizeExpression($condition);

        $path
            ->removeAttribute($attrName)
            ->insertBefore(Builder::directive('if', "({$condition})"));

        if (! $this->hasElseBranch($path)) {
            $path->insertAfter(Builder::directive('endif'));
        }
    }

    protected function handleElseIf(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $condition = $elem->getAttribute($attrName);
        $condition = $this->normalizeExpression($condition);

        $path->removeAttribute($attrName)
            ->insertBefore(Builder::directive('elseif', "({$condition})"));

        if (! $this->hasElseBranch($path)) {
            $path->insertAfter(Builder::directive('endif'));
        }
    }

    protected function handleElse(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $path->removeAttribute($attrName)
            ->insertBefore(Builder::directive('else'))
            ->insertAfter(Builder::directive('endif'));
    }

    protected function hasElseBranch(NodePath $path): bool
    {
        $next = $path->nextSibling();

        while ($next !== null && $this->isWhitespaceText($next)) {
            $siblings = $path->parentPath() !== null
                ? $path->parentPath()->node()->getChildren()
                : $path->document()->getChildren();

            $foundCurrent = false;
            $nextNext = null;

            foreach ($siblings as $sibling) {
                if ($foundCurrent && $sibling !== $next) {
                    $nextNext = $sibling;
                    break;
                }
                if ($sibling === $next) {
                    $foundCurrent = true;
                }
            }

            $next = $nextNext;
        }

        if (! $next instanceof ElementNode) {
            return false;
        }

        return $next->hasAttribute($this->prefix.'else-if')
            || $next->hasAttribute($this->prefix.'else');
    }

    protected function normalizeExpression(?string $expression): string
    {
        if ($expression === null) {
            return 'true';
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
