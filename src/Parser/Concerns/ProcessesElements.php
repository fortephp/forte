<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;
use Forte\Support\StringInterner;

trait ProcessesElements
{
    use ProcessesElementAttributes;
    use ProcessesElementAutoClosing;
    use ProcessesElementNames;

    protected function processElementStart(): void
    {
        $tokens = $this->tokens;
        $tokenCount = count($tokens);
        $startPos = $this->pos;

        $this->pos++;

        if ($startPos >= $tokenCount || $tokens[$startPos]['type'] !== TokenType::LessThan) {
            return;
        }

        // Closing tag?
        if ($this->pos < $tokenCount && $tokens[$this->pos]['type'] === TokenType::Slash) {
            $this->processElementEnd();

            return;
        }

        $tagNameStart = $this->pos;
        [$tagNameCount, $genericOffset] = $this->scanElementName();

        if ($tagNameCount === 0) {
            // No valid tag name. Treat "<" as text
            $this->addChild($this->createNode(
                kind: NodeKind::Text,
                parent: 0,
                tokenStart: $startPos,
                tokenCount: 1
            ));

            return;
        }

        $tagName = $this->getTagNameText($tagNameStart, $tagNameCount);
        $lowerNewTag = StringInterner::lower($tagName);

        // Auto-close applicable siblings before adding this node
        $this->autoCloseElementsForSibling($lowerNewTag);

        // Create Element node
        $elementIdx = $this->addChild($this->createNode(
            kind: NodeKind::Element,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: 0 // set later
        ));

        // Create ElementName as the first child
        $elementNameIdx = $this->createFirstChild(
            parentIdx: $elementIdx,
            kind: NodeKind::ElementName,
            tokenStart: $tagNameStart,
            tokenCount: $tagNameCount
        );

        // Build name parts
        $this->buildElementNameParts($tagNameStart, $tagNameCount, $elementNameIdx);

        // Consume TsxGenericType, if present
        if ($this->pos < $tokenCount && $tokens[$this->pos]['type'] === TokenType::TsxGenericType) {
            $this->pos++;
        }

        // Attributes
        $this->buildAttributes($elementIdx, $elementNameIdx);

        // Self-closing detection
        $selfClosing = false;
        $syntheticClose = false;
        if ($this->pos < $tokenCount && $tokens[$this->pos]['type'] === TokenType::Slash) {
            $this->pos++;
            $selfClosing = true;
        }

        if ($this->pos < $tokenCount) {
            $type = $tokens[$this->pos]['type'];
            if ($type === TokenType::GreaterThan) {
                $this->pos++;
            } elseif ($type === TokenType::SyntheticClose) {
                $this->pos++;
                $syntheticClose = true;
            }
        }

        // Update element metadata
        $this->nodes[$elementIdx]['tokenCount'] = $this->pos - $startPos;
        $this->nodes[$elementIdx]['genericOffset'] = $genericOffset > 0 ? $genericOffset + 1 : 0;
        $this->nodes[$elementIdx]['data'] = $selfClosing ? 1 : 0;

        // Decide if an element stays open
        $shouldStayOpen = ! $selfClosing;
        if ($syntheticClose && $shouldStayOpen) {
            if ($this->pos < $tokenCount && $tokens[$this->pos]['type'] === TokenType::LessThan) {
                $hasAttributes = $this->hasAttributeTokens($startPos, $this->pos);
                if (! $hasAttributes) {
                    $shouldStayOpen = false;
                }
            }
        }

        if (! $shouldStayOpen) {
            return;
        }

        // Keep element open (check depth to prevent DoS)
        $this->checkElementDepth();
        $this->openElements[] = $elementIdx;

        // Cache lowercase tag name (reuse $lowerNewTag from earlier)
        $this->tagNames[$elementIdx] = $lowerNewTag;

        // Push to the per-tag-name stack for static tags
        if ($lowerNewTag !== '' && ! $this->isDynamicTagName($tagNameStart, $tagNameCount)) {
            $this->tagNameStacks[$lowerNewTag] ??= [];
            $this->tagNameStacks[$lowerNewTag][] = count($this->openElements) - 1;
        }
    }

