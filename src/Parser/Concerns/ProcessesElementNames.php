<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\ConstructScanner;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
trait ProcessesElementNames
{
    /**
     * @return array{int, int} [tokenCount, genericOffset]
     */
    protected function scanElementName(): array
    {
        $tokens = $this->tokens;
        $limit = count($tokens);

        $count = 0;
        $genericOffset = 0;
        $tagNameStart = $this->pos;

        while ($this->pos < $limit) {
            $type = $tokens[$this->pos]['type'];

            if ($this->isElementNameTerminator($type)) {
                if ($type === TokenType::TsxGenericType) {
                    $genericOffset = $this->pos - $tagNameStart + 1;
                }
                break;
            }

            if ($type === TokenType::TagName) {
                $count++;
                $this->pos++;

                continue;
            }

            if (ConstructScanner::isConstructStart($type)) {
                [$newPos, $constructCount] = ConstructScanner::scanConstruct($tokens, $this->pos, $limit);
                $count += $constructCount;
                $this->pos = $newPos;

                continue;
            }

            // Unexpected token ends the name
            break;
        }

        return [$count, $genericOffset];
    }

    /**
     * @param  int  $startPos  Start token position
     * @param  int  $tokenCount  Number of tokens in the element name
     * @param  int  $parentIdx  Index of the ElementName/ClosingElementName node
     */
    protected function buildElementNameParts(int $startPos, int $tokenCount, int $parentIdx): void
    {
        $tokens = $this->tokens;
        $endPos = $startPos + $tokenCount;
        $i = $startPos;
        $lastChildIdx = null;

        while ($i < $endPos) {
            $type = $tokens[$i]['type'];
            $childTokenCount = 1;
            $childNode = null;

            if ($type === TokenType::TagName) {
                $childNode = $this->createNode(
                    kind: NodeKind::Text,
                    parent: $parentIdx,
                    tokenStart: $i,
                    tokenCount: 1
                );
            } elseif (ConstructScanner::isConstructStart($type)) {
                $nodeKind = ConstructScanner::getNodeKind($type) ?? NodeKind::Echo;
                $childTokenCount = ConstructScanner::countConstructTokens($tokens, $i, $endPos);
                $childNode = $this->createNode(
                    kind: $nodeKind,
                    parent: $parentIdx,
                    tokenStart: $i,
                    tokenCount: $childTokenCount
                );
            }

            if ($childNode !== null) {
                $lastChildIdx = $this->linkChildNode($parentIdx, $lastChildIdx, $childNode);
            }

            $i += $childTokenCount;
        }
    }

    protected function getTagNameText(int $startPos, int $tokenCount): string
    {
        $tokens = $this->tokens;
        $total = count($tokens);
        $text = '';

        for ($i = 0; $i < $tokenCount; $i++) {
            $idx = $startPos + $i;
            if ($idx >= $total) {
                break;
            }

            if ($tokens[$idx]['type'] === TokenType::TagName) {
                $text .= substr(
                    $this->source,
                    $tokens[$idx]['start'],
                    $tokens[$idx]['end'] - $tokens[$idx]['start']
                );
            }
        }

        return $text;
    }

    protected function getOpenElementTagName(int $nodeIdx): string
    {
        $node = $this->nodes[$nodeIdx];

        if ($node['kind'] !== NodeKind::Element) {
            return '';
        }

        $tagNameCount = $node['data'];
        $tagNameStart = $node['tokenStart'] + 1; // Skip "<"

        return $this->getTagNameText($tagNameStart, $tagNameCount);
    }

    protected function isDynamicTagName(int $startPos, int $tokenCount): bool
    {
        $tokens = $this->tokens;
        $total = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $idx = $startPos + $i;
            if ($idx >= $total) {
                break;
            }
            if ($tokens[$idx]['type'] !== TokenType::TagName) {
                return true;
            }
        }

        return false;
    }

    private function isElementNameTerminator(int $type): bool
    {
        return $type === TokenType::Whitespace
            || $type === TokenType::GreaterThan
            || $type === TokenType::Slash
            || $type === TokenType::Directive
            || $type === TokenType::TsxGenericType;
    }

    /**
     * @phpstan-param  FlatNode  $childNode
     *
     * @return int The new child's index
     */
    private function linkChildNode(int $parentIdx, ?int $lastChildIdx, array $childNode): int
    {
        $childIdx = $this->nodeCount++;
        $this->nodes[$childIdx] = $childNode;

        if ($lastChildIdx === null) {
            $this->nodes[$parentIdx]['firstChild'] = $childIdx;
        } else {
            $this->nodes[$lastChildIdx]['nextSibling'] = $childIdx;
        }
        $this->nodes[$parentIdx]['lastChild'] = $childIdx;

        return $childIdx;
    }
}
