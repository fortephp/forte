<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;

trait ProcessesEchoes
{
    protected function processEcho(): void
    {
        $this->processEchoSpan(TokenType::EchoEnd, NodeKind::Echo);
    }

    protected function processRawEcho(): void
    {
        $this->processEchoSpan(TokenType::RawEchoEnd, NodeKind::RawEcho);
    }

    protected function processTripleEcho(): void
    {
        $this->processEchoSpan(TokenType::TripleEchoEnd, NodeKind::TripleEcho);
    }

    protected function processEchoSpan(int $endType, int $nodeKind): void
    {
        $startPos = $this->pos;
        $this->pos++;

        $tokens = $this->tokens;
        $limit = count($tokens);

        while ($this->pos < $limit) {
            $type = $tokens[$this->pos]['type'];

            if ($type === $endType) {
                $this->pos++;
                break;
            }

            if (
                $type === TokenType::EchoStart
                || $type === TokenType::RawEchoStart
                || $type === TokenType::TripleEchoStart
                || $type === TokenType::BladeCommentStart
            ) {
                $this->emitTextSpan($startPos, $this->pos - $startPos);

                return;
            }

            $this->pos++;
        }

        $this->emitNode($nodeKind, $startPos, $this->pos - $startPos);
    }

    protected function emitTextSpan(int $start, int $length): void
    {
        if ($length <= 0) {
            return;
        }

        $this->addChild($this->createNode(
            kind: NodeKind::Text,
            parent: 0,
            tokenStart: $start,
            tokenCount: $length
        ));
    }

    protected function emitNode(int $kind, int $start, int $length): void
    {
        $this->addChild($this->createNode(
            kind: $kind,
            parent: 0,
            tokenStart: $start,
            tokenCount: $length
        ));
    }
}
