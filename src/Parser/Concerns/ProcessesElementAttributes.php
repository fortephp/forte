<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\ConstructScanner;
use Forte\Parser\Extension\AttributeParserContext;
use Forte\Parser\NodeKind;

trait ProcessesElementAttributes
{
    /**
     * Build attribute nodes as children of an element.
     *
     * @param  int  $elementIdx  Index of the parent Element node
     * @param  int  $lastChildIdx  Index of the last child (ElementName)
     */
    protected function buildAttributes(int $elementIdx, int $lastChildIdx): void
    {
        $attrEnd = $this->findAttributeRegionEnd();

        // Push element to stack so directive children are correctly parented
        $this->openElements[] = $elementIdx;
        [$savedOpenDirectives, $savedOpenConditions] = [$this->openDirectives, $this->openConditions];
        $this->openDirectives = [];
        $this->openConditions = [];

        try {
            while ($this->pos < $attrEnd) {
                $type = $this->tokens[$this->pos]['type'];

                if ($type === TokenType::Whitespace) {
                    $this->addChild($this->createNode(
                        kind: NodeKind::AttributeWhitespace,
                        parent: 0,
                        tokenStart: $this->pos,
                        tokenCount: 1
                    ));
                    $this->pos++;

                    continue;
                }

                if ($type === TokenType::Directive) {
                    $this->processDirective();

                    continue;
                }

                if ($type === TokenType::JsxShorthandAttribute) {
                    $this->addChild($this->createNode(
                        kind: NodeKind::JsxAttribute,
                        parent: 0,
                        tokenStart: $this->pos,
                        tokenCount: 1
                    ));
                    $this->pos++;

                    continue;
                }

                // Check for attribute extension tokens
                if (TokenType::isExtension($type)) {
                    $extension = $this->getAttributeExtensionForTokenType($type);
                    if ($extension !== null) {
                        // Lazily create reusable context
                        $this->attrExtContext ??= new AttributeParserContext($this, $attrEnd);
                        $consumed = $extension->buildAttributeNode($this->attrExtContext->reset($attrEnd));
                        $this->pos += $consumed;

                        continue;
                    }
                }

                $this->buildUnifiedAttribute($attrEnd);
            }

            $this->closeRemainingAttributeDirectives();
        } finally {
            $this->openDirectives = $savedOpenDirectives;
            $this->openConditions = $savedOpenConditions;
            array_pop($this->openElements);
        }
    }

    /**
     * Close any remaining open directives at the end of an attribute region.
     */
    protected function closeRemainingAttributeDirectives(): void
    {
        while (! empty($this->openConditions)) {
            $frame = array_pop($this->openConditions);
            $this->popIfTop($frame['currentBranchIdx']);
            $this->popIfTop($frame['blockIdx']);

            $blockIdx = $frame['blockIdx'];
            $this->nodes[$blockIdx]['tokenCount'] = $this->pos - $this->nodes[$blockIdx]['tokenStart'];
        }

        while (! empty($this->openDirectives)) {
            $frame = array_pop($this->openDirectives);
            $this->popIfTop($frame['startDirectiveIdx']);
            $this->popIfTop($frame['blockIdx']);

            $blockIdx = $frame['blockIdx'];
            $this->nodes[$blockIdx]['tokenCount'] = $this->pos - $this->nodes[$blockIdx]['tokenStart'];
        }
    }

    /**
     * Build child nodes for attribute name.
     */
    protected function buildAttributeNameParts(int $startPos, int $tokenCount, int $parentIdx): void
    {
        $this->buildParts(
            startPos: $startPos,
            endPos: $startPos + $tokenCount,
            parentIdx: $parentIdx,
            asValue: false
        );
    }

    /**
     * Build child nodes for attribute value.
     */
    protected function buildAttributeValueParts(int $startPos, int $tokenCount, int $parentIdx): void
    {
        $endPos = $startPos + $tokenCount;
        $i = $startPos;

        // Skip opening quote
        if ($i < $endPos && $this->tokens[$i]['type'] === TokenType::Quote) {
            $i++;
        }

        // Stop before closing the quote
        if ($endPos > $startPos && $this->tokens[$endPos - 1]['type'] === TokenType::Quote) {
            $endPos--;
        }

        $this->buildParts(
            startPos: $i,
            endPos: $endPos,
            parentIdx: $parentIdx,
            asValue: true
        );
    }

