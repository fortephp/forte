<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\LexerError;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;

trait BladeCommentScanner
{
    protected function scanBladeCommentStart(): void
    {
        $start = $this->pos;

        // Consume {{--
        $this->pos += 4;

        // Emit start token
        $this->emitToken(TokenType::BladeCommentStart, $start, $this->pos);

        // Switch to comment scanning mode
        $this->state = State::BladeComment;
    }

    protected function scanBladeCommentContent(): void
    {
        $start = $this->pos;

        while ($this->pos < $this->len) {
            // Find the next occurrence of --}}
            $closePos = strpos($this->source, '--}}', $this->pos);

            if ($closePos === false) {
                // Hit EOF without finding --}}
                $this->logError(LexerError::unclosedComment(State::BladeComment, $this->len));

                if ($start < $this->len) {
                    $this->emitToken(TokenType::Text, $start, $this->len);
                }

                // Set position to EOF to signal we're done
                $this->pos = $this->len;

                // Return to the data state and continue
                $this->state = $this->returnState;
                $this->returnState = State::Data;

                return;
            }

            // Found the closing --}}
            $contentEnd = $closePos; // Position before --}}

            if ($contentEnd > $start) {
                $this->emitToken(TokenType::Text, $start, $contentEnd);
            }

            $endStart = $contentEnd; // Consume --}}
            $this->pos = $closePos + 4; // Move past --}}
            $this->emitToken(TokenType::BladeCommentEnd, $endStart, $this->pos);

            // Return to data state
            $this->state = $this->returnState;
            $this->returnState = State::Data;

            return;
        }

        // Fail safe.
        $this->logError(LexerError::unclosedComment(State::BladeComment, $this->len));

        if ($start < $this->len) {
            $this->emitToken(TokenType::Text, $start, $this->len);
        }

        $this->pos = $this->len;
        $this->state = $this->returnState;
        $this->returnState = State::Data;
    }
}
