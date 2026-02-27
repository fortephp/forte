<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\StringUtilities;

class ConditionalAttributesRewriter extends Visitor implements AttributeDirective
{
    /**
     * @param  string  $prefix  The attribute prefix to use
     */
    public function __construct(
        protected string $prefix = '#'
    ) {}

    public function matches(Attribute $attr): bool
    {
        $name = $attr->rawName();

        return $name === $this->prefix.'if'
            || $name === $this->prefix.'else-if'
            || $name === $this->prefix.'else';
    }

    public function apply(NodePath $path, ElementNode $elem, Attribute $attr): void
    {
        $name = $attr->rawName();
        $checkElseBranch = $this->isLeftmostDirective($elem, $attr);

        if ($name === $this->prefix.'if') {
            $this->handleIf($path, $elem, $name, $attr->valueText(), $checkElseBranch);
        } elseif ($name === $this->prefix.'else-if') {
            $this->handleElseIf($path, $elem, $name, $attr->valueText(), $checkElseBranch);
        } elseif ($name === $this->prefix.'else') {
            $this->handleElse($path, $elem, $name);
        }
    }

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

    protected function handleIf(NodePath $path, ElementNode $elem, string $attrName, ?string $value = null, bool $checkElseBranch = true): void
    {
        $condition = $value ?? $elem->getAttribute($attrName);
        $condition = $this->normalizeExpression($condition);

        $path->removeAttribute($attrName);

        if ($checkElseBranch && $this->hasElseBranch($path)) {
            $path->insertBefore(Builder::phpTag("if({$condition}):"));
        } else {
            $path->wrapIn(
                Builder::phpTag("if({$condition}):"),
                Builder::phpTag('endif;')
            );
        }
    }

    protected function handleElseIf(NodePath $path, ElementNode $elem, string $attrName, ?string $value = null, bool $checkElseBranch = true): void
    {
        $condition = $value ?? $elem->getAttribute($attrName);
        $condition = $this->normalizeExpression($condition);

        $path->removeAttribute($attrName);

        if ($checkElseBranch && $this->hasElseBranch($path)) {
            $path->insertBefore(Builder::phpTag("elseif({$condition}):"));
        } else {
            $path->wrapIn(
                Builder::phpTag("elseif({$condition}):"),
                Builder::phpTag('endif;')
            );
        }
    }

    protected function handleElse(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $path->removeAttribute($attrName)
            ->wrapIn(
                Builder::phpTag('else:'),
                Builder::phpTag('endif;')
            );
    }

    private function isLeftmostDirective(ElementNode $elem, Attribute $attr): bool
    {
        foreach ($elem->attributes() as $a) {
            if ($this->matches($a)) {
                return $a === $attr;
            }
        }

        return true;
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
