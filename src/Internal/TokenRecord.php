<?php

declare(strict_types=1);

namespace Forte\Internal;

use ArrayAccess;
use LogicException;

/**
 * Compact internal token storage.
 *
 * @internal
 *
 * @implements ArrayAccess<'type'|'start'|'end', int>
 */
final readonly class TokenRecord implements ArrayAccess
{
    public function __construct(
        public int $type,
        public int $start,
        public int $end,
    ) {}

    /**
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function fromArray(array $token): self
    {
        return new self($token['type'], $token['start'], $token['end']);
    }

    /**
     * @return array{type: int, start: int, end: int}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'start' => $this->start, 'end' => $this->end];
    }

    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'type' || $offset === 'start' || $offset === 'end';
    }

    public function offsetGet(mixed $offset): int
    {
        return match ($offset) {
            'type' => $this->type,
            'start' => $this->start,
            'end' => $this->end,
            default => throw new LogicException("Unknown token field: {$offset}"),
        };
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Token records are immutable.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Token records are immutable.');
    }
}