    /**
     * Build a unified attribute node, handling all composite attribute types.
     *
     * @param  int  $attrEnd  End boundary for attributes
     */
    protected function buildUnifiedAttribute(int $attrEnd): void
    {
        $attrStart = $this->pos;

        $bounds = $this->scanAttributeBounds($attrEnd);
        if ($bounds['length'] === 0) {
            $this->pos++; // Skip unknown things

            return;
        }

        $this->pos = $bounds['end'];

        if ($bounds['isNameValue']) {
            $attrIdx = $this->addChild($this->createNode(
                kind: NodeKind::Attribute,
                parent: 0,
                tokenStart: $attrStart,
                tokenCount: $bounds['length']
            ));

            $nameIdx = $this->createFirstChild(
                parentIdx: $attrIdx,
                kind: NodeKind::AttributeName,
                tokenStart: $attrStart,
                tokenCount: $bounds['nameCount']
            );
            $this->buildAttributeNameParts($attrStart, $bounds['nameCount'], $nameIdx);

            if ($bounds['valueCount'] > 0) {
                $valueIdx = $this->createSiblingChild(
                    prevSiblingIdx: $nameIdx,
                    parentIdx: $attrIdx,
                    kind: NodeKind::AttributeValue,
                    tokenStart: $bounds['valueStart'],
                    tokenCount: $bounds['valueCount']
                );
                $this->buildAttributeValueParts($bounds['valueStart'], $bounds['valueCount'], $valueIdx);
            }

            return;
        }

        // Standalone attribute
        $firstType = $this->tokens[$attrStart]['type'];

        if ($bounds['length'] <= 3 && ConstructScanner::isEchoStart($firstType)) {
            $this->pos = $attrStart;
            $this->processConstructInAttributes(default: NodeKind::Echo);

            return;
        }

        if ($bounds['length'] <= 5 && ConstructScanner::isPhpStart($firstType)) {
            $this->pos = $attrStart;
            $this->processConstructInAttributes(default: NodeKind::PhpTag);

            return;
        }

        $attrIdx = $this->addChild($this->createNode(
            kind: NodeKind::Attribute,
            parent: 0,
            tokenStart: $attrStart,
            tokenCount: $bounds['length']
        ));

        $nameIdx = $this->createFirstChild(
            parentIdx: $attrIdx,
            kind: NodeKind::AttributeName,
            tokenStart: $attrStart,
            tokenCount: $bounds['length']
        );

        $this->buildAttributeNameParts($attrStart, $bounds['length'], $nameIdx);
    }

    protected function findAttributeRegionEnd(): int
    {
        $attrEnd = $this->pos;
        $tokenCount = count($this->tokens);

        while ($attrEnd < $tokenCount) {
            $type = $this->tokens[$attrEnd]['type'];

            if ($type === TokenType::GreaterThan || $type === TokenType::SyntheticClose) {
                break;
            }

            if ($type === TokenType::Slash && $attrEnd + 1 < $tokenCount) {
                $next = $this->tokens[$attrEnd + 1]['type'];
                if ($next === TokenType::GreaterThan || $next === TokenType::SyntheticClose) {
                    break;
                }
            }

            $attrEnd++;
        }

        return $attrEnd;
    }

    /**
     * Scan forward to determine the full token span of the current attribute.
     *
     * Returns:
     * - length: total tokens in attribute
     * - end: absolute token index where scan stopped
     * - isNameValue: bool
     * - nameCount, valueStart, valueCount (when name/value)
     *
     * @return array{length: int, end: int, isNameValue: bool, nameCount: int, valueStart: int, valueCount: int}
     */
    protected function scanAttributeBounds(int $attrEnd): array
    {
        $attrStart = $this->pos;

        $equalsPos = -1;
        $nameEnd = -1;
        $scanPos = $this->pos;
        $lastNonWhitespace = $this->pos;

        while ($scanPos < $attrEnd) {
            $type = $this->tokens[$scanPos]['type'];

            if ($type === TokenType::Whitespace) {
                $lookAhead = $scanPos + 1;
                while ($lookAhead < $attrEnd && $this->tokens[$lookAhead]['type'] === TokenType::Whitespace) {
                    $lookAhead++;
                }
                if ($lookAhead < $attrEnd && $this->tokens[$lookAhead]['type'] === TokenType::Equals) {
                    $scanPos = $lookAhead;

                    continue;
                }
                break; // whitespace ends attribute
            }

            if ($type === TokenType::Equals) {
                $equalsPos = $scanPos;
                $nameEnd = $lastNonWhitespace;
                $scanPos++;

                while ($scanPos < $attrEnd && $this->tokens[$scanPos]['type'] === TokenType::Whitespace) {
                    $scanPos++;
                }

                if ($scanPos < $attrEnd) {
                    if ($this->tokens[$scanPos]['type'] === TokenType::Quote) {
                        $scanPos++; // opening "
                        while ($scanPos < $attrEnd && $this->tokens[$scanPos]['type'] !== TokenType::Quote) {
                            $scanPos = ConstructScanner::advancePast($this->tokens, $scanPos, $attrEnd);
                        }
                        if ($scanPos < $attrEnd) {
                            $scanPos++; // closing "
                        }
                    } else {
                        while ($scanPos < $attrEnd && $this->tokens[$scanPos]['type'] !== TokenType::Whitespace) {
                            $scanPos = ConstructScanner::advancePast($this->tokens, $scanPos, $attrEnd);
                        }
                    }
                }
                break;
            }

            $scanPos = ConstructScanner::advancePast($this->tokens, $scanPos, $attrEnd);
            $lastNonWhitespace = $scanPos;
        }

        $length = $scanPos - $attrStart;

        if ($length === 0) {
            return [
                'length' => 0,
                'end' => $scanPos,
                'isNameValue' => false,
                'nameCount' => 0,
                'valueStart' => 0,
                'valueCount' => 0,
            ];
        }

        if ($equalsPos !== -1 && $nameEnd !== -1) {
            $nameCount = $nameEnd - $attrStart;
            $valueStart = $equalsPos + 1;
            while ($valueStart < $scanPos && $this->tokens[$valueStart]['type'] === TokenType::Whitespace) {
                $valueStart++;
            }
            $valueCount = $scanPos - $valueStart;

            return [
                'length' => $length,
                'end' => $scanPos,
                'isNameValue' => true,
                'nameCount' => $nameCount,
                'valueStart' => $valueStart,
                'valueCount' => $valueCount,
            ];
        }

        return [
            'length' => $length,
            'end' => $scanPos,
            'isNameValue' => false,
            'nameCount' => 0,
            'valueStart' => 0,
            'valueCount' => 0,
        ];
    }

