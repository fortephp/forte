<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes;

use Exception;
use Forte\Ast\Components\ComponentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Illuminate\Support\Str;

class Instrumentation implements AstRewriter
{
    /** @var array<string, array<string>> */
    private array $typePatterns = [];

    /** @var callable|null */
    private $callback;

    private string $commentPrefix = 'forte';

    private bool $includeLineNumbers = false;

    /** @var array<string, mixed> */
    private array $extraMeta = [];

    private function __construct() {}

    /**
     * Create a new Instrumentation transformer.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Instrument element nodes matching patterns.
     *
     * @param  array<string>|null  $patterns  Tag patterns (e.g., ['div', 'section', 'x-*']). Null = all.
     */
    public function elements(?array $patterns = null): self
    {
        $this->typePatterns['element'] = $patterns ?? ['*'];

        return $this;
    }

    /**
     * Instrument component nodes matching patterns.
     *
     * @param  array<string>|null  $patterns  Component patterns (e.g., ['x-alert', 'livewire:*']). Null = all.
     */
    public function components(?array $patterns = null): self
    {
        $this->typePatterns['component'] = $patterns ?? ['*'];

        return $this;
    }

    /**
     * Instrument directive nodes matching patterns.
     *
     * @param  array<string>|null  $patterns  Directive patterns (e.g., ['include', 'yield']). Null = all.
     */
    public function directives(?array $patterns = null): self
    {
        $this->typePatterns['directive'] = $patterns ?? ['*'];

        return $this;
    }

    /**
     * Instrument directive block nodes matching patterns.
     *
     * @param  array<string>|null  $patterns  Block patterns (e.g., ['if', 'foreach']). Null = all.
     */
    public function directiveBlocks(?array $patterns = null): self
    {
        $this->typePatterns['directiveBlock'] = $patterns ?? ['*'];

        return $this;
    }

    /**
     * Only match nodes with these patterns.
     *
     * @param  array<string>  $patterns  Name patterns (supports * wildcards)
     */
    public function matching(array $patterns): self
    {
        foreach (array_keys($this->typePatterns) as $type) {
            $this->typePatterns[$type] = $patterns;
        }

        return $this;
    }

