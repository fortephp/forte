<?php

declare(strict_types=1);

namespace Forte\Extensions;

use Forte\Extensions\Concerns\HasConfiguration;
use Forte\Extensions\Concerns\HasDiagnostics;
use Forte\Extensions\Concerns\ProvidesDefaultExtensionImplementation;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Tokens\TokenTypeRegistry;

abstract class AbstractLexerExtension implements ForteExtension, LexerExtension
{
    use HasConfiguration;
    use HasDiagnostics;
    use ProvidesDefaultExtensionImplementation;

    /**
     * @var int[]
     */
    protected array $tokenTypes = [];

    private bool $typesRegistered = false;

    /**
     * @var array<string, true>|null
     */
    private ?array $triggerCharSet = null;

    /**
     * Unique identifier for this extension.
     */
    abstract public function id(): string;

    /**
     * Get characters that trigger this extension.
     */
    abstract public function triggerCharacters(): string;

    /**
     * Register custom token types.
     */
    abstract protected function registerTypes(TokenTypeRegistry $registry): void;

    /**
     * Attempt to tokenize at the current position.
     */
    abstract protected function doTokenize(LexerContext $ctx): bool;

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

        if ($this->triggerCharSet === null) {
            $this->triggerCharSet = [];
            foreach (str_split($this->triggerCharacters()) as $char) {
                $this->triggerCharSet[$char] = true;
            }
        }

        return isset($this->triggerCharSet[$current]);
    }

    public function tokenize(LexerContext $ctx): bool
    {
        return $this->doTokenize($ctx);
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
}