    /**
     * Handle the generic construct processing for getEchoes / PHP blocks in attributes.
     */
    protected function processConstructInAttributes(int $default): void
    {
        $startPos = $this->pos;
        $tokenCount = count($this->tokens);
        $startType = $this->tokens[$startPos]['type'];

        $nodeKind = ConstructScanner::getNodeKind($startType) ?? $default;
        $constructTokenCount = ConstructScanner::countConstructTokens($this->tokens, $startPos, $tokenCount);
        $this->pos += $constructTokenCount;

        $this->addChild($this->createNode(
            kind: $nodeKind,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $constructTokenCount
        ));
    }

    /**
     * Build parts (name or value) into child Text/Construct/Directive nodes.
     */
    protected function buildParts(int $startPos, int $endPos, int $parentIdx, bool $asValue): void
    {
        $i = $startPos;
        $lastChildIdx = null;

        while ($i < $endPos) {
            $type = $this->tokens[$i]['type'];
            $childNode = null;
            $childTokenCount = 1;

            if ($this->isTextLike($type, $asValue)) {
                $childNode = $this->createNode(
                    kind: NodeKind::Text,
                    parent: $parentIdx,
                    tokenStart: $i,
                    tokenCount: 1
                );
            } elseif (ConstructScanner::isConstructStart($type)) {
                $nodeKind = ConstructScanner::getNodeKind($type) ?? NodeKind::Echo;
                $childTokenCount = ConstructScanner::countConstructTokens($this->tokens, $i, $endPos);
                $childNode = $this->createNode(
                    kind: $nodeKind,
                    parent: $parentIdx,
                    tokenStart: $i,
                    tokenCount: $childTokenCount
                );
            } elseif ($asValue && $type === TokenType::Directive) {
                $dirEnd = $i + 1;
                if ($dirEnd < $endPos && $this->tokens[$dirEnd]['type'] === TokenType::DirectiveArgs) {
                    $dirEnd++;
                }
                $childTokenCount = $dirEnd - $i;
                $childNode = $this->createNode(
                    kind: NodeKind::Directive,
                    parent: $parentIdx,
                    tokenStart: $i,
                    tokenCount: $childTokenCount
                );
            } else {
                $childNode = $this->createNode(
                    kind: NodeKind::Text,
                    parent: $parentIdx,
                    tokenStart: $i,
                    tokenCount: 1
                );
            }

            $childIdx = $this->nodeCount++;
            $this->nodes[$childIdx] = $childNode;

            if ($lastChildIdx === null) {
                $this->nodes[$parentIdx]['firstChild'] = $childIdx;
            } else {
                $this->nodes[$lastChildIdx]['nextSibling'] = $childIdx;
            }
            $this->nodes[$parentIdx]['lastChild'] = $childIdx;
            $lastChildIdx = $childIdx;

            $i += $childTokenCount;
        }
    }

    protected function isTextLike(int $type, bool $asValue): bool
    {
        if ($asValue) {
            return $type === TokenType::AttributeValue || $type === TokenType::Text;
        }

        return in_array($type, [
            TokenType::AttributeName,
            TokenType::BoundAttribute,
            TokenType::EscapedAttribute,
            TokenType::ShorthandAttribute,
            TokenType::Text,
        ], true);
    }
}
