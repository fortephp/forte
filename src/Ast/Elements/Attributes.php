<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Document;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\EchoNode;
use Forte\Ast\Node;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use Illuminate\Support\Collection;
use Traversable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 *
 * @extends Collection<int, Attribute>
 */
class Attributes extends Collection
{
    private ?Document $document = null;

    private ?int $elementIndex = null;

    /** @var array<string, Attribute>|null */
    private ?array $byName = null;

    private int $trailingWhitespaceIdx = -1;

    private bool $loaded = false;

    /**
     * @param  Document|iterable<int, Attribute>  $items
     */
    public function __construct(Document|iterable $items = [], ?int $elementIndex = null)
    {
        if ($items instanceof Document) {
            $this->document = $items;
            $this->elementIndex = $elementIndex;
            parent::__construct([]);
        } else {
            /** @var iterable<int, Attribute> $items */
            parent::__construct($items);
            $this->loaded = true;
        }
    }

    /**
     * Get all attributes.
     *
     * @return array<int, Attribute>
     */
    public function all(): array
    {
        $this->ensureLoaded();

        return array_values($this->items);
    }

    /**
     * Get an attribute by name..
     *
     * @param  string|int  $key
     * @param  mixed  $default
     */
    public function get($key, $default = null): mixed
    {
        $this->ensureLoaded();

        if (is_string($key)) {
            return $this->byName[strtolower($key)] ?? $default;
        }

        return parent::get($key, $default);
    }

    /**
     * Check if an attribute exists by name or index.
     *
     * @param  string|int  $key
     */
    public function has($key): bool
    {
        $this->ensureLoaded();

        if (is_string($key)) {
            return isset($this->byName[strtolower($key)]);
        }

        return parent::has($key);
    }

    /**
     * Get the number of attributes.
     */
    public function count(): int
    {
        $this->ensureLoaded();

        return parent::count();
    }

    /**
     * Check if there are no attributes.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Get iterator for attributes.
     */
    public function getIterator(): Traversable
    {
        $this->ensureLoaded();

        return parent::getIterator();
    }

    /**
     * Check if any attribute is a Blade construct (echo, directive, PHP tag).
     */
    public function hasBladeConstruct(): bool
    {
        $this->ensureLoaded();

        return $this->contains(fn (Attribute $attr) => $attr->isBladeConstruct());
    }

    /**
     * Check if any attribute is an expression attribute ({...}).
     */
    public function hasExpression(): bool
    {
        $this->ensureLoaded();

        return $this->contains(fn (Attribute $attr) => $attr->isExpression());
    }

