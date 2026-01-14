<?php

declare(strict_types=1);

namespace Forte\Lexer\Extension;

use Forte\Lexer\Tokens\TokenTypeRegistry;

class ExtensionStack
{
    /**
     * @var LexerExtension[]
     */
    private array $extensions = [];

    private bool $sorted = true;

    /**
     * Add an extension to the stack.
     */
    public function add(LexerExtension $extension): self
    {
        $this->extensions[] = $extension;
        $this->sorted = false;

        return $this;
    }

    /**
     * Register token types for all extensions.
     */
    public function registerTokenTypes(TokenTypeRegistry $registry): void
    {
        foreach ($this->extensions as $extension) {
            $extension->registerTokenTypes($registry);
        }
    }

    /**
     * Check if the stack has any extensions.
     */
    public function isEmpty(): bool
    {
        return empty($this->extensions);
    }

    /**
     * Get the number of extensions.
     */
    public function count(): int
    {
        return count($this->extensions);
    }

    /**
     * Get all extensions sorted by priority (highest first).
     *
     * @return LexerExtension[]
     */
    public function all(): array
    {
        $this->ensureSorted();

        return $this->extensions;
    }

    /**
     * Try to activate and tokenize with an extension.
     */
    public function tryTokenize(LexerContext $ctx): bool
    {
        $this->ensureSorted();

        foreach ($this->extensions as $extension) {
            if ($extension->shouldActivate($ctx)) {
                if ($extension->tokenize($ctx)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all trigger characters from all extensions.
     */
    public function getTriggerCharacters(): string
    {
        $chars = '';
        foreach ($this->extensions as $extension) {
            $chars .= $extension->triggerCharacters();
        }

        return count_chars($chars, 3);
    }

    private function ensureSorted(): void
    {
        if ($this->sorted) {
            return;
        }

        usort($this->extensions, fn ($a, $b) => $b->priority() <=> $a->priority());
        $this->sorted = true;
    }
}
