<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;
use Forte\Support\LoopVariablesExtractor;
use Forte\Support\StringUtilities;

class ForelseAttributeRewriter extends Visitor implements AttributeDirective
{
    /**
     * @param  string  $prefix  The attribute prefix to use
     */
    private int $forelseCounter = 0;

    /** @var list<int> */
    private array $forelseCounterStack = [];

    public function __construct(
        protected string $prefix = '#'
    ) {}

    public function matches(Attribute $attr): bool
    {
        $name = $attr->rawName();

        return $name === $this->prefix.'forelse'
            || $name === $this->prefix.'empty';
    }

    public function apply(NodePath $path, ElementNode $elem, Attribute $attr): void
    {
        $name = $attr->rawName();

        if ($name === $this->prefix.'forelse') {
            $this->handleForelse($path, $elem, $name, $attr->valueText());
        } elseif ($name === $this->prefix.'empty') {
            $this->handleEmpty($path, $elem, $name);
        }
    }

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

    protected function handleForelse(NodePath $path, ElementNode $elem, string $attrName, ?string $value = null): void
    {
        $expression = $value ?? $elem->getAttribute($attrName);
        $expression = $this->normalizeExpression($expression);

        $extractor = new LoopVariablesExtractor;
        $loop = $extractor->extractDetails($expression);

        $path->removeAttribute($attrName);

        if ($this->hasEmptyBranch($path)) {
            $this->forelseCounter++;
            $this->forelseCounterStack[] = $this->forelseCounter;
            $emptyVar = '$__empty_'.$this->forelseCounter;

            $path->insertBefore(
                Builder::phpTag("{$emptyVar} = true; \$__currentLoopData = {$loop->variable}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$loop->alias}): {$emptyVar} = false; \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop();")
            );
        } else {
            $path->wrapIn(
                Builder::phpTag("\$__currentLoopData = {$loop->variable}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$loop->alias}): \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop();"),
                Builder::phpTag('endforeach; $__env->popLoop(); $loop = $__env->getLastLoop();')
            );
        }
    }

    protected function handleEmpty(NodePath $path, ElementNode $elem, string $attrName): void
    {
        $counter = array_pop($this->forelseCounterStack);
        $emptyVar = '$__empty_'.$counter;

        $path->removeAttribute($attrName)
            ->wrapIn(
                Builder::phpTag("endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if ({$emptyVar}):"),
                Builder::phpTag('endif;')
            );
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