    /**
     * Get all standalone Blade construct attributes (getEchoes, directives, PHP tags).
     */
    public function bladeConstructs(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isBladeConstruct());
    }

    /**
     * Get all block directive attributes (@if...@endif, @foreach...@endforeach, etc).
     */
    public function blockDirectives(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isBladeConstruct() &&
            $attr->getBladeConstruct() instanceof DirectiveBlockNode
        );
    }

    /**
     * Get all non-block directive attributes (@csrf, @class, etc).
     */
    public function directives(): static
    {
        $this->ensureLoaded();

        return $this->filter(function (Attribute $attr) {
            if (! $attr->isBladeConstruct()) {
                return false;
            }

            return $attr->getBladeConstruct() instanceof DirectiveNode;
        });
    }

    /**
     * Get all echo attributes ({{ }}, {!! !!}, {{{ }}}).
     */
    public function echoes(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isBladeConstruct() && $attr->getBladeConstruct() instanceof EchoNode);
    }

    /**
     * Get all PHP tag attributes (<?php ?>, <?= ?>).
     */
    public function phpTags(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isBladeConstruct() &&
            ($attr->getBladeConstruct() instanceof PhpTagNode ||
             $attr->getBladeConstruct() instanceof PhpBlockNode)
        );
    }

    /**
     * Get all static attributes (name="value").
     */
    public function static(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isStatic());
    }

    /**
     * Get all bound attributes (:name="value").
     */
    public function bound(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isBound());
    }

    /**
     * Get all escaped attributes (::name="value").
     */
    public function escaped(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isEscaped());
    }

    /**
     * Get all boolean attributes (no value, e.g., disabled, required).
     */
    public function boolean(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->isBoolean());
    }

    /**
     * Get all attributes with complex/interpolated names or values.
     */
    public function complex(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => $attr->hasComplexName() || $attr->hasComplexValue()
        );
    }

    /**
     * Get all simple attributes.
     */
    public function simple(): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => ! $attr->isBladeConstruct() &&
            ! $attr->hasComplexName() &&
            ! $attr->hasComplexValue()
        );
    }

    /**
     * Get attributes matching a specific name.
     */
    public function whereNameIs(string $name): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => strcasecmp($attr->nameText(), $name) === 0
        );
    }

    /**
     * Get attributes matching a regex pattern.
     */
    public function whereNameMatches(string $pattern): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => preg_match($pattern, $attr->nameText()) === 1
        );
    }

    /**
     * Get directive attributes with a specific directive name.
     */
    public function whereDirectiveName(string $name): static
    {
        $this->ensureLoaded();

        return $this->filter(function (Attribute $attr) use ($name) {
            if (! $attr->isBladeConstruct()) {
                return false;
            }

            $node = $attr->getBladeConstruct();

            if (! ($node instanceof DirectiveNode) && ! ($node instanceof DirectiveBlockNode)) {
                return false;
            }

            return $node->nameText() === $name;
        });
    }

    /**
     * Find an attribute by name (case-insensitive).
     */
    public function find(string $name): ?Attribute
    {
        $this->ensureLoaded();

        return $this->byName[strtolower($name)] ?? null;
    }

    /**
     * Get attributes excluding those with the specified name(s).
     *
     * @param  array<string>|string  $names  Attribute name(s) to exclude
     */
    public function exceptNames(array|string $names): static
    {
        $this->ensureLoaded();

        $names = array_map(strtolower(...), (array) $names);

        return $this->filter(
            fn (Attribute $attr) => ! in_array(strtolower($attr->nameText()), $names, true)
        );
    }

    /**
     * Get only attributes with the specified name(s).
     *
     * @param  array<string>|string  $names  Attribute name(s) to include
     */
    public function onlyNames(array|string $names): static
    {
        $this->ensureLoaded();

        $names = array_map(strtolower(...), (array) $names);

        return $this->filter(
            fn (Attribute $attr) => in_array(strtolower($attr->nameText()), $names, true)
        );
    }

    /**
     * Extract the underlying AST nodes from blade construct attributes.
     *
     * @return NodeCollection<int, Node>
     */
    public function getBladeConstructs(): NodeCollection
    {
        /** @var array<int, Node> $items */
        $items = $this->bladeConstructs()
            ->map(fn (Attribute $attr) => $attr->getBladeConstruct())
            ->filter()
            ->values()
            ->all();

        /** @var NodeCollection<int, Node> */
        return NodeCollection::make($items);
    }

    /**
     * Get the trailing whitespace after all attributes.
     */
    public function trailingWhitespace(): string
    {
        $this->ensureLoaded();

        if ($this->document === null || $this->trailingWhitespaceIdx === -1) {
            return '';
        }

        $wsNode = $this->document->getFlatNode($this->trailingWhitespaceIdx);
        $tokens = $this->document->getTokens();
        $source = $this->document->source();

        $startToken = $tokens[$wsNode['tokenStart']];
        $endToken = $tokens[$wsNode['tokenStart'] + $wsNode['tokenCount'] - 1];

        return substr($source, $startToken['start'], $endToken['end'] - $startToken['start']);
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $this->byName = [];

        if ($this->document === null || $this->elementIndex === null) {
            return;
        }

        $elementNode = $this->document->getFlatNode($this->elementIndex);
        $childIdx = $elementNode['firstChild'];

        $openingTagEndPos = $this->document
            ->findOpeningTagEndPosition($this->elementIndex);
        $tokens = $this->document->getTokens();

        $lastWhitespaceIdx = -1;

        while ($childIdx !== -1) {
            $childNode = $this->document->getFlatNode($childIdx);
            $kind = $childNode['kind'];

            if ($kind === NodeKind::ElementName) {
                $childIdx = $childNode['nextSibling'];

                continue;
            }

            if ($kind === NodeKind::ClosingElementName) {
                break;
            }

            $tokenStart = $childNode['tokenStart'];
            if ($tokenStart < 0) {
                break;
            }

            $childStartPos = $tokens[$tokenStart]['start'];
            if ($childStartPos >= $openingTagEndPos) {
                break;
            }

            switch (true) {
                case $kind === NodeKind::AttributeWhitespace:
                    $lastWhitespaceIdx = $childIdx;
                    break;

                case $kind === NodeKind::Attribute:
                case NodeKind::isExtension($kind) && NodeKind::isAttribute($kind):
                case $this->isStandaloneAttributeKind($kind):
                    $isExtensionAttribute = NodeKind::isExtension($kind) && NodeKind::isAttribute($kind);
                    $isStandalone = ($kind !== NodeKind::Attribute && ! $isExtensionAttribute);

                    $attr = new Attribute($this->document, $childIdx, $lastWhitespaceIdx, $isStandalone);
                    $this->items[] = $attr;

                    if (! $isStandalone) {
                        $name = $attr->nameText();
                        if ($name !== '') {
                            $this->byName[strtolower($name)] = $attr;
                        }
                    }

                    $lastWhitespaceIdx = -1;
                    break;
            }

            $childIdx = $childNode['nextSibling'];
        }

        if ($lastWhitespaceIdx !== -1) {
            $this->trailingWhitespaceIdx = $lastWhitespaceIdx;
        }
    }

    private function isStandaloneAttributeKind(int $kind): bool
    {
        return $kind === NodeKind::Echo
            || $kind === NodeKind::RawEcho
            || $kind === NodeKind::TripleEcho
            || $kind === NodeKind::Directive
            || $kind === NodeKind::DirectiveBlock
            || $kind === NodeKind::PhpTag
            || $kind === NodeKind::PhpBlock
            || $kind === NodeKind::JsxAttribute;
    }
}
