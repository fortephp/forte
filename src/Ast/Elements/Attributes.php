<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
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
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Traversable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 *
 * @implements IteratorAggregate<int, Attribute>
 * @implements ArrayAccess<int, Attribute>
 */
class Attributes implements ArrayAccess, Countable, IteratorAggregate
{
    private ?Document $document = null;

    private ?int $elementIndex = null;

    /** @var array<string, Attribute>|null */
    private ?array $byName = null;

    private int $trailingWhitespaceIdx = -1;

    private bool $loaded = false;

    /** @var array<int, Attribute> */
    private array $items = [];

    /**
     * @param  Document|iterable<int, Attribute>  $items
     */
    public function __construct(Document|iterable $items = [], ?int $elementIndex = null)
    {
        if ($items instanceof Document) {
            $this->document = $items;
            $this->elementIndex = $elementIndex;

            return;
        }

        $this->loaded = true;
        $this->items = $this->normalizeItems($items);
        $this->buildNameIndex();
    }

    /**
     * Get all attributes.
     *
     * @return array<int, Attribute>
     */
    public function all(): array
    {
        $this->ensureLoaded();

        return $this->items;
    }

    /**
     * Get an attribute by name or position.
     *
     * @template TDefault
     *
     * @param  string|int  $key
     * @param  TDefault|Closure(): TDefault  $default
     * @return Attribute|TDefault
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();

        if (is_string($key)) {
            return $this->byName[strtolower($key)] ?? self::resolveDefault($default);
        }

        if (is_int($key) && array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return self::resolveDefault($default);
    }

    /**
     * Check if an attribute exists by name or index.
     *
     * @param  string|int  $key
     */
    public function has(mixed $key): bool
    {
        $this->ensureLoaded();

        if (is_string($key)) {
            return isset($this->byName[strtolower($key)]);
        }

        return is_int($key) && array_key_exists($key, $this->items);
    }

