<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\Concerns\ContainsComplexParts;
use Forte\Ast\Node;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 *
 * @implements IteratorAggregate<int, Node|string>
 */
class AttributeValue implements IteratorAggregate, JsonSerializable, Stringable
{
    use ContainsComplexParts;

    private ?string $cachedContent = null;

    private ?bool $cachedIsComplex = null;

    public function __construct(private Document $document, private int $index) {}

    public function __toString(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        $flat = $this->document->getFlatNode($this->index);
        $tokens = $this->document->getTokens();
        $source = $this->document->source();

        if ($flat['tokenCount'] === 0) {
            $this->cachedContent = '';

            return '';
        }

        // Get content between quotes (if quoted)
        $tokenStart = $flat['tokenStart'];
        $tokenEnd = $tokenStart + $flat['tokenCount'];
        $tokenTotal = count($tokens);

        // Bounds check
        if ($tokenStart < 0 || $tokenStart >= $tokenTotal) {
            $this->cachedContent = '';

            return '';
        }

        // Skip opening quote if present
        if ($tokens[$tokenStart]['type'] === TokenType::Quote) {
            $tokenStart++;
        }

        // Skip closing quote if present
        if ($tokenEnd > $tokenStart && $tokenEnd - 1 < $tokenTotal && $tokens[$tokenEnd - 1]['type'] === TokenType::Quote) {
            $tokenEnd--;
        }

        if ($tokenStart >= $tokenEnd || $tokenStart >= $tokenTotal || $tokenEnd - 1 >= $tokenTotal) {
            $this->cachedContent = '';

            return '';
        }

        $startToken = $tokens[$tokenStart];
        $endToken = $tokens[$tokenEnd - 1];

        $this->cachedContent = substr($source, $startToken['start'], $endToken['end'] - $startToken['start']);

        return $this->cachedContent;
    }

    public function toString(): string
    {
        return (string) $this;
    }

    /**
     * Check if this is an empty value.
     */
    public function isEmpty(): bool
    {
        $flat = $this->document->getFlatNode($this->index);

        return $flat['firstChild'] === -1;
    }

    /**
     * Get the parts that make up the value.
     *
     * @return iterable<Node|string>
     */
    public function parts(): iterable
    {
        $flat = $this->document->getFlatNode($this->index);
        $childIdx = $flat['firstChild'];

        while ($childIdx !== -1) {
            $childNode = $this->document->getFlatNode($childIdx);
            $kind = $childNode['kind'];

            if ($kind === NodeKind::Text) {
                yield $this->document->getNodeTokenContent($childIdx);
            } else {
                yield $this->document->getNode($childIdx);
            }

            $childIdx = $childNode['nextSibling'];
        }
    }

    /**
     * Get parts as an array.
     *
     * @return array<Node|string>
     */
    public function getParts(): array
    {
        return iterator_to_array($this->parts());
    }

    /**
     * Iterate over parts.
     */
    public function getIterator(): Traversable
    {
        foreach ($this->parts() as $part) {
            yield $part;
        }
    }

    /**
     * Check if this is a simple, non-interpolated value.
     */
    public function isSimple(): bool
    {
        return ! $this->isComplex();
    }

    /**
     * Get just the static text portions (excludes interpolation content).
     */
    public function staticText(): string
    {
        $result = '';
        $flat = $this->document->getFlatNode($this->index);
        $childIdx = $flat['firstChild'];

        while ($childIdx !== -1) {
            $childNode = $this->document->getFlatNode($childIdx);
            if ($childNode['kind'] === NodeKind::Text) {
                $result .= $this->document->getNodeTokenContent($childIdx);
            }
            $childIdx = $childNode['nextSibling'];
        }

        return $result;
    }

    /**
     * Get the flat node for this value.
     *
     * @return FlatNode
     */
    public function getFlatNode(): array
    {
        return $this->document->getFlatNode($this->index);
    }

    /**
     * Get the quote character used for this value.
     */
    public function quote(): ?string
    {
        $flat = $this->document->getFlatNode($this->index);
        $tokens = $this->document->getTokens();

        if ($flat['tokenCount'] === 0) {
            return null;
        }

        $tokenStart = $flat['tokenStart'];
        if ($tokenStart < 0 || $tokenStart >= count($tokens)) {
            return null;
        }

        $firstToken = $tokens[$tokenStart];
        if ($firstToken['type'] === TokenType::Quote) {
            $source = $this->document->source();

            return substr($source, $firstToken['start'], 1);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $flat = $this->document->getFlatNode($this->index);
        $tokens = $this->document->getTokens();
        $tokenStart = $flat['tokenStart'];
        $startOffset = $tokenStart >= 0 && isset($tokens[$tokenStart]) ? $tokens[$tokenStart]['start'] : -1;
        $endTokenIdx = $tokenStart + $flat['tokenCount'] - 1;
        $endOffset = $endTokenIdx >= 0 && isset($tokens[$endTokenIdx]) ? $tokens[$endTokenIdx]['end'] : -1;

        return [
            'type' => 'attribute_value',
            'value' => (string) $this,
            'is_empty' => $this->isEmpty(),
            'is_complex' => $this->isComplex(),
            'static_text' => $this->staticText(),
            'quote' => $this->quote(),
            'start' => ['offset' => $startOffset],
            'end' => ['offset' => $endOffset],
            'parts' => array_map(
                fn ($part) => is_string($part)
                    ? ['type' => 'text', 'content' => $part]
                    : $part->jsonSerialize(),
                $this->getParts()
            ),
        ];
    }
}
