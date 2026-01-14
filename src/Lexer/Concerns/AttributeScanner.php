<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;

trait AttributeScanner
{
    protected function scanAttrName(): void
    {
        $start = $this->pos;

        // Check for attribute extensions first (e.g., ...$spread, #ref, *directive)
        if ($this->tryAttributeExtension()) {
            $this->handlePostAttributeName();

            return;
        }

        // Check for special Blade attribute patterns at the start
        $tokenType = TokenType::AttributeName;

        if ($this->pos < $this->len && $this->source[$this->pos] === ':') {
            if ($this->pos + 1 < $this->len && $this->source[$this->pos + 1] === ':') {
                // ::name - EscapedAttribute
                $tokenType = TokenType::EscapedAttribute;
            } elseif ($this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '$') {
                // :$name - ShorthandAttribute
                $tokenType = TokenType::ShorthandAttribute;
            } else {
                // :name - BoundAttribute
                $tokenType = TokenType::BoundAttribute;
            }
        }

        // Scan attribute name
        while (true) {
            if ($this->pos >= $this->len) {
                break;
            }

            $byte = $this->source[$this->pos];

            // Check for Blade echo in the attribute name (e.g., class-{{ $thing }})
            if ($byte === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                // Emit attribute name so far if we have any
                if ($this->pos > $start) {
                    $this->emitToken($tokenType, $start, $this->pos);
                    $start = $this->pos; // Update start to avoid double-emission
                }

                // Try to scan Blade echo
                $savedPos = $this->pos;
                if ($this->peekAhead(1) === '{') {
                    // Check for {{-- (blade comment)
                    $savedState = $this->state;

                    if ($this->peekAhead(2) === '-' && $this->peekAhead(3) === '-') {
                        $this->returnState = State::Data;
                        $this->scanBladeCommentStart();
                        $this->scanBladeCommentContent();
                        $this->state = $savedState;

                        $start = $this->pos;

                        continue;
                    }

                    // Scan {{ or {{{ echo
                    $this->returnState = State::Data;
                    $this->scanEcho();
                    $this->state = $savedState;

                    $start = $this->pos;

                    continue;
                } elseif ($this->peekAhead(1) === '!' && $this->peekAhead(2) === '!') {
                    // Scan {!! raw echo
                    $savedState = $this->state;
                    $this->returnState = State::Data;
                    $this->scanRawEcho();
                    $this->state = $savedState;

                    $start = $this->pos;

                    continue;
                }

                // Not a Blade echo. Restore position and break (end of attribute name)
                $this->pos = $savedPos;
                break;
            }

            // Check for PHP tag in the attribute name (e.g., data-<?php echo 'thing'; ? >more)
            if ($byte === '<' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '?') {
                // Emit attribute name so far if we have any
                if ($this->pos > $start) {
                    $this->emitToken($tokenType, $start, $this->pos);
                    $start = $this->pos; // Update start to avoid double-emission
                }

                if ($this->tryScanPhpTag()) {
                    $start = $this->pos;

                    continue;
                }

                break;
            }

            // Check for Blade directive in the attribute name (e.g., class-@if($x)thing)
            if ($byte === '@' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                $tempPos = $this->pos + 1; // Skip @
                $nameStart = $tempPos;

                // Look ahead to find the potential directive name.
                while ($tempPos < $this->len) {
                    $dirByte = $this->source[$tempPos];
                    if (ctype_alnum((string) $dirByte) || $dirByte === '_') {
                        $tempPos++;
                    } else {
                        break;
                    }
                }

                $isKnownDirective = false;
                if ($tempPos > $nameStart) {
                    $name = substr($this->source, $nameStart, $tempPos - $nameStart);
                    $isKnownDirective = $this->directives()->isDirective($name);
                }

                if ($isKnownDirective) {
                    // Emit attribute name so far if we have any
                    if ($this->pos > $start) {
                        $this->emitToken($tokenType, $start, $this->pos);
                    }

                    // Scan directive
                    $savedState = $this->state;
                    $this->returnState = State::Data;
                    $this->scanDirective();
                    $this->state = $savedState;

                    $start = $this->pos;

                    continue;
                }

                $this->pos++;

                continue;
            }

            // Regular attribute name characters
            // Includes: alphanumeric, dash, colon, underscore, $, @, .
            // @ and . are needed for Vue/Alpine event handlers: @click.prevent.stop
            if (ctype_alnum((string) $byte) || $byte === '-' || $byte === ':' || $byte === '_' || $byte === '$' || $byte === '@' || $byte === '.') {
                $this->pos++;
            } else {
                break;
            }
        }

        // Emit attribute name
        if ($start < $this->pos) {
            $this->emitToken($tokenType, $start, $this->pos);
        }

        if ($this->pos >= $this->len) {
            // EOF. Unclosed tag
            $this->state = State::Data;

            return;
        }

        $char = $this->source[$this->pos];

        if ($char === '=') {
            // Attribute has a value
            $this->emitToken(TokenType::Equals, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::BeforeAttrValue;
        } elseif ($this->inXmlDeclaration && $char === '?' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '>') {
            // Boolean attribute, end of XML declaration
            $this->emitToken(TokenType::DeclEnd, $this->pos, $this->pos + 2);
            $this->pos += 2;
            $this->inXmlDeclaration = false;
            $this->state = State::Data;
        } elseif ($char === '>') {
            // Boolean attribute, end of tag
            $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::Data;
        } elseif ($char === '/') {
            // Boolean attribute, self-closing tag
            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
            $this->pos++;

            // Expect >
            if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
                $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                $this->pos++;
                $this->state = State::Data;
            } else {
                // Malformed, but continue
                $this->state = State::BeforeAttrName;
            }
        } elseif (ctype_space((string) $char)) {
            // Whitespace after attribute name
            $this->state = State::AfterAttrName;
        } elseif ($char === '<') {
            // New tag starting without closing the current tag (e.g., JSX/TypeScript-style generics: <Map<Record<)
            // Let BeforeAttrName handle the <, which will emit SyntheticClose if needed
            $this->state = State::BeforeAttrName;
        } else {
            // Unexpected character. Emit as text so we can recover it later
            $this->emitToken(TokenType::Text, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::BeforeAttrName;
        }
    }

    protected function scanAfterAttrName(): void
    {
        $this->skipAndEmitWhitespace();

        // Check what comes after whitespace
        if ($this->pos >= $this->len) {
            // EOF. Unclosed tag
            $this->state = State::Data;

            return;
        }

        $char = $this->source[$this->pos];

        if ($char === '=') {
            // Attribute has a value
            $this->emitToken(TokenType::Equals, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::BeforeAttrValue;
        } elseif ($this->inXmlDeclaration && $char === '?' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '>') {
            // Boolean attribute, end of XML declaration
            $this->emitToken(TokenType::DeclEnd, $this->pos, $this->pos + 2);
            $this->pos += 2;
            $this->inXmlDeclaration = false;
            $this->state = State::Data;
        } elseif ($char === '>') {
            // Boolean attribute, end of tag
            $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::Data;
        } elseif ($char === '/') {
            // Boolean attribute, self-closing tag
            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
            $this->pos++;

            // Expect >
            if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
                $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                $this->pos++;
                $this->state = State::Data;
            } else {
                // Malformed
                $this->state = State::BeforeAttrName;
            }
        } else {
            // Some other character; we'll let BeforeAttrName handle it
            $this->state = State::BeforeAttrName;
        }
    }

    protected function scanBeforeAttrValue(): void
    {
        $this->skipAndEmitWhitespace();

        // Check what comes after whitespace
        if ($this->pos >= $this->len) {
            // EOF. Unclosed tag
            $this->state = State::Data;

            return;
        }

        $char = $this->source[$this->pos];

        // Prioritize Blade getEchoes over JSX-style attributes
        if ($char === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
            // Check for Blade echo patterns: {{, {!!, {{{
            if ($this->peekAhead(1) === '{') {
                // {{ or {{{
                $savedState = $this->state;
                $this->returnState = State::BeforeAttrName;
                $this->scanEcho();
                $this->state = State::BeforeAttrName;

                return;
            } elseif ($this->peekAhead(1) === '!' && $this->peekAhead(2) === '!') {
                // {!!
                $savedState = $this->state;
                $this->returnState = State::BeforeAttrName;
                $this->scanRawEcho();
                $this->state = State::BeforeAttrName;

                return;
            }

            // Not a Blade echo, try JSX-style attribute value
            if ($this->scanJsxAttributeValue()) {
                $this->state = State::BeforeAttrName;

                return;
            }

            // Not JSX, treat as unquoted value
            $this->state = State::AttrValueUnquoted;

            return;
        }

        // Check for ({expression}) pattern
        if ($char === '(' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '{') {
            if ($this->scanJsxAttributeValue()) {
                $this->state = State::BeforeAttrName;

                return;
            }

            $this->state = State::AttrValueUnquoted;

            return;
        }

        if ($char === '"' || $char === "'") {
            // Quoted attribute value
            $this->state = State::AttrValueQuoted;
        } elseif ($this->inXmlDeclaration && $char === '?' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '>') {
            // Empty attribute value, end of XML declaration
            $this->emitToken(TokenType::DeclEnd, $this->pos, $this->pos + 2);
            $this->pos += 2;
            $this->inXmlDeclaration = false;
            $this->state = State::Data;
        } elseif ($char === '>') {
            // Empty attribute value, end of tag
            $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::Data;
        } else {
            // Unquoted attribute value
            $this->state = State::AttrValueUnquoted;
        }
    }

    protected function scanAttrValueQuoted(): void
    {
        $start = $this->pos;
        $quote = $this->source[$this->pos]; // " or '

        // Emit the opening quote and skip.
        $this->emitToken(TokenType::Quote, $start, $start + 1);
        $this->pos++;

        $valueStart = $this->pos;

        // Scan for closing quote, {, or @
        while (true) {
            if ($this->pos >= $this->len) {
                // EOF
                if ($this->pos > $valueStart) {
                    $this->emitToken(TokenType::AttributeValue, $valueStart, $this->pos);
                }
                $this->state = State::BeforeAttrName;

                return;
            }

            // Find the next quote, {, or @ character
            $quotePos = strpos($this->source, (string) $quote, $this->pos);
            $bracePos = strpos($this->source, '{', $this->pos);
            $atPos = strpos($this->source, '@', $this->pos);

            // Find minimum position (next interesting character)
            $nextPos = $this->len;
            if ($quotePos !== false && $quotePos < $nextPos) {
                $nextPos = $quotePos;
            }
            if ($bracePos !== false && $bracePos < $nextPos) {
                $nextPos = $bracePos;
            }
            if ($atPos !== false && $atPos < $nextPos) {
                $nextPos = $atPos;
            }

            if ($nextPos === $this->len) {
                // No quote, {, or @ found
                $this->pos = $this->len;
                if ($this->pos > $valueStart) {
                    $this->emitToken(TokenType::AttributeValue, $valueStart, $this->pos);
                }
                $this->state = State::BeforeAttrName;

                return;
            }

            $this->pos = $nextPos;
            $byte = $this->source[$this->pos];

            if ($byte === $quote) {
                // Check if this quote is escaped
                $backslashCount = 0;
                $checkPos = $this->pos - 1;
                while ($checkPos >= $valueStart && $this->source[$checkPos] === '\\') {
                    $backslashCount++;
                    $checkPos--;
                }

                if ($backslashCount % 2 === 1) {
                    $this->pos++;

                    continue;
                }

                // Found unescaped closing quote
                if ($this->pos > $valueStart) {
                    $this->emitToken(TokenType::AttributeValue, $valueStart, $this->pos);
                }

                // Emit closing quote
                $this->emitToken(TokenType::Quote, $this->pos, $this->pos + 1);
                $this->pos++;
                $this->state = State::BeforeAttrName;

                return;
            } elseif ($byte === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                // Potential echo delimiter
                $savedPos = $this->pos;

                // Check for Blade patterns: {{--, {{, {!!, {{{
                if ($this->peekAhead(1) === '{') {
                    // Check for {{-- (blade comment)
                    if ($this->peekAhead(2) === '-' && $this->peekAhead(3) === '-') {
                        // Emit attribute value content before comment if any
                        if ($savedPos > $valueStart) {
                            $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                        }
                        // Scan blade comment (start + content)
                        $this->returnState = State::AttrValueQuoted;
                        $this->scanBladeCommentStart();
                        $this->scanBladeCommentContent();
                        $valueStart = $this->pos; // Resume after comment

                        continue;
                    }
                    // Found {{ or {{{
                    if ($savedPos > $valueStart) {
                        $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                    }
                    $this->returnState = State::AttrValueQuoted;
                    $this->scanEcho();
                    $valueStart = $this->pos; // Resume after echo

                    continue;
                } elseif ($this->peekAhead(1) === '!' && $this->peekAhead(2) === '!') {
                    // Found {!!
                    if ($savedPos > $valueStart) {
                        $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                    }
                    $this->returnState = State::AttrValueQuoted;
                    $this->scanRawEcho();
                    $valueStart = $this->pos; // Resume after echo

                    continue;
                }

                // Not an echo, just a regular {
                $this->pos = $savedPos + 1;
            } elseif ($byte === '@' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                // Potential directive
                // Check if @ can start a directive
                $canStart = $this->pos === $valueStart || ! ctype_alnum((string) $this->source[$this->pos - 1]);

                if ($canStart) {
                    $savedPos = $this->pos;

                    // Emit attribute value content before directive if any
                    if ($savedPos > $valueStart) {
                        $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                    }

                    // Scan directive
                    $this->returnState = State::AttrValueQuoted;
                    $this->scanDirective();
                    $valueStart = $this->pos; // Resume after directive

                    continue;
                }

                // Not a directive, just a regular @
                $this->pos++;
            } else {
                // Fail-safe. We _shouldn't_ get here, but the real world is messy
                $this->pos++;
            }
        }
    }

    protected function scanAttrValueUnquoted(): void
    {
        $valueStart = $this->pos;

        // Scan until whitespace, >, XML declarations, or EOF
        while (true) {
            if ($this->pos >= $this->len) {
                break;
            }

            $byte = $this->source[$this->pos];

            // Check for XML declaration ending
            if ($this->inXmlDeclaration && $byte === '?' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '>') {
                break;
            }

            // Check for the end of an unquoted value
            if ($byte === ' ' || $byte === "\t" || $byte === "\n" || $byte === "\r" || $byte === '>') {
                break;
            }

            // Check for PHP tag in unquoted value
            if ($byte === '<' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '?') {
                $savedPos = $this->pos;

                // Emit attribute value content before PHP tag if any
                if ($savedPos > $valueStart) {
                    $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                }

                // Try to scan PHP tag
                if ($this->tryScanPhpTag()) {
                    $valueStart = $this->pos;

                    continue;
                }

                // Not a PHP tag
                break;
            }

            // Check for Blade echo in unquoted value
            if ($byte === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                $savedPos = $this->pos;

                // Check for Blade patterns: {{--, {{, {!!, {{{
                if ($this->peekAhead(1) === '{') {
                    // Check for {{-- (blade comment)
                    if ($this->peekAhead(2) === '-' && $this->peekAhead(3) === '-') {
                        // Emit attribute value content before comment if any
                        if ($savedPos > $valueStart) {
                            $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                        }
                        // Scan blade comment (start + content)
                        $this->returnState = State::AttrValueUnquoted;
                        $this->scanBladeCommentStart();
                        $this->scanBladeCommentContent();
                        $valueStart = $this->pos; // Resume after comment

                        continue;
                    }
                    // Found {{ or {{{
                    if ($savedPos > $valueStart) {
                        $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                    }
                    $this->returnState = State::AttrValueUnquoted;
                    $this->scanEcho();
                    $valueStart = $this->pos; // Resume after echo

                    continue;
                } elseif ($this->peekAhead(1) === '!' && $this->peekAhead(2) === '!') {
                    // Found {!!
                    if ($savedPos > $valueStart) {
                        $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                    }
                    $this->returnState = State::AttrValueUnquoted;
                    $this->scanRawEcho();
                    $valueStart = $this->pos; // Resume after echo

                    continue;
                }

                // Not an echo, just a regular {
                $this->pos = $savedPos + 1;

                continue;
            }

            // Check for Blade directive in unquoted value
            if ($byte === '@' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                $savedPos = $this->pos;

                // Check if @ can start a directive
                $canStart = $this->pos === $valueStart || ! ctype_alnum((string) $this->source[$this->pos - 1]);

                if ($canStart) {
                    // Emit attribute value content before directive if any
                    if ($savedPos > $valueStart) {
                        $this->emitToken(TokenType::AttributeValue, $valueStart, $savedPos);
                    }

                    // Scan directive
                    $this->returnState = State::AttrValueUnquoted;
                    $this->scanDirective();
                    $valueStart = $this->pos; // Resume after directive

                    continue;
                }

                // Not a directive, just a regular @
                $this->pos++;

                continue;
            }

            // Regular byte
            $this->pos++;
        }

        // Emit remaining unquoted value
        if ($valueStart < $this->pos) {
            $this->emitToken(TokenType::AttributeValue, $valueStart, $this->pos);
        }

        // Transition to BeforeAttrName to handle the next attribute or tag end
        $this->state = State::BeforeAttrName;
    }

    protected function handlePostAttributeName(): void
    {
        if ($this->pos >= $this->len) {
            // EOF. Unclosed tag
            $this->state = State::Data;

            return;
        }

        $char = $this->source[$this->pos];

        if ($char === '=') {
            // Attribute has a value
            $this->emitToken(TokenType::Equals, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::BeforeAttrValue;
        } elseif ($this->inXmlDeclaration && $char === '?' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '>') {
            // Boolean attribute, end of XML declaration
            $this->emitToken(TokenType::DeclEnd, $this->pos, $this->pos + 2);
            $this->pos += 2;
            $this->inXmlDeclaration = false;
            $this->state = State::Data;
        } elseif ($char === '>') {
            // Boolean attribute, end of tag
            $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::Data;
        } elseif ($char === '/') {
            // Boolean attribute, self-closing tag
            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
            $this->pos++;

            // Expect >
            if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
                $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                $this->pos++;
                $this->state = State::Data;
            } else {
                // Malformed, but continue
                $this->state = State::BeforeAttrName;
            }
        } elseif (ctype_space((string) $char)) {
            // Whitespace after attribute name
            $this->state = State::AfterAttrName;
        } elseif ($char === '<') {
            // New tag starting without closing the current tag
            $this->state = State::BeforeAttrName;
        } else {
            // Unexpected character. Emit as text so we can recover it later
            $this->emitToken(TokenType::Text, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::BeforeAttrName;
        }
    }
}
