<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;

trait RawtextScanner
{
    protected function scanRawtext(): void
    {
        $start = $this->pos;
        $len = $this->len;
        $tagName = $this->rawtextTagName;
        $tagNameLen = strlen((string) $tagName);

        while ($this->pos < $len) {
            $byte = $this->source[$this->pos];

            // Check for Blade constructs
            if ($byte === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                $next1 = $this->pos + 1 < $len ? $this->source[$this->pos + 1] : null;

                // Check for Blade patterns: {{--, {{, {!!, {{{
                if ($next1 === '{') {
                    $next2 = $this->pos + 2 < $len ? $this->source[$this->pos + 2] : null;
                    $next3 = $this->pos + 3 < $len ? $this->source[$this->pos + 3] : null;
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    $this->returnState = State::RawText;
                    if ($next2 === '-' && $next3 === '-') {
                        // Found {{-- blade comment
                        $this->scanBladeCommentStart();

                        return;
                    }
                    // Found {{ or {{{
                    $this->scanEcho();

                    return;
                } elseif ($next1 === '!' && ($this->pos + 2 < $len && $this->source[$this->pos + 2] === '!')) {
                    // Found {!!
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }
                    $this->returnState = State::RawText;
                    $this->scanRawEcho();

                    return;
                }
                // Just a regular {
                $this->pos++;

                continue;
            }

            // Directive stuff.
            if ($byte === '@' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                $prevChar = $this->pos > 0 ? $this->source[$this->pos - 1] : null;
                $canStart = $this->pos === 0 || (! ctype_alnum((string) $prevChar) && $prevChar !== '@');

                if ($canStart) {
                    // Check for escape sequences: @@, @{{, @{!!
                    $nextPos = $this->pos + 1;
                    $isEscaped = false;
                    if ($nextPos < $this->len) {
                        $nextByte = $this->source[$nextPos];
                        if ($nextByte === '@') {
                            $isEscaped = true;
                        } elseif ($nextByte === '{' && $nextPos + 1 < $this->len) {
                            $afterBrace = $this->source[$nextPos + 1];
                            if ($afterBrace === '{' || $afterBrace === '!') {
                                $isEscaped = true;
                            }
                        }
                    }

                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    if ($isEscaped) {
                        // Emit text before escape, then the escape @
                        $this->emitToken(TokenType::AtSign, $this->pos, $this->pos + 1);
                        $this->pos++;

                        return;
                    }

                    // Process directive
                    $this->returnState = State::RawText;
                    $this->scanDirective();

                    return;
                }
                $this->pos++;

                continue;
            }

            // Check for closing tag: </script> or </style> (with optional whitespace before >)
            if ($byte === '<') {
                if ($this->pos + 2 + $tagNameLen <= $len &&
                    $this->source[$this->pos + 1] === '/') {

                    // Extract potential tag name and compare
                    $potentialTagName = substr($this->source, $this->pos + 2, $tagNameLen);
                    if (strcasecmp($potentialTagName, (string) $tagName) === 0) {
                        // Check that tag name ends at word boundary (whitespace or >)
                        $afterTagPos = $this->pos + 2 + $tagNameLen;
                        if ($afterTagPos < $len) {
                            $afterTagChar = $this->source[$afterTagPos];
                            if ($afterTagChar === '>' || ctype_space((string) $afterTagChar)) {
                                // Emit text before the closing tag.
                                if ($start < $this->pos) {
                                    $this->emitToken(TokenType::Text, $start, $this->pos);
                                }

                                // Exit rawtext mode
                                $this->rawtextTagName = '';

                                // Emit the closing tag tokens: <, /, tagname
                                $this->emitToken(TokenType::LessThan, $this->pos, $this->pos + 1);
                                $this->pos++;
                                $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
                                $this->pos++;
                                $this->emitToken(TokenType::TagName, $this->pos, $this->pos + $tagNameLen);
                                $this->currentTagName = $potentialTagName;
                                $this->isClosingTag = true;
                                $this->pos += $tagNameLen;

                                // Look ahead past whitespace to check for >
                                $peekPos = $this->pos;
                                while ($peekPos < $this->len && ctype_space((string) $this->source[$peekPos])) {
                                    $peekPos++;
                                }

                                // Only consume whitespace if followed by >
                                if ($peekPos < $this->len && $this->source[$peekPos] === '>') {
                                    // Consume the whitespace and > for the closing tag.
                                    if ($this->pos < $peekPos) {
                                        $this->emitToken(TokenType::Whitespace, $this->pos, $peekPos);
                                        $this->pos = $peekPos;
                                    }
                                    $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                                    $this->pos++;
                                } else {
                                    // If we're here, we have a malformed closing tag (doesn't have its >).
                                    // We'll just emit a SyntheticClose without consuming whitespace.
                                    $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
                                }
                                $this->state = State::Data;

                                return;
                            }
                        } elseif ($afterTagPos === $len) {
                            // Tag names at EOF will just become text
                            if ($start < $this->pos) {
                                $this->emitToken(TokenType::Text, $start, $this->pos);
                            }
                            $this->rawtextTagName = '';
                            $this->emitToken(TokenType::LessThan, $this->pos, $this->pos + 1);
                            $this->pos++;
                            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
                            $this->pos++;
                            $this->emitToken(TokenType::TagName, $this->pos, $this->pos + $tagNameLen);
                            $this->pos += $tagNameLen;
                            $this->state = State::Data;

                            return;
                        }
                    }
                }

                // Treat < as text since its not a closing tag.
                $this->pos++;

                continue;
            }

            $this->pos++;
        }

        // EOE. Emit remaining content as text
        if ($start < $this->pos) {
            $this->emitToken(TokenType::Text, $start, $this->pos);
        }

        // Exit rawtext mode (unclosed element)
        $this->rawtextTagName = '';
    }
}
