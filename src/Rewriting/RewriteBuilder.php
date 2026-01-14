<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Document\Document;
use Forte\Ast\Document\Selection;
use Forte\Ast\Node;
use Forte\Rewriting\Builders\Builder;

class RewriteBuilder
{
    /**
     * @var array<int, array{
     *     remove?: bool,
     *     replace?: string,
     *     wrapTag?: string,
     *     wrapAttrs?: array<string, string>,
     *     insertBefore?: string,
     *     insertAfter?: string,
     *     addClass?: array<string>,
     *     removeClass?: array<string>,
     *     setAttribute?: array<string, string>,
     *     removeAttribute?: array<string>
     * }>
     */
    private array $operations = [];

    public function __construct(private readonly Document $document) {}

    /**
     * Find the first element matching a tag name.
     */
    public function find(string $tagName): Selection
    {
        $node = $this->document->findElementByName($tagName);

        if ($node === null) {
            // Try components
            $node = $this->document->findComponentByName($tagName);
        }

        return new Selection($node !== null ? [$node] : [], $this);
    }

    /**
     * Find all elements matching a tag name.
     */
    public function findAll(string $tagName): Selection
    {
        $elements = $this->document->findElementsByName($tagName)->all();
        $components = $this->document->findComponentsByName($tagName)->all();

        return new Selection(array_merge($elements, $components), $this);
    }

    /**
     * Find the first node matching an XPath expression.
     */
    public function xpath(string $expression): Selection
    {
        $node = $this->document->xpath($expression)->first();

        return new Selection($node !== null ? [$node] : [], $this);
    }

    /**
     * Find all nodes matching an XPath expression.
     */
    public function xpathAll(string $expression): Selection
    {
        /** @var array<Node> $nodes */
        $nodes = $this->document->xpath($expression)->all();

        return new Selection($nodes, $this);
    }

    /**
     * Select a specific node.
     */
    public function select(Node $node): Selection
    {
        return new Selection([$node], $this);
    }

    /**
     * Select multiple nodes.
     *
     * @param  array<Node>  $nodes
     */
    public function selectAll(array $nodes): Selection
    {
        return new Selection($nodes, $this);
    }

    /**
     * @internal
     */
    public function queueAddClass(Node $node, string $class): void
    {
        $idx = $node->index();
        $this->operations[$idx]['addClass'] ??= [];
        $this->operations[$idx]['addClass'][] = $class;
    }

    /**
     * @internal
     */
    public function queueRemoveClass(Node $node, string $class): void
    {
        $idx = $node->index();
        $this->operations[$idx]['removeClass'] ??= [];
        $this->operations[$idx]['removeClass'][] = $class;
    }

    /**
     * @internal
     */
    public function queueSetAttribute(Node $node, string $name, string $value): void
    {
        $idx = $node->index();
        $this->operations[$idx]['setAttribute'] ??= [];
        $this->operations[$idx]['setAttribute'][$name] = $value;
    }

    /**
     * @internal
     */
    public function queueRemoveAttribute(Node $node, string $name): void
    {
        $idx = $node->index();
        $this->operations[$idx]['removeAttribute'] ??= [];
        $this->operations[$idx]['removeAttribute'][] = $name;
    }

    /**
     * @internal
     */
    public function queueRemove(Node $node): void
    {
        $idx = $node->index();
        $this->operations[$idx]['remove'] = true;
    }

    /**
     * @internal
     */
    public function queueReplace(Node $node, string $replacement): void
    {
        $idx = $node->index();
        $this->operations[$idx]['replace'] = $replacement;
    }

    /**
     * @internal
     *
     * @param  array<string, string>  $attributes
     */
    public function queueWrap(Node $node, string $tagName, array $attributes): void
    {
        $idx = $node->index();
        $this->operations[$idx]['wrapTag'] = $tagName;
        $this->operations[$idx]['wrapAttrs'] = $attributes;
    }

    /**
     * @internal
     */
    public function queueInsertBefore(Node $node, string $content): void
    {
        $idx = $node->index();
        $existing = $this->operations[$idx]['insertBefore'] ?? '';
        $this->operations[$idx]['insertBefore'] = $existing.$content;
    }

    /**
     * @internal
     */
    public function queueInsertAfter(Node $node, string $content): void
    {
        $idx = $node->index();
        $existing = $this->operations[$idx]['insertAfter'] ?? '';
        $this->operations[$idx]['insertAfter'] = $existing.$content;
    }

    /**
     * Apply all queued operations and return the new Document.
     *
     * @internal
     */
    public function apply(): Document
    {
        if (empty($this->operations)) {
            return $this->document;
        }

        $operations = $this->operations;

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new CallbackVisitor(function (NodePath $path) use ($operations): void {
            $nodeIndex = $path->nodeIndex();

            if (! isset($operations[$nodeIndex])) {
                return;
            }

            $op = $operations[$nodeIndex];

            if (! empty($op['remove'])) {
                $path->remove();

                return;
            }

            if (isset($op['replace'])) {
                $path->replaceWith($op['replace']);

                return;
            }

            if (isset($op['wrapTag'])) {
                $wrapper = Builder::element($op['wrapTag']);
                foreach ($op['wrapAttrs'] ?? [] as $attrName => $attrValue) {
                    $wrapper->attr($attrName, $attrValue);
                }
                $path->wrapWith($wrapper);
            }

            if (isset($op['insertBefore'])) {
                $path->insertBefore($op['insertBefore']);
            }

            if (isset($op['insertAfter'])) {
                $path->insertAfter($op['insertAfter']);
            }

            if ($path->isElement()) {
                foreach ($op['addClass'] ?? [] as $class) {
                    $path->addClass($class);
                }

                foreach ($op['removeClass'] ?? [] as $class) {
                    $path->removeClass($class);
                }

                foreach ($op['setAttribute'] ?? [] as $name => $value) {
                    $path->setAttribute($name, $value);
                }

                foreach ($op['removeAttribute'] ?? [] as $name) {
                    $path->removeAttribute($name);
                }
            }
        }));

        return $rewriter->rewrite($this->document);
    }
}
