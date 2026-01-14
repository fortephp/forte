<?php

declare(strict_types=1);

namespace Forte\Extensions;

use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\TreeBuilder;
use RuntimeException;

class ExtensionRegistry
{
    /**
     * @var array<string, ForteExtension>
     */
    private array $extensions = [];

    /**
     * @var string[]
     */
    private array $order = [];

    /**
     * Whether extensions have been resolved.
     */
    private bool $resolved = false;

    /**
     * @var string[]
     */
    private array $resolvedOrder = [];

    private bool $materialized = false;

    public function __construct(
        private readonly TokenTypeRegistry $tokenRegistry,
        private readonly NodeKindRegistry $nodeRegistry
    ) {}

    /**
     * Get the token type registry.
     */
    public function getTokenRegistry(): TokenTypeRegistry
    {
        return $this->tokenRegistry;
    }

    /**
     * Get the node kind registry.
     */
    public function getNodeRegistry(): NodeKindRegistry
    {
        return $this->nodeRegistry;
    }

    /**
     * Register an extension.
     *
     * @throws RuntimeException
     */
    public function register(ForteExtension $extension): self
    {
        $id = $extension->id();

        if (isset($this->extensions[$id])) {
            throw new RuntimeException("Extension '{$id}' is already registered");
        }

        $this->extensions[$id] = $extension;
        $this->order[] = $id;
        $this->resolved = false;
        $this->materialized = false;

        return $this;
    }

    /**
     * Check if an extension is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->extensions[$id]);
    }

    /**
     * Get an extension by ID.
     */
    public function get(string $id): ?ForteExtension
    {
        return $this->extensions[$id] ?? null;
    }

    /**
     * Get all registered extensions.
     *
     * @return ForteExtension[]
     */
    public function all(): array
    {
        $this->ensureResolved();
        $this->ensureMaterialized();

        $result = [];
        foreach ($this->resolvedOrder as $id) {
            $result[] = $this->extensions[$id];
        }

        return $result;
    }

    /**
     * Get extensions with a specific capability.
     *
     * @template T
     *
     * @param  class-string<T>  $interface
     * @return T[]
     */
    public function extensionOfType(string $interface): array
    {
        $this->ensureResolved();

        $result = [];
        foreach ($this->resolvedOrder as $id) {
            $extension = $this->extensions[$id];
            if ($extension instanceof $interface) {
                $result[] = $extension;
            }
        }

        return $result;
    }

    /**
     * Configure a lexer with registered extensions.
     */
    public function configureLexer(Lexer $lexer): void
    {
        $tokenRegistry = $this->getTokenRegistry();

        foreach ($this->extensionOfType(LexerExtension::class) as $extension) {
            $lexer->registerExtension($extension, $tokenRegistry);
        }

        foreach ($this->extensionOfType(AttributeExtension::class) as $extension) {
            $lexer->registerAttributeExtension($extension, $tokenRegistry);
        }
    }

    /**
     * Configure a tree builder with registered extensions.
     */
    public function configureTreeBuilder(TreeBuilder $builder): void
    {
        $nodeRegistry = $this->getNodeRegistry();

        foreach ($this->extensionOfType(TreeExtension::class) as $extension) {
            $builder->registerExtension($extension, $nodeRegistry);
        }

        // Configure attribute extensions
        foreach ($this->extensionOfType(AttributeExtension::class) as $extension) {
            $builder->registerAttributeExtension($extension, $nodeRegistry);
        }
    }

    /**
     * @throws RuntimeException
     */
    private function ensureResolved(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->assertDependenciesPresent();
        $this->assertNoConflicts();

        $this->resolvedOrder = $this->topologicalSort();
        $this->resolved = true;
    }

    /**
     * Materialize all extensions by registering their token types and node kinds.
     */
    private function ensureMaterialized(): void
    {
        if ($this->materialized) {
            return;
        }

        $tokenRegistry = $this->getTokenRegistry();
        $nodeRegistry = $this->getNodeRegistry();

        foreach ($this->resolvedOrder as $id) {
            $extension = $this->extensions[$id];

            if ($extension instanceof LexerExtension) {
                $extension->registerTokenTypes($tokenRegistry);
            }

            if ($extension instanceof TreeExtension) {
                $extension->registerNodeKinds($nodeRegistry);
            }

            if ($extension instanceof AttributeExtension) {
                $extension->registerAttributeTokenType($tokenRegistry);
                $extension->registerAttributeNodeKind($nodeRegistry);
            }
        }

        $this->materialized = true;
    }

    /**
     * Topological sort of extensions by dependencies.
     *
     * @return string[]
     */
    private function topologicalSort(): array
    {
        $result = [];
        $visited = [];
        $temporary = [];

        $visit = function (string $id) use (&$visit, &$result, &$visited, &$temporary): void {
            if (isset($temporary[$id])) {
                throw new RuntimeException("Circular dependency detected involving '{$id}'");
            }

            if (isset($visited[$id])) {
                return;
            }

            $temporary[$id] = true;

            $extension = $this->extensions[$id];
            foreach ($extension->dependencies() as $dependencyId) {
                $visit($dependencyId);
            }

            unset($temporary[$id]);
            $visited[$id] = true;
            $result[] = $id;
        };

        foreach ($this->order as $id) {
            $visit($id);
        }

        return $result;
    }

    /**
     * @throws RuntimeException
     */
    private function assertDependenciesPresent(): void
    {
        foreach ($this->extensions as $id => $extension) {
            foreach ($extension->dependencies() as $dependencyId) {
                if (! isset($this->extensions[$dependencyId])) {
                    throw new RuntimeException(
                        "Extension '{$id}' depends on '{$dependencyId}' which is not registered"
                    );
                }
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function assertNoConflicts(): void
    {
        foreach ($this->extensions as $id => $extension) {
            foreach ($extension->conflicts() as $conflictId) {
                if (isset($this->extensions[$conflictId])) {
                    throw new RuntimeException(
                        "Extension '{$id}' conflicts with '{$conflictId}'"
                    );
                }
            }
        }
    }
}
