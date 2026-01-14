<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\Concerns\ContainsComplexParts;
use Forte\Ast\Node;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 *
 * @implements IteratorAggregate<int, string|Node>
 */
class AttributeName implements IteratorAggregate, JsonSerializable, Stringable
{
    use ContainsComplexParts;

    private ?string $cachedContent = null;

    private ?bool $cachedIsComplex = null;

    public function __construct(
        private readonly Document $document,
        private readonly int $index,
        private readonly string $type = 'static'
    ) {}

    /**
     * Get the name as a string, without a prefix.
     */
    public function __toString(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        $rawContent = $this->document->getNodeTokenContent($this->index);

        if ($rawContent === '') {
            $this->cachedContent = '';

            return '';
        }

        return $this->cachedContent = $this->stripPrefix($rawContent);
    }

    /**
     * Get the attribute name.
     */
    public function toString(): string
    {
        return (string) $this;
    }

    /**
     * Get the full raw name, including prefix.
     */
    public function rawName(): string
    {
        return $this->document->getNodeTokenContent($this->index);
    }

    /**
     * Get the attribute type.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Check if this is a bound attribute.
     */
    public function isBound(): bool
    {
        return $this->type === AttributeType::BOUND;
    }

    /**
     * Check if this is an escaped attribute.
     */
    public function isEscaped(): bool
    {
        return $this->type === AttributeType::ESCAPED;
    }

    /**
     * Check if this is a static attribute.
     */
    public function isStatic(): bool
    {
        return $this->type === AttributeType::STATIC;
    }

    /**
     * Get the parts that make up the name.
     *
     * @return iterable<string|Node>
     */
    public function parts(): iterable
    {
        $flat = $this->document->getFlatNode($this->index);
        $childIdx = $flat['firstChild'];
        $isFirst = true;

        while ($childIdx !== -1) {
            $childNode = $this->document->getFlatNode($childIdx);
            $kind = $childNode['kind'];

            if ($kind === NodeKind::Text) {
                // Get text content
                $content = $this->document->getNodeTokenContent($childIdx);

                // Strip prefix from the first text part if bound/escaped/dynamic
                if ($isFirst) {
                    $content = $this->stripPrefix($content);
                }

                if ($content !== '') {
                    yield $content;
                }
            } else {
                yield $this->document->getNode($childIdx);
            }

            $isFirst = false;
            $childIdx = $childNode['nextSibling'];
        }
    }

    /**
     * Get the name parts.
     *
     * @return array<string|Node>
     */
    public function getParts(): array
    {
        return iterator_to_array($this->parts());
    }

    public function getIterator(): Traversable
    {
        foreach ($this->parts() as $part) {
            yield $part;
        }
    }

    /**
     * Check if this is a simple, non-interpolated name.
     */
    public function isSimple(): bool
    {
        return ! $this->isComplex();
    }

    /**
     * Get just the static text portions of the name.
     */
    public function staticText(): string
    {
        $result = '';
        $flat = $this->document->getFlatNode($this->index);
        $childIdx = $flat['firstChild'];
        $isFirst = true;

        while ($childIdx !== -1) {
            $childNode = $this->document->getFlatNode($childIdx);
            if ($childNode['kind'] === NodeKind::Text) {
                $content = $this->document->getNodeTokenContent($childIdx);

                // Strip prefix from the first text part
                if ($isFirst) {
                    $content = $this->stripPrefix($content);
                    $isFirst = false;
                }

                $result .= $content;
            }
            $childIdx = $childNode['nextSibling'];
        }

        return $result;
    }

    /**
     * Strip the attribute prefix from the provided content.
     */
    private function stripPrefix(string $content): string
    {
        return match ($this->type) {
            AttributeType::BOUND => ltrim($content, ':'),
            AttributeType::ESCAPED => str_starts_with($content, '::') ? substr($content, 2) : ltrim($content, ':'),
            AttributeType::SHORTHAND => str_starts_with($content, ':$') ? substr($content, 2) : ltrim($content, ':$'),
            default => $content,
        };
    }

    /**
     * Get the flat node for this name.
     *
     * @return FlatNode
     */
    public function getFlatNode(): array
    {
        return $this->document->getFlatNode($this->index);
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
            'type' => 'attribute_name',
            'name' => (string) $this,
            'raw_name' => $this->rawName(),
            'attribute_type' => $this->type,
            'is_complex' => $this->isComplex(),
            'is_bound' => $this->isBound(),
            'is_escaped' => $this->isEscaped(),
            'is_static' => $this->isStatic(),
            'static_text' => $this->staticText(),
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
