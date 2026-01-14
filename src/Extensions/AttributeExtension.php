<?php

declare(strict_types=1);

namespace Forte\Extensions;

use Forte\Lexer\Extension\AttributeLexerContext;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\AttributeParserContext;
use Forte\Parser\NodeKindRegistry;

interface AttributeExtension extends ForteExtension
{
    /**
     * Prefix that triggers this attribute type.
     */
    public function attributePrefix(): string;

    /**
     * Whether to activate for this attribute position.
     *
     * @param  AttributeLexerContext  $ctx  Context positioned after the prefix
     */
    public function shouldActivate(AttributeLexerContext $ctx): bool;

    /**
     * Register the token type for this attribute.
     */
    public function registerAttributeTokenType(TokenTypeRegistry $registry): int;

    /**
     * Register the node kind for this attribute.
     */
    public function registerAttributeNodeKind(NodeKindRegistry $registry): int;

    /**
     * Get the registered token type ID.
     */
    public function getTokenType(): int;

    /**
     * Get the registered node kind ID.
     */
    public function getNodeKind(): int;

    /**
     * Tokenize the attribute name.
     *
     * @param  AttributeLexerContext  $ctx  Context for attribute tokenization
     */
    public function tokenizeAttributeName(AttributeLexerContext $ctx): int;

    /**
     * Build the attribute node.
     *
     * @param  AttributeParserContext  $ctx  Context for attribute parsing
     */
    public function buildAttributeNode(AttributeParserContext $ctx): int;

    /**
     * Whether this attribute type accepts a value.
     */
    public function acceptsValue(): bool;
}
