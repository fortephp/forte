<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;

trait ProcessesComments
{
    protected function createHtmlComment(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::CommentEnd, NodeKind::Comment);
    }

    protected function createBladeComment(int $startPos): void
    {
        $this->createBlockNode($startPos, TokenType::BladeCommentEnd, NodeKind::BladeComment);
    }

    protected function createBogusComment(int $startPos): void
    {
        $node = $this->createNode(
            kind: NodeKind::BogusComment,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: 1,
            data: 1
        );

        $this->addChild($node);
        $this->pos++;
    }

    protected function createConditionalComment(int $startPos): void
    {
        $endPos = $startPos + 1;
        $hasClosing = false;

        while ($endPos < count($this->tokens)) {
            if ($this->tokens[$endPos]['type'] === TokenType::ConditionalCommentEnd) {
                $hasClosing = true;
                break;
            }
            $endPos++;
        }

        $fullEndPos = $hasClosing ? $endPos + 1 : $endPos;

        $node = $this->createNode(
            kind: NodeKind::ConditionalComment,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $fullEndPos - $startPos,
            data: $hasClosing ? 1 : 0
        );

        $nodeIdx = $this->addChild($node);

        $elementStackDepth = count($this->openElements);

        $this->checkElementDepth();
        $this->openElements[] = $nodeIdx;

        $this->pos = $startPos + 1;
        while ($this->pos < $endPos) {
            $this->processToken();
        }

        while (count($this->openElements) > $elementStackDepth + 1) {
            $poppedIdx = array_pop($this->openElements);
            $this->cleanupTagNameStack($poppedIdx);
        }

        $poppedIdx = array_pop($this->openElements);
        $this->cleanupTagNameStack($poppedIdx);

        $this->pos = $fullEndPos;
    }

    protected function createProcessingInstruction(int $startPos): void
    {
        $this->createBlockNode(
            $startPos,
            TokenType::PIEnd,
            NodeKind::ProcessingInstruction
        );
    }
}
