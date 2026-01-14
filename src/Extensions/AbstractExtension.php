<?php

declare(strict_types=1);

namespace Forte\Extensions;

use Forte\Extensions\Concerns\HasConfiguration;
use Forte\Extensions\Concerns\HasDiagnostics;
use Forte\Extensions\Concerns\ProvidesDefaultExtensionImplementation;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Tokens\TokenType;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\NodeKind;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\TreeBuilder;
use RuntimeException;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
abstract class AbstractExtension implements ForteExtension, LexerExtension, TreeExtension
{
    use HasConfiguration;
    use HasDiagnostics;
    use ProvidesDefaultExtensionImplementation;

    /**
     * @var int[]
     */
    protected array $tokenTypes = [];

    /**
     * @var int[]
     */
    protected array $nodeKinds = [];

    private bool $typesRegistered = false;

    private bool $kindsRegistered = false;

    abstract public function id(): string;

    /**
     * Characters that trigger this extension's tokenizer.
     */
    abstract public function triggerCharacters(): string;

    /**
     * Attempt to tokenize at the current position.
     *
     * @param  LexerContext  $ctx  The lexer context with position and emit methods
     */
    abstract protected function doTokenize(LexerContext $ctx): bool;

    /**
     * Registers custom token types.
     */
    abstract protected function registerTypes(TokenTypeRegistry $registry): void;

    /**
     * Register custom node kinds.
     */
    protected function registerKinds(NodeKindRegistry $registry): void {}

    /**
     * Check if this extension can handle the current token.
     */
    public function canHandle(TreeContext $ctx): bool
    {
        $token = $ctx->currentToken();

        return $token && in_array($token['type'], $this->tokenTypes, true);
    }

    protected function doHandle(TreeContext $ctx): int
    {
        if (empty($this->nodeKinds)) {
            throw new RuntimeException(
                "No node kinds registered for extension '{$this->id()}'. ".
                'Register node kinds in registerKinds() or override doHandle().'
            );
        }

        $kind = $this->nodeKinds[0];
        $nodeIdx = $ctx->addNode($kind, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }

    public function priority(): int
    {
        return 0;
    }

    public function registerTokenTypes(TokenTypeRegistry $registry): array
    {
        if (! $this->typesRegistered) {
            $this->registerTypes($registry);
            $this->typesRegistered = true;
        }

        return $this->tokenTypes;
    }

    public function shouldActivate(LexerContext $ctx): bool
    {
        $current = $ctx->current();
        if ($current === null) {
            return false;
        }

        return str_contains($this->triggerCharacters(), $current);
    }

    public function tokenize(LexerContext $ctx): bool
    {
        return $this->doTokenize($ctx);
    }

    public function registerNodeKinds(NodeKindRegistry $registry): void
    {
        if (! $this->kindsRegistered) {
            $this->registerKinds($registry);
            $this->kindsRegistered = true;
        }
    }

    public function handle(TreeContext $ctx): int
    {
        return $this->doHandle($ctx);
    }

    /**
     * Register a token type and store its ID.
     */
    protected function registerType(TokenTypeRegistry $registry, string $name, ?string $label = null): int
    {
        $id = $registry->register($this->id(), $name, $label);
        $this->tokenTypes[] = $id;

        return $id;
    }

    /**
     * Register a node kind and store its ID.
     *
     * @param  class-string|null  $nodeClass
     */
    protected function registerKind(
        NodeKindRegistry $registry,
        string $name,
        ?string $nodeClass = null,
        ?string $label = null
    ): int {
        $id = $registry->register($this->id(), $name, $nodeClass, $label);
        $this->nodeKinds[] = $id;

        return $id;
    }

    /**
     * Get the string key for a registered token type.
     *
     * @param  string  $name  The type name (registered via registerType)
     */
    protected function typeKey(string $name): string
    {
        return "{$this->id()}:{$name}";
    }

    /**
     * Get the string key for a registered node kind.
     *
     * @param  string  $name  The kind name (registered via registerKind)
     */
    protected function kindKey(string $name): string
    {
        return "{$this->id()}::{$name}";
    }

    /**
     * Check if a token is one of this extension's types by name.
     *
     * @param  array{type: int, start: int, end: int}  $token  The token array
     * @param  string  $name  The type name (registered via registerType)
     */
    protected function isTokenType(array $token, string $name): bool
    {
        return TokenType::is($token, $this->typeKey($name));
    }

    /**
     * Check if a node is one of this extension's kinds by name.
     *
     * @param  string  $name  The kind name (registered via registerKind)
     *
     * @phpstan-param  FlatNode  $node  The node array
     */
    protected function isNodeKind(array $node, string $name): bool
    {
        return NodeKind::is($node, $this->kindKey($name));
    }

    /**
     * Get all registered token types for this extension.
     *
     * @return int[]
     */
    public function getRegisteredTokenTypes(): array
    {
        return $this->tokenTypes;
    }

    /**
     * Get all registered node kinds for this extension.
     *
     * @return int[]
     */
    public function getRegisteredNodeKinds(): array
    {
        return $this->nodeKinds;
    }

    /**
     * Check if a token type is one of this extension's registered types.
     */
    public function hasTokenType(int $type): bool
    {
        return in_array($type, $this->tokenTypes, true);
    }

    /**
     * Check if a node kind is one of this extension's registered kinds.
     */
    public function hasNodeKind(int $kind): bool
    {
        return in_array($kind, $this->nodeKinds, true);
    }
}
