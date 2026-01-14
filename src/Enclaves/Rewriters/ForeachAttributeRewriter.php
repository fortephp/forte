<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\StringUtilities;

class ForeachAttributeRewriter extends Visitor
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

        $foreachAttr = $this->prefix.'foreach';

        if (! $elem->hasAttribute($foreachAttr)) {
            return;
        }

        $expression = $elem->getAttribute($foreachAttr);
        $expression = $this->normalizeExpression($expression);

        /**
         * Hello, source divers!
         * The following can also be achieved with explicit calls to:
         *
         * $path->insertBefore(...)
         *      ->insertAfter(...);
         */
        $path->removeAttribute($foreachAttr)
            ->wrapIn(
                Builder::directive('foreach', "({$expression})"),
                Builder::directive('endforeach')
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