    /**
     * Set a custom callback for generating markers.
     *
     * @param  callable(array<string, mixed>): array{0: string, 1: string, 2?: array<string, string>}  $callback
     */
    public function using(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Set the comment prefix for default markers.
     */
    public function prefix(string $prefix): self
    {
        $this->commentPrefix = $prefix;

        return $this;
    }

    /**
     * Use data attribute instrumentation instead of comments.
     *
     * Injects a data attribute (default: data-source) with encoded metadata.
     */
    public function asAttribute(string $attributeName = 'data-instrumentation'): self
    {
        $this->callback = function (array $meta) use ($attributeName) {
            $json = json_encode($meta);
            $encoded = base64_encode($json !== false ? $json : '{}');

            return [
                '',
                '',
                [$attributeName => $encoded],
            ];
        };

        return $this;
    }

    /**
     * Use JSON data attribute instrumentation.
     *
     * @param  string  $attributeName  The attribute name to use (default: 'data-instrumentation')
     */
    public function asJsonAttribute(string $attributeName = 'data-instrumentation'): self
    {
        $this->callback = function (array $meta) use ($attributeName) {
            $json = json_encode($meta, JSON_HEX_APOS | JSON_HEX_QUOT);

            return [
                '',
                '',
                [$attributeName => $json],
            ];
        };

        return $this;
    }

    /**
     * Include or exclude line numbers from metadata.
     */
    public function withLineNumbers(bool $include = true): self
    {
        $this->includeLineNumbers = $include;

        return $this;
    }

    /**
     * Add extra metadata to include in markers.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $this->extraMeta = array_merge($this->extraMeta, $meta);

        return $this;
    }

    public function rewrite(Document $doc): Document
    {
        $this->ensureDefaultTypes();

        $rewriter = new Rewriter;

        $rewriter->addVisitor(
            new CallbackVisitor(
                enter: function (NodePath $path): void {
                    $this->handleNode($path);
                }
            )
        );

        return $rewriter->rewrite($doc);
    }

    private function handleNode(NodePath $path): void
    {
        $node = $path->node();
        $nodeType = $this->getNodeType($node);

        if ($nodeType === null || ! isset($this->typePatterns[$nodeType])) {
            return;
        }

        $name = $this->getNodeName($node, $nodeType);
        if ($name === null || ! $this->matchesPatterns($name, $this->typePatterns[$nodeType])) {
            return;
        }

        $meta = $this->buildMetadata($node, $nodeType, $name, $path);
        $markers = $this->getMarkers($meta);

        $start = $markers[0] ?? '';
        $end = $markers[1] ?? '';
        $attributes = $markers[2] ?? [];

        if (! empty($attributes) && $node instanceof ElementNode) {
            foreach ($attributes as $attrName => $attrValue) {
                $path->setAttribute($attrName, $attrValue);
            }
        }

        if ($start === '' && $end === '') {
            return;
        }

        $startSpec = $start !== '' ? Builder::raw($start) : Builder::raw('');
        $endSpec = $end !== '' ? Builder::raw($end) : Builder::raw('');
        $path->safeSurround($startSpec, $endSpec);
    }

    private function ensureDefaultTypes(): void
    {
        if (! empty($this->typePatterns)) {
            return;
        }

        $this->typePatterns = [
            'element' => ['*'],
            'component' => ['*'],
        ];
    }

    /**
     * Get the node type.
     */
    private function getNodeType(Node $node): ?string
    {
        if ($node instanceof ComponentNode) {
            return 'component';
        }

        if ($node instanceof ElementNode) {
            return 'element';
        }

        if ($node instanceof DirectiveBlockNode) {
            return 'directiveBlock';
        }

        if ($node instanceof DirectiveNode) {
            return 'directive';
        }

        return null;
    }

    /**
     * Get the name of the node for pattern matching.
     */
    private function getNodeName(Node $node, string $nodeType): ?string
    {
        return match ($nodeType) {
            'element', 'component' => $node instanceof ElementNode ? $node->tagNameText() : null,
            'directive' => $node instanceof DirectiveNode ? $node->nameText() : null,
            'directiveBlock' => $node instanceof DirectiveBlockNode ? $node->nameText() : null,
            default => null,
        };
    }

    /**
     * Check if the name matches any of the given patterns.
     *
     * @param  array<string>  $patterns
     */
    private function matchesPatterns(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build metadata array for the node.
     *
     * @return array<string, mixed>
     */
    private function buildMetadata(Node $node, string $nodeType, string $name, NodePath $path): array
    {
        $meta = [
            'type' => $nodeType,
            'name' => $name,
        ];

        if ($this->includeLineNumbers) {
            $meta['line'] = $node->startLine();
        }

        if ($node instanceof ComponentNode) {
            $meta['componentName'] = $node->getComponentName();
            $meta['componentType'] = $node->getType();
            $meta['prefix'] = $node->getPrefix();
        }

        if ($node instanceof DirectiveNode) {
            $meta['arguments'] = $node->arguments();
        }

        if ($node instanceof DirectiveBlockNode) {
            $meta['arguments'] = $node->arguments();
        }

        $meta['depth'] = $path->depth();

        return array_merge($meta, $this->extraMeta);
    }

    /**
     * Get the markers to use for instrumentation.
     *
     * @param  array<string, mixed>  $meta
     * @return array{0: string, 1: string, 2?: array<string, string>}
     */
    private function getMarkers(array $meta): array
    {
        if ($this->callback !== null) {
            /** @var array{0: string, 1: string, 2?: array<string, string>} */
            return ($this->callback)($meta);
        }

        $json = json_encode($meta);
        $encoded = base64_encode($json !== false ? $json : '{}');
        $prefix = $this->commentPrefix;

        return [
            "<!-- {$prefix}:start {$encoded} -->",
            "<!-- {$prefix}:end -->",
        ];
    }

    /**
     * Decode an instrumentation marker back to metadata.
     *
     * @param  string  $marker  The full comment string or just the encoded part
     * @return array<string, mixed>|null
     */
    public static function decode(string $marker): ?array
    {
        if (preg_match('/<!-- \w+:start ([A-Za-z0-9+\/=]+) -->/', $marker, $matches)) {
            $marker = $matches[1];
        }

        try {
            $decoded = json_decode(base64_decode($marker), true);

            if (is_array($decoded) && isset($decoded['type'])) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        } catch (Exception) {
            // Invalid encoding
        }

        return null;
    }
}