    protected function processElementEnd(): void
    {
        $tokens = $this->tokens;
        $tokenCount = count($tokens);

        // Positioned at Slash after "<"
        $startPos = $this->pos - 1;
        $this->pos++; // skip "/"

        $tagNameStartPos = $this->pos;
        [$tagNameCount, $_] = $this->scanElementName();

        $closingTagName = $tagNameCount > 0
            ? $this->getTagNameText($tagNameStartPos, $tagNameCount)
            : '';

        // Skip to ">" or SyntheticClose
        while ($this->pos < $tokenCount &&
            $tokens[$this->pos]['type'] !== TokenType::GreaterThan &&
            $tokens[$this->pos]['type'] !== TokenType::SyntheticClose) {
            $this->pos++;
        }
        if ($this->pos < $tokenCount) {
            $this->pos++; // consume end token
        }

        // Directive scope boundary
        $searchLimit = 1; // skip root
        if (! empty($this->openDirectives)) {
            $currentDirective = end($this->openDirectives);
            $searchLimit = $currentDirective['elementStackBase'] + 1;
        }

        $lowerClosingTag = StringInterner::lower($closingTagName);
        $foundMatch = false;
        $matchDepth = 0;
        $matchedOpenElementsIndex = -1;

        if (isset($this->tagNameStacks[$lowerClosingTag]) && ! empty($this->tagNameStacks[$lowerClosingTag])) {
            $stack = $this->tagNameStacks[$lowerClosingTag];
            $openElementsCount = count($this->openElements);
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                $openElementsIndex = $stack[$i];
                if ($openElementsIndex >= $searchLimit && $openElementsIndex < $openElementsCount) {
                    $elementIdx = $this->openElements[$openElementsIndex];
                    $elementTagName = $this->tagNames[$elementIdx] ?? '';
                    if ($elementTagName === $lowerClosingTag) {
                        $matchedOpenElementsIndex = $openElementsIndex;
                        $foundMatch = true;
                        $matchDepth = $openElementsCount - $openElementsIndex;
                        break;
                    }
                }
            }
        }

        // Fallback search (dynamic tags / missed)
        for ($i = count($this->openElements) - 1; $i >= $searchLimit; $i--) {
            $elementIdx = $this->openElements[$i];
            if ($this->nodes[$elementIdx]['kind'] === NodeKind::DirectiveBlock) {
                continue;
            }

            $openTagName = $this->tagNames[$elementIdx] ?? $this->getOpenElementTagName($elementIdx);
            if (strcasecmp((string) $openTagName, (string) $closingTagName) === 0
                || $this->isSlotClosingMatch($openTagName, $closingTagName)
            ) {
                $foundMatch = true;
                $matchDepth = count($this->openElements) - $i;
                $matchedOpenElementsIndex = $i;
                break;
            }
        }

        if ($foundMatch) {
            $elementIdx = $this->openElements[$matchedOpenElementsIndex];
            $endPos = $this->pos; // After closing token
            $this->nodes[$elementIdx]['tokenCount'] = $endPos - $this->nodes[$elementIdx]['tokenStart'];

            // ClosingElementName node
            $closingNameIdx = $this->createAndLinkClosingNameNode(
                parentIdx: $elementIdx,
                tokenStart: $tagNameStartPos,
                tokenCount: $tagNameCount
            );
            $this->buildElementNameParts($tagNameStartPos, $tagNameCount, $closingNameIdx);

            // Pop matched element and any nested open elements
            for ($i = 0; $i < $matchDepth; $i++) {
                if (count($this->openElements) > 1) {
                    $poppedElementIdx = array_pop($this->openElements);
                    $this->cleanupTagNameStack($poppedElementIdx);
                }
            }

            return;
        }

        // Didn't find a match, so we will emit UnpairedClosingTag.
        $tokenCountSpan = $this->pos - $startPos;
        $this->addChild($this->createNode(
            kind: NodeKind::UnpairedClosingTag,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCountSpan,
            data: $tagNameCount
        ));
    }

    protected function hasAttributeTokens(int $startPos, int $endPos): bool
    {
        $tokens = $this->tokens;
        $limit = min($endPos, count($tokens));

        for ($i = $startPos; $i < $limit; $i++) {
            $type = $tokens[$i]['type'];
            if ($type === TokenType::AttributeName ||
                $type === TokenType::Equals ||
                $type === TokenType::Quote ||
                $type === TokenType::AttributeValue ||
                $type === TokenType::EscapedAttribute ||
                $type === TokenType::BoundAttribute ||
                $type === TokenType::ShorthandAttribute ||
                $type === TokenType::JsxAttributeValue ||
                $type === TokenType::JsxShorthandAttribute ||
                $type === TokenType::Directive) {
                return true;
            }
        }

        return false;
    }

    protected function isSlotClosingMatch(string $openTagName, string $closingTagName): bool
    {
        $lowerOpen = StringInterner::lower($openTagName);
        $lowerClose = StringInterner::lower($closingTagName);

        if ($lowerClose !== 'x-slot') {
            return false;
        }

        // Match: x-slot:name, x-slot[name], x-slot:[name], x-slot:name[]
        return str_starts_with($lowerOpen, 'x-slot:')
            || str_starts_with($lowerOpen, 'x-slot[');
    }

    private function createAndLinkClosingNameNode(int $parentIdx, int $tokenStart, int $tokenCount): int
    {
        $idx = $this->nodeCount++;
        $this->nodes[$idx] = $this->createNode(
            kind: NodeKind::ClosingElementName,
            parent: $parentIdx,
            tokenStart: $tokenStart,
            tokenCount: $tokenCount
        );

        $lastChild = $this->nodes[$parentIdx]['lastChild'];
        if ($lastChild !== -1) {
            $this->nodes[$lastChild]['nextSibling'] = $idx;
        } else {
            $this->nodes[$parentIdx]['firstChild'] = $idx;
        }
        $this->nodes[$parentIdx]['lastChild'] = $idx;

        return $idx;
    }
}
