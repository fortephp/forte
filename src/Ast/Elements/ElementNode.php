<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

use Forte\Ast\Elements\Concerns\ManagesElementAttributes;
use Forte\Ast\Elements\Concerns\ManagesElementClosing;
use Forte\Ast\Elements\Concerns\ManagesElementGenerics;
use Forte\Ast\Elements\Concerns\RendersElements;
use Forte\Ast\Node;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class ElementNode extends Node
{
    use ManagesElementAttributes,
        ManagesElementClosing,
        ManagesElementGenerics,
        RendersElements;

    private ?ElementNameNode $cachedTagName = null;

    private ?ElementNameNode $cachedClosingTag = null;

    private ?Attributes $cachedAttributes = null;

    private ?int $closingNameIdx = null;

    private bool $closingNameSearched = false;

    /**
     * Get the tag's name node.
     */
    public function tagName(): ElementNameNode
    {
        if ($this->cachedTagName !== null) {
            return $this->cachedTagName;
        }

        // Find the ElementName child node
        $flat = $this->flat();
        $childIdx = $flat['firstChild'] ?? -1;

        while ($childIdx !== -1) {
            $childFlat = $this->document->getFlatNode($childIdx);
            if ($childFlat['kind'] === NodeKind::ElementName) {
                $node = $this->document->getNode($childIdx);
                assert($node instanceof ElementNameNode);

                return $this->cachedTagName = $node;
            }
            $childIdx = $childFlat['nextSibling'] ?? -1;
        }

        $node = $this->document->getNode($this->index);
        assert($node instanceof ElementNameNode || $node instanceof self);

        throw new RuntimeException('Element has no ElementName child node');
    }

    /**
     * Get the tag name as a string.
     */
    public function tagNameText(): string
    {
        $meta = $this->document->getSyntheticMeta($this->index);

        if ($meta !== null && isset($meta['tagName']) && is_string($meta['tagName'])) {
            return $meta['tagName'];
        }

        return (string) $this->tagName();
    }

    /**
     * Get element content children, excluding tag names and attributes.
     *
     * @return iterable<Node>
     */
    public function children(): iterable
    {
        $flat = $this->flat();
        $childIdx = $flat['firstChild'] ?? -1;

        // Find where the opening tag ends to skip attribute-position children
        $openingTagEndPos = $this->findOpeningTagEndPosition();
        $tokens = $this->document->getTokens();

        while ($childIdx !== -1) {
            $childFlat = $this->document->getFlatNode($childIdx);
            $kind = $childFlat['kind'];

            // Skip ElementName and ClosingElementName
            if ($kind === NodeKind::ElementName || $kind === NodeKind::ClosingElementName) {
                $childIdx = $childFlat['nextSibling'] ?? -1;

                continue;
            }

            // Skip attribute-related nodes
            if ($kind === NodeKind::Attribute ||
                $kind === NodeKind::JsxAttribute ||
                $kind === NodeKind::AttributeName ||
                $kind === NodeKind::AttributeValue ||
                $kind === NodeKind::AttributeWhitespace) {
                $childIdx = $childFlat['nextSibling'] ?? -1;

                continue;
            }

            // Check if this child is within the opening tag (before >)
            // If so, it's an attribute-position node, and we skip it.
            // Synthetic nodes (tokenStart = -1) are always content.
            $tokenStart = $childFlat['tokenStart'];
            if ($tokenStart >= 0) {
                $childStartPos = $tokens[$tokenStart]['start'];
                if ($childStartPos < $openingTagEndPos) {
                    $childIdx = $childFlat['nextSibling'] ?? -1;

                    continue;
                }
            }

            yield $this->document->getNode($childIdx);

            $childIdx = $childFlat['nextSibling'] ?? -1;
        }
    }

    /**
     * Find the source position where the opening tag ends (after >).
     */
    private function findOpeningTagEndPosition(): int
    {
        return $this->document->findOpeningTagEndPosition($this->index);
    }

    /**
     * Find the source offset where the tag name ends.
     */
    private function findTagNameEndOffset(): int
    {
        $flat = $this->flat();
        $tokens = $this->document->getTokens();

        // 1. ElementName child node gives an exact span.
        for ($childIdx = $flat['firstChild'] ?? -1; $childIdx !== -1;) {
            $childFlat = $this->document->getFlatNode($childIdx);

            if (($childFlat['kind'] ?? null) === NodeKind::ElementName) {
                $tokenStart = (int) ($childFlat['tokenStart'] ?? 0);
                $tokenCount = (int) ($childFlat['tokenCount'] ?? 0);
                $lastTokenIdx = $tokenStart + $tokenCount - 1;

                if ($tokenCount > 0 && isset($tokens[$lastTokenIdx])) {
                    return $tokens[$lastTokenIdx]['end'];
                }

                break; // Malformed ElementName; fall back.
            }

            $childIdx = $childFlat['nextSibling'] ?? -1;
        }

        // 2. Fallback: derive from the element token stream.
        $elementTokenStart = (int) ($flat['tokenStart'] ?? 0);

        // Assumption: tokenStart points at '<'. The tag name begins at tokenStart + 1.
        $tagNameFirstTokenIdx = $elementTokenStart + 1;

        // genericOffset, when present, describes how many tokens belong to the
        // generics. If unavailable, assume the tag name is exactly one token
        $tagNameTokenCount = (int) ($flat['genericOffset'] ?? 0);
        if ($tagNameTokenCount <= 0) {
            $tagNameTokenCount = 1;
        }

        // If tokenStart is '<', then the last tag-name token index is:
        //   tokenStart + tagNameTokenCount + 1 (for leading '<').
        $tagNameLastTokenIdx = $elementTokenStart + $tagNameTokenCount;

        if (isset($tokens[$tagNameLastTokenIdx])) {
            return $tokens[$tagNameLastTokenIdx]['end'];
        }

        // 3. It's come to this.
        if (isset($tokens[$tagNameFirstTokenIdx])) {
            return $tokens[$tagNameFirstTokenIdx]['end'];
        }

        return isset($tokens[$elementTokenStart]) ? $tokens[$elementTokenStart]['end'] : 0;
    }

    /**
     * Check if this element matches a tag name pattern.
     */
    public function is(string $pattern): bool
    {
        $tagName = $this->tagNameText();

        if ($tagName === '') {
            return false;
        }

        return Str::is($pattern, $tagName);
    }

    /**
     * Check if this is a void element.
     */
    public function isVoid(): bool
    {
        return isset(VoidElements::$voidElements[strtolower($this->tagNameText())]);
    }

    /**
     * Check if this is a component.
     */
    public function isComponent(): bool
    {
        return $this->document->componentManager()->isComponent(
            strtolower($this->tagNameText())
        );
    }

    /**
     * Get the trailing whitespace inside the opening tag (before >).
     */
    public function trailingWhitespace(): string
    {
        return $this->attributes()->trailingWhitespace();
    }

    /**
     * Get all inner content.
     */
    public function innerContent(): string
    {
        $content = '';

        foreach ($this->children() as $node) {
            $content .= $node->render();
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'element';
        $data['tag_name_text'] = $this->tagNameText();
        $data['tag_name'] = $this->tagName()->jsonSerialize();
        $data['is_void'] = $this->isVoid();
        $data['is_component'] = $this->isComponent();
        $data['is_self_closing'] = $this->isSelfClosing();
        $data['is_paired'] = $this->isPaired();

        $closingTag = $this->closingTag();
        if ($closingTag !== null) {
            $data['closing_tag'] = $closingTag->jsonSerialize();
        }

        $data['attributes'] = array_map(
            fn (Attribute $attr) => $attr->jsonSerialize(),
            $this->attributes()->all()
        );

        return $data;
    }
}
