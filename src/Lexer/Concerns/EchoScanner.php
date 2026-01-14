<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\ErrorReason;
use Forte\Lexer\LexerError;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;
use Forte\Support\ScanPrimitives;

trait EchoScanner
{
    protected function scanEcho(): void
    {
        $start = $this->pos;

        if ($this->peek() === '{'
            && $this->peekAhead(1) === '{'
            && $this->peekAhead(2) === '{'
        ) {
            $this->scanTripleEcho();

            return;
        }

        $this->emitToken(TokenType::EchoStart, $start, $start + 2);
        $this->pos += 2;

        // Scan content until }}
        $contentStart = $this->pos;

        while ($this->pos < $this->len) {
            $byte = $this->source[$this->pos];

            if ($byte === "'") {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, "'");
            } elseif ($byte === '"') {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, '"');
            } elseif ($byte === '`') {
                $this->pos++;
                ScanPrimitives::skipBacktickString($this->source, $this->pos, $this->len);
            } elseif ($byte === '<' && $this->peekAhead(1) === '<' && $this->peekAhead(2) === '<') {
                $this->pos += 3;
                ScanPrimitives::skipHeredoc($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '*') {
                $this->pos += 2;
                ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '/') {
                $this->pos += 2;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '#') {
                $this->pos++;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '{' || $byte === '@' || $byte === '<') {
                if ($this->detectConstruct(self::CONSTRUCT_ALL)) {
                    $this->logError(new LexerError(
                        State::EchoContent,
                        ErrorReason::ConstructCollision,
                        $this->pos
                    ));

                    if ($contentStart < $this->pos) {
                        $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
                    }

                    $this->state = $this->returnState;
                    $this->returnState = State::Data;

                    return;
                }

                $this->pos++;
            } elseif ($byte === '}' && $this->peekAhead(1) === '}') {
                if ($contentStart < $this->pos) {
                    $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
                }
                $this->emitToken(TokenType::EchoEnd, $this->pos, $this->pos + 2);
                $this->pos += 2;
                $this->state = $this->returnState;
                $this->returnState = State::Data;

                return;
            } else {
                $this->pos++;
            }
        }

        $this->logError(LexerError::unexpectedEof(State::EchoContent, $this->len));
        if ($contentStart < $this->pos) {
            $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
        }
        $this->setPosition($this->len);  // Prevent duplicate tokens
        $this->state = $this->returnState;
        $this->returnState = State::Data;
    }

    protected function scanRawEcho(): void
    {
        $start = $this->pos;

        $this->emitToken(TokenType::RawEchoStart, $start, $start + 3);
        $this->pos += 3;

        $contentStart = $this->pos;

        while ($this->pos < $this->len) {
            $byte = $this->source[$this->pos];

            if ($byte === "'") {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, "'");
            } elseif ($byte === '"') {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, '"');
            } elseif ($byte === '`') {
                $this->pos++;
                ScanPrimitives::skipBacktickString($this->source, $this->pos, $this->len);
            } elseif ($byte === '<' && $this->peekAhead(1) === '<' && $this->peekAhead(2) === '<') {
                $this->pos += 3;
                ScanPrimitives::skipHeredoc($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '*') {
                $this->pos += 2;
                ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '/') {
                $this->pos += 2;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '#') {
                $this->pos++;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '{' || $byte === '@' || $byte === '<') {
                if ($this->detectConstruct(self::CONSTRUCT_ALL)) {
                    $this->logError(new LexerError(
                        State::RawEchoContent,
                        ErrorReason::ConstructCollision,
                        $this->pos
                    ));

                    if ($contentStart < $this->pos) {
                        $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
                    }

                    $this->state = $this->returnState;
                    $this->returnState = State::Data;

                    return;
                }

                $this->pos++;
            } elseif ($byte === '!' && $this->peekAhead(1) === '!' && $this->peekAhead(2) === '}') {
                if ($contentStart < $this->pos) {
                    $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
                }
                $this->emitToken(TokenType::RawEchoEnd, $this->pos, $this->pos + 3);
                $this->pos += 3;
                $this->state = $this->returnState;
                $this->returnState = State::Data;

                return;
            } else {
                $this->pos++;
            }
        }

        $this->logError(LexerError::unexpectedEof(State::RawEchoContent, $this->len));
        if ($contentStart < $this->pos) {
            $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
        }
        $this->setPosition($this->len);  // Prevent duplicate tokens
        $this->state = $this->returnState;
        $this->returnState = State::Data;
    }

    protected function scanTripleEcho(): void
    {
        $start = $this->pos;

        $this->emitToken(TokenType::TripleEchoStart, $start, $start + 3);
        $this->pos += 3;

        // Scan content until }}}
        $contentStart = $this->pos;

        while ($this->pos < $this->len) {
            $byte = $this->source[$this->pos];

            if ($byte === "'") {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, "'");
            } elseif ($byte === '"') {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, '"');
            } elseif ($byte === '`') {
                $this->pos++;
                ScanPrimitives::skipBacktickString($this->source, $this->pos, $this->len);
            } elseif ($byte === '<' && $this->peekAhead(1) === '<' && $this->peekAhead(2) === '<') {
                $this->pos += 3;
                ScanPrimitives::skipHeredoc($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '*') {
                $this->pos += 2;
                ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
            } elseif ($byte === '/' && $this->peekAhead(1) === '/') {
                $this->pos += 2;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '#') {
                $this->pos++;
                $this->skipLineCommentWithWarnings();
            } elseif ($byte === '{' || $byte === '@' || $byte === '<') {
                if ($this->detectConstruct(self::CONSTRUCT_ALL)) {
                    $this->logError(new LexerError(
                        State::TripleEchoContent,
                        ErrorReason::ConstructCollision,
                        $this->pos
                    ));

                    if ($contentStart < $this->pos) {
                        $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
                    }

                    $this->state = $this->returnState;
                    $this->returnState = State::Data;

                    return;
                }

                $this->pos++;
            } elseif ($byte === '}' && $this->peekAhead(1) === '}' && $this->peekAhead(2) === '}') {
                // Closing }}}

                if ($contentStart < $this->pos) {
                    $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
                }
                $this->emitToken(TokenType::TripleEchoEnd, $this->pos, $this->pos + 3);
                $this->pos += 3;
                $this->state = $this->returnState;
                $this->returnState = State::Data;

                return;
            } else {
                $this->pos++;
            }
        }

        $this->logError(LexerError::unexpectedEof(State::TripleEchoContent, $this->len));
        if ($contentStart < $this->pos) {
            $this->emitToken(TokenType::EchoContent, $contentStart, $this->pos);
        }
        $this->setPosition($this->len);  // Prevent duplicate tokens
        $this->state = $this->returnState;
        $this->returnState = State::Data;
    }
}
