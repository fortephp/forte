<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\LoopVariablesExtractor;
use Forte\Support\StringUtilities;

class ForeachAttributeRewriter extends Visitor implements AttributeDirective
{
    /**
     * @param  string  $prefix  The attribute prefix to use
     */
    public function __construct(
        protected string $prefix = '#'
    ) {}

    public function matches(Attribute $attr): bool
    {
        return $attr->rawName() === $this->prefix.'foreach';
    }

    public function apply(NodePath $path, ElementNode $elem, Attribute $attr): void
    {
        $this->handleForeach($path, $elem, $attr->rawName(), $attr->valueText());
    }

    public function enter(NodePath $path): void
    {
        $elem = $path->asElement();

        if ($elem === null) {
            return;
        }

        $foreachAttr = $this->prefix.'foreach';

        if (! $elem->hasAttribute($foreachAttr)) {
            return;
        }

        $this->handleForeach($path, $elem, $foreachAttr);
    }

    protected function handleForeach(NodePath $path, ElementNode $elem, string $attrName, ?string $value = null): void
    {
        $expression = $value ?? $elem->getAttribute($attrName);
        $expression = $this->normalizeExpression($expression);

        $extractor = new LoopVariablesExtractor;
        $loop = $extractor->extractDetails($expression);

        $path->removeAttribute($attrName)
            ->wrapIn(
                Builder::phpTag("\$__currentLoopData = {$loop->variable}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$loop->alias}): \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop();"),
                Builder::phpTag('endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();')
            );
    }

    protected function normalizeExpression(?string $expression): string
    {
        if ($expression === null) {
            return '';
        }

        return trim(StringUtilities::unwrapParentheses($expression));
    }
}
