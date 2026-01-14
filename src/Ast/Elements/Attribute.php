<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Document\Document;
use Forte\Ast\Node;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\TreeBuilder;
use JsonSerializable;
use RuntimeException;
use Stringable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class Attribute implements JsonSerializable, Stringable
{
    private ?string $cachedName = null;

    /** @var string|null|false false = not cached, null = boolean attr, string = value */
    private string|null|false $cachedValue = false;

    private ?string $cachedType = null;

    private ?string $cachedQuote = null;

    private ?AttributeName $cachedNameObject = null;

    private ?AttributeValue $cachedValueObject = null;

    private ?string $cachedLeadingWhitespace = null;

    public function __construct(private readonly Document $document, private readonly int $index, private readonly int $leadingWhitespaceIdx = -1, private readonly bool $isBladeConstruct = false) {}

    /**
     * Get the document this attribute belongs to.
     */
    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * Check if the attribute is an inter-mixed Blade construct.
     */
    public function isBladeConstruct(): bool
    {
        return $this->isBladeConstruct;
    }

    /**
     * Get the node for inter-mixed Blade construct attributes.
     */
    public function getBladeConstruct(): ?Node
    {
        if (! $this->isBladeConstruct) {
            return null;
        }

        return $this->document->getNode($this->index);
    }

    /**
     * Get the leading whitespace before this attribute.
     */
    public function leadingWhitespace(): string
    {
        if ($this->cachedLeadingWhitespace !== null) {
            return $this->cachedLeadingWhitespace;
        }

        if ($this->leadingWhitespaceIdx === -1) {
            $this->cachedLeadingWhitespace = '';

            return '';
        }

        return $this->cachedLeadingWhitespace = $this->document->getNodeTokenContent($this->leadingWhitespaceIdx);
    }

    /**
     * Get the attribute name as a string, without a prefix.
     */
    public function nameText(): string
    {
        if ($this->cachedName !== null) {
            return $this->cachedName;
        }

        if ($this->isBladeConstruct) {
            $this->cachedName = '';

            return '';
        }

        $nameIdx = $this->getNameNodeIndex();
        if ($nameIdx === -1) {
            $this->cachedName = '';

            return '';
        }

        return $this->cachedName = (string) $this->name();
    }

    /**
     * Get the attribute name object for complex name support.
     */
    public function name(): AttributeName
    {
        if ($this->cachedNameObject !== null) {
            return $this->cachedNameObject;
        }

        $nameIdx = $this->getNameNodeIndex();
        if ($nameIdx === -1) {
            throw new RuntimeException('Attribute has no name node');
        }

        $this->cachedNameObject = new AttributeName(
            $this->document,
            $nameIdx,
            $this->type()
        );

        return $this->cachedNameObject;
    }

    /**
     * Get the attribute value as a string, or null for boolean attributes.
     */
    public function valueText(): ?string
    {
        // false = not cached yet
        // null = boolean attr
        // string = value (including empty)
        if ($this->cachedValue !== false) {
            return $this->cachedValue;
        }

        $valueIdx = $this->getValueNodeIndex();
        if ($valueIdx === -1) {
            $this->cachedValue = null;

            return null;
        }

        $valueNode = $this->document->getFlatNode($valueIdx);
        $tokens = $this->document->getTokens();
        $source = $this->document->source();
        $tokenTotal = count($tokens);

        // Find value content (skip quotes)
        $tokenStart = $valueNode['tokenStart'];
        $tokenEnd = $tokenStart + $valueNode['tokenCount'];

        // Bounds check
        if ($tokenStart < 0 || $tokenStart >= $tokenTotal) {
            $this->cachedValue = '';

            return '';
        }

        // Skip opening quote if present
        if ($tokenStart < $tokenEnd && $tokens[$tokenStart]['type'] === TokenType::Quote) {
            $tokenStart++;
        }

        // Skip closing quote if present
        if ($tokenEnd > $tokenStart && $tokenEnd - 1 < $tokenTotal && $tokens[$tokenEnd - 1]['type'] === TokenType::Quote) {
            $tokenEnd--;
        }

        if ($tokenStart >= $tokenEnd || $tokenStart >= $tokenTotal || $tokenEnd - 1 >= $tokenTotal) {
            $this->cachedValue = '';

            return '';
        }

        $startToken = $tokens[$tokenStart];
        $endToken = $tokens[$tokenEnd - 1];

        return $this->cachedValue = substr($source, $startToken['start'], $endToken['end'] - $startToken['start']);
    }

    /**
     * Get the attribute value object for complex value support.
     */
    public function value(): ?AttributeValue
    {
        if ($this->cachedValueObject !== null) {
            return $this->cachedValueObject;
        }

        $valueIdx = $this->getValueNodeIndex();
        if ($valueIdx === -1) {
            return null;
        }

        return $this->cachedValueObject = new AttributeValue(
            $this->document,
            $valueIdx
        );
    }

    /**
     * Check if the name contains interpolation.
     */
    public function hasComplexName(): bool
    {
        return $this->name()->isComplex();
    }

    /**
     * Check if the value contains a complex, interpolated value.
     */
    public function hasComplexValue(): bool
    {
        $valueObj = $this->value();

        return $valueObj !== null && $valueObj->isComplex();
    }

    /**
     * Get the value text or a default if null.
     */
    public function valueOrDefault(string $default = ''): string
    {
        return $this->valueText() ?? $default;
    }

    /**
     * Get the attribute type.
     */
    public function type(): string
    {
        if ($this->cachedType !== null) {
            return $this->cachedType;
        }

        if ($this->isBladeConstruct) {
            return $this->cachedType = 'standalone';
        }

        $nameIdx = $this->getNameNodeIndex();
        if ($nameIdx === -1) {
            return $this->cachedType = 'static';
        }

        $nameNode = $this->document->getFlatNode($nameIdx);
        $tokens = $this->document->getTokens();

        if ($nameNode['tokenCount'] === 0) {
            return $this->cachedType = 'static';
        }

        $firstToken = $tokens[$nameNode['tokenStart']];
        $tokenType = $firstToken['type'];

        // Check if it's an extension token type
        if (TokenType::isExtension($tokenType)) {
            return $this->cachedType = TokenType::label($tokenType);
        }

        return $this->cachedType = match ($tokenType) {
            TokenType::BoundAttribute => AttributeType::BOUND,
            TokenType::EscapedAttribute => AttributeType::ESCAPED,
            TokenType::ShorthandAttribute => AttributeType::SHORTHAND,
            default => AttributeType::STATIC,
        };
    }

    /**
     * Get the value's quote character used, if available.
     */
    public function quote(): ?string
    {
        if ($this->cachedQuote !== null) {
            return $this->cachedQuote === '' ? null : $this->cachedQuote;
        }

        $valueIdx = $this->getValueNodeIndex();
        if ($valueIdx === -1) {
            $this->cachedQuote = '';

            return null;
        }

        $valueNode = $this->document->getFlatNode($valueIdx);
        $tokens = $this->document->getTokens();

        if ($valueNode['tokenCount'] === 0) {
            $this->cachedQuote = '';

            return null;
        }

        $firstToken = $tokens[$valueNode['tokenStart']];
        if ($firstToken['type'] === TokenType::Quote) {
            $source = $this->document->source();

            return $this->cachedQuote = substr($source, $firstToken['start'], 1);
        }

        $this->cachedQuote = '';

        return null;
    }

    /**
     * Check if this is a boolean attribute.
     *
     * Example: <input disabled>
     */
    public function isBoolean(): bool
    {
        if ($this->isVariableShorthand()) {
            return false;
        }

        return $this->getValueNodeIndex() === -1;
    }

    /**
     * Check if this is a bound attribute.
     *
     * Example: <div :class=""></div>
     */
    public function isBound(): bool
    {
        return $this->type() === 'bound';
    }

    /**
     * Check if this is a static attribute.
     */
    public function isStatic(): bool
    {
        return $this->type() === 'static';
    }

    /**
     * Check if this is an escaped attribute.
     *
     * Example: <div ::class=""></div>
     */
    public function isEscaped(): bool
    {
        return $this->type() === 'escaped';
    }

    /**
     * Check if this is a dynamic shorthand attribute.
     *
     * Example: <div :$variable></div>
     */
    public function isVariableShorthand(): bool
    {
        return $this->type() === AttributeType::SHORTHAND;
    }

    /**
     * Check if this is a JSX-style expression attribute ({expression}).
     */
    public function isExpression(): bool
    {
        if (! $this->isBladeConstruct) {
            return false;
        }

        $flat = $this->document->getFlatNode($this->index);

        return $flat['kind'] === NodeKind::JsxAttribute;
    }

    /**
     * Check if this is an extension attribute.
     */
    public function isExtensionAttribute(): bool
    {
        $flat = $this->document->getFlatNode($this->index);

        return NodeKind::isExtension($flat['kind']) && NodeKind::isAttribute($flat['kind']);
    }

    /**
     * Get the extension ID for this attribute.
     */
    public function extensionId(): ?string
    {
        if (! $this->isExtensionAttribute()) {
            return null;
        }

        $flat = $this->document->getFlatNode($this->index);
        $kindInfo = app(NodeKindRegistry::class)->get($flat['kind']);

        return $kindInfo?->namespace;
    }

    /**
     * Get the node kind for this attribute.
     */
    public function kind(): int
    {
        return $this->document->getFlatNode($this->index)['kind'];
    }

    /**
     * Get the raw attribute name including any prefix.
     */
    public function rawName(): string
    {
        if ($this->isBladeConstruct) {
            return '';
        }

        $nameIdx = $this->getNameNodeIndex();
        if ($nameIdx === -1) {
            return '';
        }

        return $this->document->getNodeTokenContent($nameIdx);
    }

    /**
     * Get the flat node for this attribute.
     *
     * @return FlatNode
     */
    public function getFlatNode(): array
    {
        return $this->document->getFlatNode($this->index);
    }

    /**
     * Get the start offset in the source document.
     */
    public function startOffset(): int
    {
        $flat = $this->document->getFlatNode($this->index);
        $tokens = $this->document->getTokens();
        $tokenStart = $flat['tokenStart'];

        if ($tokenStart < 0 || ! isset($tokens[$tokenStart])) {
            return -1;
        }

        return $tokens[$tokenStart]['start'];
    }

    /**
     * Get the end offset in the source document.
     */
    public function endOffset(): int
    {
        $flat = $this->document->getFlatNode($this->index);
        $tokens = $this->document->getTokens();
        $endTokenIdx = $flat['tokenStart'] + $flat['tokenCount'] - 1;

        if ($endTokenIdx < 0 || ! isset($tokens[$endTokenIdx])) {
            return -1;
        }

        return $tokens[$endTokenIdx]['end'];
    }

    /**
     * Render the attribute back to string.
     */
    public function render(): string
    {
        return $this->document->getNodeTokenContent($this->index);
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Get the index of the AttributeName child node.
     */
    private function getNameNodeIndex(): int
    {
        $flat = $this->document->getFlatNode($this->index);
        $childIdx = $flat['firstChild'];

        if ($childIdx === -1) {
            return -1;
        }

        $childNode = $this->document->getFlatNode($childIdx);
        if ($childNode['kind'] === NodeKind::AttributeName) {
            return $childIdx;
        }

        return -1;
    }

    /**
     * Get the index of the AttributeValue child node.
     */
    private function getValueNodeIndex(): int
    {
        $nameIdx = $this->getNameNodeIndex();
        if ($nameIdx === -1) {
            return -1;
        }

        $nameNode = $this->document->getFlatNode($nameIdx);
        $valueIdx = $nameNode['nextSibling'];

        if ($valueIdx === -1) {
            return -1;
        }

        $valueNode = $this->document->getFlatNode($valueIdx);
        if ($valueNode['kind'] === NodeKind::AttributeValue) {
            return $valueIdx;
        }

        return -1;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $flat = $this->document->getFlatNode($this->index);
        $tokens = $this->document->getTokens();

        $startOffset = $flat['tokenStart'] >= 0 ? $tokens[$flat['tokenStart']]['start'] : -1;
        $endTokenIdx = $flat['tokenStart'] + $flat['tokenCount'] - 1;
        $endOffset = $endTokenIdx >= 0 && isset($tokens[$endTokenIdx]) ? $tokens[$endTokenIdx]['end'] : -1;

        $data = [
            'type' => 'attribute',
            'kind' => $flat['kind'],
            'kind_name' => NodeKind::name($flat['kind']),
            'attribute_type' => $this->type(),
            'is_blade_construct' => $this->isBladeConstruct,
            'is_boolean' => $this->isBoolean(),
            'is_bound' => $this->isBound(),
            'is_escaped' => $this->isEscaped(),
            'is_static' => $this->isStatic(),
            'is_variable_shorthand' => $this->isVariableShorthand(),
            'is_expression' => $this->isExpression(),
            'is_extension_attribute' => $this->isExtensionAttribute(),
            'raw_name' => $this->rawName(),
            'quote' => $this->quote(),
            'content' => $this->render(),
            'start' => ['offset' => $startOffset],
            'end' => ['offset' => $endOffset],
        ];

        if ($this->isBladeConstruct) {
            $bladeNode = $this->getBladeConstruct();
            $data['blade_construct'] = $bladeNode?->jsonSerialize();
        } else {
            $data['name_text'] = $this->nameText();
            $data['value_text'] = $this->valueText();

            $nameIdx = $this->getNameNodeIndex();
            if ($nameIdx !== -1) {
                $data['name'] = $this->name()->jsonSerialize();
            }

            $valueObj = $this->value();
            if ($valueObj !== null) {
                $data['value'] = $valueObj->jsonSerialize();
            }

            $data['has_complex_name'] = $this->hasComplexName();
            $data['has_complex_value'] = $this->hasComplexValue();
        }

        if ($this->isExtensionAttribute()) {
            $data['extension_id'] = $this->extensionId();
        }

        return $data;
    }
}
