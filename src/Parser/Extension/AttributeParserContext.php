<?php

declare(strict_types=1);

namespace Forte\Parser\Extension;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;

class AttributeParserContext
{
    public function __construct(private readonly TreeBuilder $builder, private int $attrEnd) {}

    /**
     * Reset the context to a new attribute end boundary.
     */
    public function reset(int $attrEnd): self
    {
        $this->attrEnd = $attrEnd;

        return $this;
    }

    /**
     * Get current position in token stream.
     */
    public function position(): int
    {
        return $this->builder->position();
    }

    /**
     * Get the end boundary for attributes.
     */
    public function attributeEnd(): int
    {
        return $this->attrEnd;
    }

    /**
     * Get the current token.
     *
     * @return array{type: int, start: int, end: int}|null
     */
    public function currentToken(): ?array
    {
        return $this->builder->getCurrentToken();
    }

    /**
     * Peek at a token at offset from the current position.
     *
     * @return array{type: int, start: int, end: int}|null
     */
    public function peekToken(int $offset = 0): ?array
    {
        return $this->builder->peekToken($offset);
    }

    /**
     * Get the source string.
     */
    public function source(): string
    {
        return $this->builder->source();
    }

    /**
     * Get text content of a token.
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public function tokenText(array $token): string
    {
        return substr($this->source(), $token['start'], $token['end'] - $token['start']);
    }

    /**
     * Build a standard attribute node.
     *
     * @param  int  $kind  The node kind for the attribute
     * @param  bool  $acceptsValue  Whether to look for and include a value
     */
    public function buildStandardAttribute(int $kind, bool $acceptsValue = true): int
    {
        $startPos = $this->position();
        $pos = $startPos;

        // Collect the attribute name token
        $nameCount = 1;
        $pos++;

        // Check for whitespace after name
        while ($pos < $this->attrEnd && $this->peekToken($pos - $startPos) !== null) {
            $token = $this->peekToken($pos - $startPos);
            if ($token['type'] === TokenType::Whitespace) {
                $pos++;
            } else {
                break;
            }
        }

        $valueStart = -1;
        $valueCount = 0;

        // Check for = and value
        if ($acceptsValue && $pos < $this->attrEnd) {
            $nextToken = $this->peekToken($pos - $startPos);
            if ($nextToken !== null && $nextToken['type'] === TokenType::Equals) {
                $pos++; // Skip =

                // Skip whitespace after =
                while ($pos < $this->attrEnd) {
                    $wsToken = $this->peekToken($pos - $startPos);
                    if ($wsToken !== null && $wsToken['type'] === TokenType::Whitespace) {
                        $pos++;
                    } else {
                        break;
                    }
                }

                // Collect value tokens
                if ($pos < $this->attrEnd) {
                    $valueStart = $pos;
                    $valToken = $this->peekToken($pos - $startPos);

                    if ($valToken !== null && $valToken['type'] === TokenType::Quote) {
                        // Quoted value
                        $pos++; // Opening quote
                        while ($pos < $this->attrEnd) {
                            $innerToken = $this->peekToken($pos - $startPos);
                            if ($innerToken === null) {
                                break;
                            }
                            $pos++;
                            if ($innerToken['type'] === TokenType::Quote) {
                                break; // Closing quote
                            }
                        }
                    } else {
                        // Unquoted value - scan until whitespace or end
                        while ($pos < $this->attrEnd) {
                            $innerToken = $this->peekToken($pos - $startPos);
                            if ($innerToken === null || $innerToken['type'] === TokenType::Whitespace) {
                                break;
                            }
                            $pos++;
                        }
                    }
                    $valueCount = $pos - $valueStart;
                }
            }
        }

        $totalTokens = $pos - $startPos;

        $attrIdx = $this->builder->createExtensionNode(
            kind: $kind,
            tokenStart: $startPos,
            tokenCount: $totalTokens
        );
        $this->builder->addChildNode($attrIdx);

        $nameIdx = $this->builder->createExtensionNode(
            kind: NodeKind::AttributeName,
            tokenStart: $startPos,
            tokenCount: $nameCount
        );
        $this->builder->linkAsFirstChild($attrIdx, $nameIdx);

        if ($valueCount > 0) {
            $valueIdx = $this->builder->createExtensionNode(
                kind: NodeKind::AttributeValue,
                tokenStart: $valueStart,
                tokenCount: $valueCount
            );
            $this->builder->linkAsSibling($nameIdx, $valueIdx, $attrIdx);
        }

        return $totalTokens;
    }

    /**
     * Create a simple node (no value, just the name token).
     *
     * @param  int  $kind  The node kind
     */
    public function buildSimpleAttribute(int $kind): int
    {
        $startPos = $this->position();

        $attrIdx = $this->builder->createExtensionNode(
            kind: $kind,
            tokenStart: $startPos,
            tokenCount: 1
        );
        $this->builder->addChildNode($attrIdx);

        $nameIdx = $this->builder->createExtensionNode(
            kind: NodeKind::AttributeName,
            tokenStart: $startPos,
            tokenCount: 1
        );
        $this->builder->linkAsFirstChild($attrIdx, $nameIdx);

        return 1;
    }
}
