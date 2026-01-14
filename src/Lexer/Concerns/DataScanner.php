<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\LexerError;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;

trait DataScanner
{
    private function scanData(): void
    {
        $start = $this->pos;
        $len = $this->len;

        if (! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
            $stopChars = '{<@'.$this->extensionTriggers;
            $skip = strcspn($this->source, $stopChars, $this->pos);

            if ($skip > 0) {
                $this->pos += $skip;
                if ($this->pos >= $len) {
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    return;
                }

                // If we stopped on an extension trigger, emit text and let the main loop handle it
                $stoppedChar = $this->source[$this->pos];
                if ($this->extensionTriggers !== '' && str_contains($this->extensionTriggers, (string) $stoppedChar)) {
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    return;
                }
            }
        }

        // Scan until we hit a special character: {, <, or @
        while ($this->pos < $len) {
            $byte = $this->source[$this->pos];

            if ($byte === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                // Check if the previous character is @ (escaped: @{{ or @{!!)
                $prevChar = $this->pos > 0 ? $this->source[$this->pos - 1] : null;
                if ($prevChar === '@') {
                    // Escaped echo, treat as text
                    $this->pos++;

                    continue;
                }

                $next1 = $this->pos + 1 < $len ? $this->source[$this->pos + 1] : null;

                // Check for Blade patterns: {{--, {{, {!!, {{{
                if ($next1 === '{') {
                    // Check for {{--
                    $next2 = $this->pos + 2 < $len ? $this->source[$this->pos + 2] : null;
                    $next3 = $this->pos + 3 < $len ? $this->source[$this->pos + 3] : null;
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    if ($next2 === '-' && $next3 === '-') {
                        // Found {{--
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
                    $this->scanRawEcho();

                    return;
                } else {
                    // Just a regular {, keep scanning
                    $this->pos++;
                }
            } elseif ($byte === '@') {
                // In verbatim mode, only check for @endverbatim
                if ($this->verbatim) {
                    if ($this->isEndverbatimAt($this->pos)) {
                        if ($start < $this->pos) {
                            $this->emitToken(TokenType::Text, $start, $this->pos);
                        }
                        $this->scanDirective();

                        return;
                    } else {
                        // Not @endverbatim, keep it as text
                        $this->pos++;
                    }

                    continue;  // Skip the rest of @ processing
                }

                // In PHP block mode, only check for @endphp
                if ($this->phpBlock) {
                    if ($this->isEndphpAt($this->pos)) {
                        // Found @endphp - emit PHP block content before it
                        if ($start < $this->pos) {
                            $this->emitToken(TokenType::PhpBlock, $start, $this->pos);
                        }
                        $this->scanDirective();

                        return;
                    } else {
                        // Not @endphp, keep it as PHP block content
                        $this->pos++;
                    }

                    continue;  // Skip the rest of @ processing
                }

                // Only process @ if it can start a directive
                // Check if the previous character is alphanumeric (e.g., user@example.com)
                // Skip if the previous character is @ (escaped: @@directive)
                $prevChar = $this->pos > 0 ? $this->source[$this->pos - 1] : null;
                $canStart = $this->pos === 0 || (! ctype_alnum((string) $prevChar) && $prevChar !== '@');

                if ($canStart) {
                    if ($this->phpTag) {
                        // In PHP tag mode, @ is just regular PHP content
                        $this->pos++;
                    } else {
                        // Check if this is an escape sequence: @@, @{{, @{!!, or @{{{
                        $nextPos = $this->pos + 1;
                        $isEscaped = false;
                        if ($nextPos < $this->len) {
                            $nextByte = $this->source[$nextPos];
                            if ($nextByte === '@') {
                                // @@directive
                                $isEscaped = true;
                            } elseif ($nextByte === '{' && $nextPos + 1 < $this->len) {
                                // Check for @{{, @{!!, or @{{{
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
                            $this->emitToken(TokenType::AtSign, $this->pos, $this->pos + 1);
                            $this->pos++;

                            return;
                        }

                        $this->scanDirective();

                        return;
                    }
                } else {
                    // @ can't start directive (e.g., in email), keep scanning as text
                    $this->pos++;
                }
            } elseif ($byte === '<' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                // Check for DOCTYPE
                if ($this->pos + 9 <= $len
                    && $this->source[$this->pos + 1] === '!'
                    && strncasecmp(substr($this->source, $this->pos + 2, 7), 'doctype', 7) === 0) {
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }
                    $this->scanDoctype();

                    return;
                }

                // Check for CDATA section, conditional comment end, or other <![...] patterns
                if ($this->pos + 3 <= $len
                    && $this->source[$this->pos + 1] === '!'
                    && $this->source[$this->pos + 2] === '[') {

                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    // Try CDATA first: <![CDATA[
                    if ($this->tryScanCdata()) {
                        return;
                    }

                    // Otherwise, try conditional comment end: <![endif]-->
                    $this->tryScanConditionalCommentEnd();

                    return;
                }

                // Check for downlevel-hidden conditional comment end: <!--<![endif]-->
                if ($this->pos + 7 <= $len
                    && $this->source[$this->pos + 1] === '!'
                    && $this->source[$this->pos + 2] === '-'
                    && $this->source[$this->pos + 3] === '-'
                    && $this->source[$this->pos + 4] === '<'
                    && $this->source[$this->pos + 5] === '!'
                    && $this->source[$this->pos + 6] === '[') {

                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    $this->tryScanDownlevelHiddenConditionalCommentEnd();

                    return;
                }

                // Check for the standard HTML comment: <!--
                if ($this->pos + 4 <= $len
                    && $this->source[$this->pos + 1] === '!'
                    && $this->source[$this->pos + 2] === '-'
                    && $this->source[$this->pos + 3] === '-') {

                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    // Check for conditional comment: <!--[if ...]>
                    if ($this->tryScanConditionalComment()) {
                        return;
                    }

                    // Standard HTML comment <!-- ... -->
                    $this->state = State::Comment;

                    return;
                }

                // Check for bogus comment with single dash: <!- (not <!--)
                if ($this->pos + 3 <= $len
                    && $this->source[$this->pos + 1] === '!'
                    && $this->source[$this->pos + 2] === '-'
                    && ($this->pos + 3 >= $len || $this->source[$this->pos + 3] !== '-')) {
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }

                    $bogusStart = $this->pos; // Scan until -> or >
                    $this->pos += 3; // Skip <!-
                    while ($this->pos < $len) {
                        if ($this->source[$this->pos] === '>') {
                            $this->pos++;
                            $this->emitToken(TokenType::BogusComment, $bogusStart, $this->pos);

                            return;
                        }
                        if ($this->pos + 1 < $len && $this->source[$this->pos] === '-' && $this->source[$this->pos + 1] === '>') {
                            $this->pos += 2;
                            $this->emitToken(TokenType::BogusComment, $bogusStart, $this->pos);

                            return;
                        }
                        $this->pos++;
                    }
                    $this->emitToken(TokenType::BogusComment, $bogusStart, $this->pos);

                    return;
                }

                // Check for XML declaration first: <?xml
                $beforeDecl = $this->pos;
                if ($this->tryScanDecl()) {
                    if ($start < $beforeDecl) {
                        $this->emitToken(TokenType::Text, $start, $beforeDecl);
                    }

                    return;
                }

                // Check for Processing Instruction: <?target data
                $beforePI = $this->pos;
                if ($start < $beforePI) {
                    $this->emitToken(TokenType::Text, $start, $beforePI);
                    $start = $beforePI;
                }
                if ($this->tryScanProcessingInstruction()) {
                    return;
                }

                // Check for bogus comments: <? ... or <- ...
                $beforeBogus = $this->pos;
                if ($this->tryScanBogusComment()) {
                    if ($start < $beforeBogus) {
                        $this->emitToken(TokenType::Text, $start, $beforeBogus);
                    }

                    return;
                }

                // Check for PHP tag start: <?php, <?=, or <?
                $tagLen = $this->phpTagStartLength($this->pos);
                if ($tagLen > 0) {
                    // Found PHP tag start
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }
                    $this->emitToken(TokenType::PhpTagStart, $this->pos, $this->pos + $tagLen);
                    $this->pos += $tagLen;
                    $this->phpTag = true;

                    return;
                }

                $nextChar = $this->pos + 1 < $len ? $this->source[$this->pos + 1] : null;
                $isValidTagStart = $nextChar !== null && (ctype_alpha($nextChar) || $nextChar === '/' || $nextChar === '_' || $nextChar === '>' || $nextChar === '{' || $nextChar === '@');

                if ($isValidTagStart) {
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::Text, $start, $this->pos);
                    }
                    $this->scanTagOpen();

                    return;
                } else {
                    $this->pos++;
                }
            } elseif ($byte === '?' && $this->phpTag) {
                if ($this->isPhpTagEndAt($this->pos)) {
                    if ($start < $this->pos) {
                        $this->emitToken(TokenType::PhpContent, $start, $this->pos);
                    }
                    $this->emitToken(TokenType::PhpTagEnd, $this->pos, $this->pos + 2);
                    $this->pos += 2;
                    $this->phpTag = false;

                    return;
                } else {
                    // Just a regular ?, keep scanning
                    $this->pos++;
                }
            } else {
                $this->pos++;
            }
        }

        if ($start < $this->pos) {
            if ($this->phpBlock) {
                $this->logError(LexerError::unexpectedEof(State::Data, $this->pos));
                $this->emitToken(TokenType::PhpBlock, $start, $this->pos);
            } elseif ($this->phpTag) {
                $this->emitToken(TokenType::PhpContent, $start, $this->pos);
            } elseif ($this->verbatim) {
                $this->logError(LexerError::unexpectedEof(State::Data, $this->pos));
                $this->emitToken(TokenType::Text, $start, $this->pos);
            } else {
                $this->emitToken(TokenType::Text, $start, $this->pos);
            }
        }
    }
}
