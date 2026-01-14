<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\ConstructScanner;
use Forte\Parser\NodeKind;

trait ProcessesMarkup
{
    /**
     * Create Verbatim node (@verbatim...@endverbatim).
     */
    protected function createVerbatim(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::VerbatimEnd, NodeKind::Verbatim);
    }

    /**
     * Create Doctype node (<!DOCTYPE>).
     */
    protected function createDoctype(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::DoctypeEnd, NodeKind::Doctype);
    }

    /**
     * Create a CDATA section node (<![CDATA[...]]>).
     */
    protected function createCdata(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::CdataEnd, NodeKind::Cdata);
    }

    protected function createDecl(int $startPos): void
    {
        $tokens = $this->tokens;
        $tokenCount = count($tokens);

        $endPos = $this->pos;
        $hasClosing = false;
        while ($endPos < $tokenCount) {
            if ($tokens[$endPos]['type'] === TokenType::DeclEnd) {
                $hasClosing = true;
                break;
            }
            $endPos++;
        }

        $declTokenCount = $hasClosing ? ($endPos + 1 - $startPos) : ($endPos - $startPos);

        $declIdx = $this->addChild($this->createNode(
            kind: NodeKind::Decl,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $declTokenCount,
            data: $hasClosing ? 1 : 0
        ));

        $this->checkElementDepth();
        $this->openElements[] = $declIdx;

        $this->pos++;

        while ($this->pos < $tokenCount) {
            $type = $tokens[$this->pos]['type'];

            if ($type === TokenType::DeclEnd) {
                $this->pos++;
                break;
            }

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

            if ($type === TokenType::EchoStart
                || $type === TokenType::RawEchoStart
                || $type === TokenType::TripleEchoStart) {
                $this->processDeclEcho();

                continue;
            }

            if ($type === TokenType::AttributeName
                || $type === TokenType::BoundAttribute
                || $type === TokenType::EscapedAttribute
                || $type === TokenType::ShorthandAttribute) {
                $this->buildDeclAttribute($endPos);

                continue;
            }

            $this->pos++;
        }

        array_pop($this->openElements);
    }

    protected function processDeclEcho(): void
    {
        $startPos = $this->pos;
        $tokenCount = count($this->tokens);
        $startType = $this->tokens[$startPos]['type'];

        $nodeKind = ConstructScanner::getNodeKind($startType) ?? NodeKind::Echo;
        $constructTokenCount = ConstructScanner::countConstructTokens($this->tokens, $startPos, $tokenCount);
        $this->pos += $constructTokenCount;

        $this->addChild($this->createNode(
            kind: $nodeKind,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $constructTokenCount
        ));
    }

    protected function buildDeclAttribute(int $attrEnd): void
    {
        $tokens = $this->tokens;
        $tokenCount = count($tokens);

        $attrStart = $this->pos;
        $nameStart = $this->pos;
        $nameCount = 0;

        while ($this->pos < $tokenCount && $this->pos < $attrEnd) {
            $type = $tokens[$this->pos]['type'];
            if ($type === TokenType::AttributeName
                || $type === TokenType::EchoStart
                || $type === TokenType::RawEchoStart
                || $type === TokenType::TripleEchoStart) {
                $nameCount++;
                $this->pos++;

                continue;
            }
            break;
        }

        if ($nameCount === 0) {
            $this->pos++;

            return;
        }

        [$hasValue, $valueStart, $valueCount] = $this->scanDeclEqualsAndValue($attrEnd, $tokenCount);

        $attrTokenCount = $this->pos - $attrStart;

        $attrIdx = $this->addChild($this->createNode(
            kind: NodeKind::Attribute,
            parent: 0,
            tokenStart: $attrStart,
            tokenCount: $attrTokenCount
        ));

        // Name child
        $nameIdx = $this->createFirstChild(
            parentIdx: $attrIdx,
            kind: NodeKind::AttributeName,
            tokenStart: $nameStart,
            tokenCount: $nameCount
        );
        $this->buildDeclAttributeParts($nameStart, $nameCount, $nameIdx, false);

        if ($hasValue && $valueCount > 0) {
            $valueIdx = $this->createSiblingChild(
                prevSiblingIdx: $nameIdx,
                parentIdx: $attrIdx,
                kind: NodeKind::AttributeValue,
                tokenStart: $valueStart,
                tokenCount: $valueCount
            );
            $this->buildDeclAttributeParts($valueStart, $valueCount, $valueIdx, true);
        }
    }

    /**
     * @return int Number of tokens consumed
     */
    protected function scanDeclAttributeValue(int $attrEnd): int
    {
        $tokens = $this->tokens;
        $tokenCount = count($tokens);

        if ($this->pos >= $tokenCount || $tokens[$this->pos]['type'] !== TokenType::Quote) {
            return 0;
        }

        $count = 1;
        $this->pos++;

        while ($this->pos < $tokenCount && $this->pos < $attrEnd) {
            $type = $tokens[$this->pos]['type'];

            if ($type === TokenType::Quote) {
                $count++;
                $this->pos++;
                break;
            }

            if ($type === TokenType::DeclEnd) {
                break;
            }

            $count++;
            $this->pos++;
        }

        return $count;
    }

    protected function buildDeclAttributeParts(int $start, int $count, int $parentIdx, bool $isValue): void
    {
        $tokens = $this->tokens;
        $lastChildIdx = -1;

        for ($i = 0; $i < $count; $i++) {
            $tokenPos = $start + $i;
            $type = $tokens[$tokenPos]['type'];

            if ($isValue) {
                // Skip structural quote/end tokens in values
                if ($type === TokenType::Quote
                    || $type === TokenType::EchoEnd
                    || $type === TokenType::RawEchoEnd
                    || $type === TokenType::TripleEchoEnd) {
                    continue;
                }
            }

            $nodeKind = $this->declPartNodeKind($type, $isValue);

            $partIdx = $this->nodeCount++;
            $this->nodes[$partIdx] = $this->createNode(
                kind: $nodeKind,
                parent: $parentIdx,
                tokenStart: $tokenPos,
                tokenCount: 1
            );

            if ($lastChildIdx === -1) {
                $this->nodes[$parentIdx]['firstChild'] = $partIdx;
            } else {
                $this->nodes[$lastChildIdx]['nextSibling'] = $partIdx;
            }
            $this->nodes[$parentIdx]['lastChild'] = $partIdx;
            $lastChildIdx = $partIdx;
        }
    }

    /**
     * @return array{bool, int, int}
     */
    private function scanDeclEqualsAndValue(int $attrEnd, int $tokenCount): array
    {
        $tokens = $this->tokens;

        $hasValue = false;
        $valueStart = 0;
        $valueCount = 0;

        $checkPos = $this->pos;
        if ($checkPos < $tokenCount && $tokens[$checkPos]['type'] === TokenType::Whitespace) {
            $checkPos++;
        }

        if ($checkPos < $tokenCount && $tokens[$checkPos]['type'] === TokenType::Equals) {
            $this->pos = $checkPos + 1;

            if ($this->pos < $tokenCount && $tokens[$this->pos]['type'] === TokenType::Whitespace) {
                $this->pos++;
            }

            if ($this->pos < $tokenCount) {
                $valueStart = $this->pos;
                $valueCount = $this->scanDeclAttributeValue($attrEnd);
                $hasValue = $valueCount > 0;
            }
        }

        return [$hasValue, $valueStart, $valueCount];
    }

    private function declPartNodeKind(int $type, bool $isValue): int
    {
        return match ($type) {
            TokenType::EchoStart => NodeKind::Echo,
            TokenType::RawEchoStart => NodeKind::RawEcho,
            TokenType::TripleEchoStart => NodeKind::TripleEcho,
            default => NodeKind::Text,
        };
    }

    protected function emitSingleTokenText(): void
    {
        $startPos = $this->pos;
        $this->pos++;

        $this->addChild($this->createNode(
            kind: NodeKind::Text,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: 1
        ));
    }
}