    /**
     * Check whether at least one named attribute exists.
     *
     * @param  string|array<string>  $names
     */
    public function hasAny(string|array $names): bool
    {
        $names = is_string($names) ? [$names] : $names;

        foreach ($names as $name) {
            if ($this->has($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether every named attribute exists.
     *
     * @param  string|array<string>  $names
     */
    public function hasAll(string|array $names): bool
    {
        $names = is_string($names) ? [$names] : $names;

        foreach ($names as $name) {
            if (! $this->has($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the first attribute, optionally matching a callback.
     *
     * @template TDefault
     *
     * @param  (callable(Attribute, int): bool)|null  $callback
     * @param  TDefault|Closure(): TDefault  $default
     * @return Attribute|TDefault
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->toCollection()->first($callback, $default);
    }

    /**
     * Get the last attribute, optionally matching a callback.
     *
     * @template TDefault
     *
     * @param  (callable(Attribute, int): bool)|null  $callback
     * @param  TDefault|Closure(): TDefault  $default
     * @return Attribute|TDefault
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->toCollection()->last($callback, $default);
    }

    /**
     * Map attributes into a base collection.
     *
     * @template TMapped
     *
     * @param  callable(Attribute, int): TMapped  $callback
     * @return Collection<int, TMapped>
     */
    public function map(callable $callback): Collection
    {
        return $this->toCollection()->map($callback);
    }

    /**
     * Filter attributes into a new lazy-safe attributes bag.
     *
     * @param  (callable(Attribute, int): bool)|null  $callback
     */
    public function filter(?callable $callback = null): static
    {
        return $this->newFromItems(
            $this->toCollection()->filter($callback)->values()->all()
        );
    }

    /**
     * Return the attributes as a dense ordered bag.
     */
    public function values(): static
    {
        return $this->newFromItems($this->toCollection()->values()->all());
    }

    /**
     * Convert attributes to a detached base collection snapshot.
     *
     * @return Collection<int, Attribute>
     */
    public function toCollection(): Collection
    {
        $this->ensureLoaded();

        return new Collection($this->items);
    }

    /**
     * Get the number of attributes.
     */
    public function count(): int
    {
        $this->ensureLoaded();

        return count($this->items);
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

        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        $this->ensureLoaded();

        return is_int($offset) && array_key_exists($offset, $this->items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $this->ensureLoaded();

        if (! is_int($offset)) {
            return null;
        }

        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->ensureLoaded();

        $attribute = $this->assertAttribute($value);

        if ($offset === null) {
            $this->items[] = $attribute;
        } else {
            if (! is_int($offset)) {
                throw new InvalidArgumentException('Attributes array access only supports integer positions. Use get(), has(), or find() for name-based lookups.');
            }

            if (! array_key_exists($offset, $this->items)) {
                throw new OutOfBoundsException("Attribute offset {$offset} does not exist.");
            }

            $this->items[$offset] = $attribute;
        }

        $this->detachFromSource();
        $this->buildNameIndex();
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->ensureLoaded();

        if (! is_int($offset)) {
            return;
        }

        if (! array_key_exists($offset, $this->items)) {
            return;
        }

        unset($this->items[$offset]);
        $this->items = array_values($this->items);

        $this->detachFromSource();
        $this->buildNameIndex();
    }

    /**
     * Check if any attribute is a Blade construct (echo, directive, PHP tag).
     */
    public function hasBladeConstruct(): bool
    {
        return $this->toCollection()->contains(fn (Attribute $attr) => $attr->isBladeConstruct());
    }

    /**
     * Check if any attribute is an expression attribute ({...}).
     */
    public function hasExpression(): bool
    {
        return $this->toCollection()->contains(fn (Attribute $attr) => $attr->isExpression());
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

        return $this->filter(fn (Attribute $attr) => ! $attr->isBladeConstruct() &&
            ($attr->hasComplexName() || $attr->hasComplexValue())
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

        return $this->filter(fn (Attribute $attr) => $attr->isNamed($name));
    }

    /**
     * Find the first attribute with the given name (case-insensitive).
     *
     * This models HTML's effective first-duplicate behavior without changing
     * the historical find()/get() lookup behavior.
     */
    public function firstNamed(string $name): ?Attribute
    {
        $this->ensureLoaded();

        foreach ($this->items as $attribute) {
            if (! $attribute->isBladeConstruct() && $attribute->isNamed($name)) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Get attributes matching a regex pattern.
     */
    public function whereNameMatches(string $pattern): static
    {
        $this->ensureLoaded();

        return $this->filter(fn (Attribute $attr) => preg_match($pattern, $attr->nameText()) === 1);
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
     * Extract internal attribute nodes (compound names/values and blade-attribute internals).
     *
     * @return NodeCollection<int, Node>
     */
    public function internalNodes(): NodeCollection
    {
        $this->ensureLoaded();

        $items = [];
        foreach ($this->items as $attribute) {
            foreach ($attribute->internalNodes() as $node) {
                $items[] = $node;
            }
        }

        /** @var NodeCollection<int, Node> */
        return NodeCollection::make($items);
    }

    /**
     * Extract all echo nodes found in attribute internals.
     *
     * @return NodeCollection<int, EchoNode>
     */
    public function internalEchoes(): NodeCollection
    {
        /** @var NodeCollection<int, EchoNode> */
        return $this->internalNodes()->ofType(EchoNode::class);
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
        $tokens = $this->document->getTokenRecords();
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
        $this->items = [];
        $this->byName = [];

        if ($this->document === null || $this->elementIndex === null) {
            return;
        }

        $elementNode = $this->document->getFlatNode($this->elementIndex);
        $childIdx = $elementNode['firstChild'];

        $openingTagEndPos = $this->document
            ->findOpeningTagEndPosition($this->elementIndex);
        $tokens = $this->document->getTokenRecords();

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

    private function buildNameIndex(): void
    {
        $this->byName = [];

        foreach ($this->items as $attribute) {
            if ($attribute->isBladeConstruct()) {
                continue;
            }

            $name = $attribute->nameText();

            if ($name !== '') {
                $this->byName[strtolower($name)] = $attribute;
            }
        }
    }

    /**
     * @param  iterable<int, Attribute>  $items
     */
    private function newFromItems(iterable $items): static
    {
        $attributes = clone $this;
        $attributes->items = $this->normalizeItems($items);
        $attributes->loaded = true;
        $attributes->detachFromSource();
        $attributes->buildNameIndex();

        return $attributes;
    }

    private function detachFromSource(): void
    {
        $this->document = null;
        $this->elementIndex = null;
        $this->trailingWhitespaceIdx = -1;
    }

    /**
     * @param  iterable<int, Attribute>  $items
     * @return array<int, Attribute>
     */
    private function normalizeItems(iterable $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = $this->assertAttribute($item);
        }

        return $normalized;
    }

    private function assertAttribute(mixed $value): Attribute
    {
        if (! $value instanceof Attribute) {
            throw new InvalidArgumentException('Attributes only accept Attribute instances.');
        }

        return $value;
    }

    private static function resolveDefault(mixed $default): mixed
    {
        return $default instanceof Closure ? $default() : $default;
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
