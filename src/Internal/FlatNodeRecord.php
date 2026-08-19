<?php

declare(strict_types=1);

namespace Forte\Internal;

use ArrayAccess;
use LogicException;
use TypeError;

/**
 * Compact internal flat-node storage.
 *
 * @internal
 *
 * @implements ArrayAccess<string, int>
 */
final class FlatNodeRecord implements ArrayAccess
{
    /** @var array<string, true> */
    private const STRUCTURAL_FIELDS = [
        'kind' => true,
        'parent' => true,
        'firstChild' => true,
        'lastChild' => true,
        'nextSibling' => true,
        'tokenStart' => true,
        'tokenCount' => true,
        'genericOffset' => true,
        'data' => true,
    ];

    /** @var array<string, mixed>|null */
    private ?array $extra = null;

    public function __construct(
        public int $kind,
        public int $parent,
        public int $firstChild,
        public int $lastChild,
        public int $nextSibling,
        public int $tokenStart,
        public int $tokenCount,
        public int $genericOffset,
        public int $data,
    ) {}

    /**
     * @param  array{kind: int, parent: int, firstChild: int, lastChild: int, nextSibling: int, tokenStart: int, tokenCount: int, genericOffset: int, data: int, ...<string, mixed>}  $node
     */
    public static function fromArray(array $node): self
    {
        $record = new self(
            $node['kind'],
            $node['parent'],
            $node['firstChild'],
            $node['lastChild'],
            $node['nextSibling'],
            $node['tokenStart'],
            $node['tokenCount'],
            $node['genericOffset'],
            $node['data'],
        );

        if (count($node) > count(self::STRUCTURAL_FIELDS)) {
            foreach ($node as $key => $value) {
                if (! self::isStructuralField($key)) {
                    $record->extra[$key] = $value;
                }
            }
        }

        return $record;
    }

    /**
     * @return array{kind: int, parent: int, firstChild: int, lastChild: int, nextSibling: int, tokenStart: int, tokenCount: int, genericOffset: int, data: int}
     */
    public function toArray(): array
    {
        $node = [
            'kind' => $this->kind,
            'parent' => $this->parent,
            'firstChild' => $this->firstChild,
            'lastChild' => $this->lastChild,
            'nextSibling' => $this->nextSibling,
            'tokenStart' => $this->tokenStart,
            'tokenCount' => $this->tokenCount,
            'genericOffset' => $this->genericOffset,
            'data' => $this->data,
        ];

        return $node + ($this->extra ?? []);
    }

    public function offsetExists(mixed $offset): bool
    {
        return match ($offset) {
            'kind', 'parent', 'firstChild', 'lastChild', 'nextSibling',
            'tokenStart', 'tokenCount', 'genericOffset', 'data' => true,
            default => false,
        };
    }

    public function &offsetGet(mixed $offset): int
    {
        switch ($offset) {
            case 'kind':
                return $this->kind;
            case 'parent':
                return $this->parent;
            case 'firstChild':
                return $this->firstChild;
            case 'lastChild':
                return $this->lastChild;
            case 'nextSibling':
                return $this->nextSibling;
            case 'tokenStart':
                return $this->tokenStart;
            case 'tokenCount':
                return $this->tokenCount;
            case 'genericOffset':
                return $this->genericOffset;
            case 'data':
                return $this->data;
            default:
                throw new LogicException("Unknown structural flat-node field: {$offset}");
        }
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (! is_int($value)) {
            throw new TypeError('Structural flat-node fields must be integers.');
        }

        match ($offset) {
            'kind' => $this->kind = $value,
            'parent' => $this->parent = $value,
            'firstChild' => $this->firstChild = $value,
            'lastChild' => $this->lastChild = $value,
            'nextSibling' => $this->nextSibling = $value,
            'tokenStart' => $this->tokenStart = $value,
            'tokenCount' => $this->tokenCount = $value,
            'genericOffset' => $this->genericOffset = $value,
            'data' => $this->data = $value,
            default => throw new LogicException("Unknown structural flat-node field: {$offset}"),
        };
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Structural flat-node fields cannot be removed.');
    }

    private static function isStructuralField(string $field): bool
    {
        return isset(self::STRUCTURAL_FIELDS[$field]);
    }
}
