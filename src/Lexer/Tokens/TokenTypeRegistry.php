<?php

declare(strict_types=1);

namespace Forte\Lexer\Tokens;

use RuntimeException;

class TokenTypeRegistry
{
    /** @var array<string, int> Maps "namespace:name" to type ID */
    private array $types = [];

    /** @var array<int, ExtensionTokenType> Maps ID to metadata */
    private array $metadata = [];

    private int $nextId = TokenType::EXTENSION_BASE;

    /**
     * Register a custom token type
     *
     * @param  string  $namespace  Extension namespace (e.g., 'livewire', 'shortcodes')
     * @param  string  $name  Type name within namespace (e.g., 'WireDirective')
     * @param  string|null  $label  Human-readable label for debugging (defaults to name)
     * @return int The assigned type ID (128-255)
     *
     * @throws RuntimeException When ID space is exhausted
     */
    public function register(string $namespace, string $name, ?string $label = null): int
    {
        $key = "{$namespace}:{$name}";

        if (isset($this->types[$key])) {
            return $this->types[$key];
        }

        if ($this->nextId > TokenType::EXTENSION_MAX) {
            throw new RuntimeException(
                sprintf(
                    'Extension token type limit exceeded. Maximum %d extension types allowed.',
                    TokenType::EXTENSION_MAX - TokenType::EXTENSION_BASE + 1
                )
            );
        }

        $id = $this->nextId++;
        $this->types[$key] = $id;
        $this->metadata[$id] = new ExtensionTokenType(
            id: $id,
            namespace: $namespace,
            name: $name,
            label: $label ?? $name
        );

        return $id;
    }

    /**
     * Get the label for an extension token type.
     */
    public function label(int $id): string
    {
        /** @phpstan-ignore nullsafe.neverNull */
        return $this->metadata[$id]?->label ?? "Extension({$id})";
    }

    /**
     * Get full metadata for an extension token type.
     */
    public function get(int $id): ?ExtensionTokenType
    {
        return $this->metadata[$id] ?? null;
    }

    /**
     * Check if a type ID has been registered.
     */
    public function has(int $id): bool
    {
        return isset($this->metadata[$id]);
    }

    /**
     * Get all registered extension types.
     *
     * @return array<int, ExtensionTokenType>
     */
    public function all(): array
    {
        return $this->metadata;
    }

    /**
     * Get the type ID for a namespace:name key.
     */
    public function getId(string $namespace, string $name): ?int
    {
        return $this->types["{$namespace}:{$name}"] ?? null;
    }

    /**
     * Get type ID by full key (e.g., "hashtag:Hashtag").
     */
    public function getIdByKey(string $key): ?int
    {
        return $this->types[$key] ?? null;
    }

    /**
     * Check if a token matches a string identifier.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public function matches(array $token, string $key): bool
    {
        $id = $this->types[$key] ?? null;

        return $id !== null && $token['type'] === $id;
    }
}
