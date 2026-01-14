<?php

declare(strict_types=1);

namespace Forte\Extensions;

use Forte\Extensions\Concerns\HasConfiguration;
use Forte\Extensions\Concerns\HasDiagnostics;
use Forte\Extensions\Concerns\ProvidesDefaultExtensionImplementation;
use Forte\Lexer\Extension\AttributeLexerContext;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\AttributeParserContext;
use Forte\Parser\NodeKindRegistry;

abstract class AbstractAttributeExtension implements AttributeExtension
{
    use HasConfiguration;
    use HasDiagnostics;
    use ProvidesDefaultExtensionImplementation;

    protected int $tokenType = -1;

    protected int $nodeKind = -1;

    private bool $tokenTypeRegistered = false;

    private bool $nodeKindRegistered = false;

    /**
     * Unique identifier for this extension.
     */
    abstract public function id(): string;

    /**
     * Prefix that triggers this attribute type.
     */
    abstract public function attributePrefix(): string;

    /**
     * Whether to activate for this attribute position.
     *
     * @param  AttributeLexerContext  $ctx  Context positioned after the prefix
     */
    public function shouldActivate(AttributeLexerContext $ctx): bool
    {
        return true;
    }

    /**
     * Whether this attribute type accepts a value.
     */
    public function acceptsValue(): bool
    {
        return true;
    }

    /**
     * Tokenize the attribute name.
     */
    public function tokenizeAttributeName(AttributeLexerContext $ctx): int
    {
        $prefixLen = strlen($this->attributePrefix());
        $consumed = $prefixLen;

        // Consume alphanumeric, $, and _ characters
        while (true) {
            $char = $ctx->peek($consumed);
            if ($char === null) {
                break;
            }
            if (ctype_alnum($char) || $char === '$' || $char === '_') {
                $consumed++;
            } else {
                break;
            }
        }

        return $consumed;
    }

    /**
     * Build the attribute node.
     */
    public function buildAttributeNode(AttributeParserContext $ctx): int
    {
        return $ctx->buildStandardAttribute($this->nodeKind, $this->acceptsValue());
    }

    /**
     * Register the token type for this attribute.
     */
    public function registerAttributeTokenType(TokenTypeRegistry $registry): int
    {
        if (! $this->tokenTypeRegistered) {
            $this->tokenType = $registry->register($this->id(), 'Attribute');
            $this->tokenTypeRegistered = true;
        }

        return $this->tokenType;
    }

    /**
     * Register the node kind for this attribute.
     */
    public function registerAttributeNodeKind(NodeKindRegistry $registry): int
    {
        if (! $this->nodeKindRegistered) {
            $this->nodeKind = $registry->register(
                namespace: $this->id(),
                name: 'Attribute',
                category: 'Attribute'
            );
            $this->nodeKindRegistered = true;
        }

        return $this->nodeKind;
    }

    /**
     * Get the registered token type ID.
     */
    public function getTokenType(): int
    {
        return $this->tokenType;
    }

    /**
     * Get the registered node kind ID.
     */
    public function getNodeKind(): int
    {
        return $this->nodeKind;
    }

    /**
     * Get the string key for the token type.
     */
    protected function typeKey(): string
    {
        return "{$this->id()}:Attribute";
    }

    /**
     * Get the string key for the node kind.
     */
    protected function kindKey(): string
    {
        return "{$this->id()}::Attribute";
    }
}
