<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;

trait HtmlScanner
{
    private const RAWTEXT_ELEMENTS = ['script', 'style'];

    private function checkRawtextMode(): State
    {
        // Only enter rawtext for opening tags, not closing tags or self-closing
        if ($this->isClosingTag) {
            return State::Data;
        }

        $tagNameLower = strtolower($this->currentTagName);
        if (in_array($tagNameLower, self::RAWTEXT_ELEMENTS, true)) {
            $this->rawtext = true;
            $this->rawtextTagName = $tagNameLower;

            return State::RawText;
        }

        return State::Data;
    }

    protected function scanTagOpen(): void
    {
        $start = $this->pos;

        // Reset tag tracking for new tag
        $this->currentTagName = '';

        $this->emitToken(TokenType::LessThan, $start, $start + 1);
        $this->pos++; // Skip <

        // Check for closing tag (</...)
        if ($this->pos < $this->len && $this->source[$this->pos] === '/') {
            // Emit / token for closing tag
            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->isClosingTag = true;
        } else {
            $this->isClosingTag = false;
        }

        // Now scan the tag name
        $this->state = State::TagName;
    }

    protected function scanTagName(): void
    {
        $start = $this->pos;

        // If we're continuing a composite tag name (returned from Blade)
        // and we see whitespace, that means the tag name is complete.
        // We need to emit whitespace and transition to BeforeAttrName
        if ($this->continuedTagName && $this->pos < $this->len && ctype_space((string) $this->source[$this->pos])) {
            $this->continuedTagName = false;
            $this->state = State::BeforeAttrName;

            return;
        }

        // Skip whitespace before tag name (for malformed HTML like "< div>")
        // Only do this at the initial entry, not when continuing a composite tag name
        if (! $this->continuedTagName) {
            $this->skipWhitespace();
        }

        // Reset the flag now that we've handled the continuation case
        $this->continuedTagName = false;

        // If we hit > or / immediately, it's an empty/malformed tag like "<>" or "</>"
        if ($this->pos >= $this->len || $this->source[$this->pos] === '>' || $this->source[$this->pos] === '/') {
            if ($start < $this->pos) {
                $this->emitToken(TokenType::Whitespace, $start, $this->pos);
            }

            // Handle > or />
            if ($this->pos < $this->len) {
                if ($this->source[$this->pos] === '>') {
                    $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                    $this->pos++;
                    $this->state = $this->checkRawtextMode();
                } elseif ($this->source[$this->pos] === '/') {
                    // Self-closing tag (never enters rawtext mode)
                    $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
                    $this->pos++;

                    // Now expect >
                    if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
                        $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                        $this->pos++;
                        $this->state = State::Data;  // Self-closing never enters rawtext
                    } else {
                        // Malformed, but continue
                        $this->state = State::BeforeAttrName;
                    }
                }
            } else {
                // EOF. Unclosed tag, emit SyntheticClose for error recovery
                $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
                $this->state = State::Data;
            }

            return;
        }

        $nameStart = $this->pos;

        while ($this->pos < $this->len) {
            $ch = $this->source[$this->pos];

            // Check for Blade echo in the tag name (e.g., <{{ $element }}>)
            if ($ch === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
                // Emit tag name content before echo if any
                if ($this->pos > $nameStart) {
                    $this->emitToken(TokenType::TagName, $nameStart, $this->pos);
                }

                // Try to scan Blade echo
                $savedPos = $this->pos;
                if ($this->peekAhead(1) === '{') {
                    // Check for {{--
                    if ($this->peekAhead(2) === '-' && $this->peekAhead(3) === '-') {
                        $this->returnState = State::TagName;
                        $this->continuedTagName = true;
                        $this->scanBladeCommentStart();
                        $this->scanBladeCommentContent();

                        return;
                    }

                    // Scan {{ or {{{ echo
                    $this->returnState = State::TagName;
                    $this->continuedTagName = true;
                    $this->scanEcho();

                    return;
                } elseif ($this->peekAhead(1) === '!' && $this->peekAhead(2) === '!') {
                    // Scan {!! raw echo
                    $this->returnState = State::TagName;
                    $this->continuedTagName = true;
                    $this->scanRawEcho();

                    return;
                }

                // Not a Blade echo. Restore and break (end of tag name)
                $this->pos = $savedPos;
                break;
            }

            // Tag name characters (special characters included to support Blade components, directives, etc.)
            // Includes [] and $ for slot syntax: <x-slot[name]>, <x-slot:items[]>, <x-slot[$variable]>
            if (ctype_alnum((string) $ch) || $ch === '-' || $ch === ':' || $ch === '_' || $ch === '@' || $ch === '.' || $ch === '[' || $ch === ']' || $ch === '$') {
                $this->pos++;
            } else {
                break;
            }
        }

        // Emit tag name and accumulate for rawtext check
        if ($nameStart < $this->pos) {
            $tagNamePart = substr($this->source, $nameStart, $this->pos - $nameStart);
            $this->currentTagName .= $tagNamePart;
            $this->emitToken(TokenType::TagName, $nameStart, $this->pos);
        }

        // Check what comes after the tag name
        if ($this->pos >= $this->len) {
            // EOF. Unclosed tag, emit SyntheticClose.
            $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
            $this->state = State::Data;

            return;
        }

        $ch = $this->source[$this->pos];

        if ($ch === '<') {
            // Check for PHP tag first
            if ($this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '?') {
                if ($this->tryScanPhpTag()) {
                    // PHP tag scanned, stay in TagName state to continue collecting tag name parts
                    $this->state = State::TagName;
                    $this->continuedTagName = true;

                    return;
                }
            }

            // Not a PHP tag, let's try TSX generic type parameter
            if ($this->tryScanTsxGenericType()) {
                // Successfully scanned TSX generic. Go to BeforeAttrName
                $this->state = State::BeforeAttrName;

                return;
            }

            // Not a TSX generic. This is a new tag starting before the current
            // tag is closed; this is a malformed/incomplete tag. Yay! edge cases.
            $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
            // Properly scan the new tag
            $this->scanTagOpen();
        } elseif ($ch === '>') {
            // End of tag
            $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = $this->checkRawtextMode();
        } elseif ($ch === '/') {
            // Self-closing tag: <br/>
            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
            $this->pos++;

            // Skip whitespace before >
            $this->skipWhitespace();

            // Expect >
            if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
                $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                $this->pos++;
                $this->state = State::Data;  // Self-closing never enters rawtext
            } else {
                // Malformed self-closing tag, but continue
                $this->state = State::BeforeAttrName;
            }
        } elseif (ctype_space((string) $ch)) {
            // Whitespace after tag name.
            $this->state = State::BeforeAttrName;
        } else {
            // Unexpected character
            $this->state = State::BeforeAttrName;
        }
    }

    protected function scanBeforeAttrName(): void
    {
        $start = $this->pos;

        // Skip whitespace
        while ($this->pos < $this->len && ctype_space((string) $this->source[$this->pos])) {
            $this->pos++;
        }

        // For closing tags, check if the tag is malformed before emitting whitespace
        if ($this->isClosingTag) {
            if ($this->pos >= $this->len) {
                // EOF. Emit SyntheticClose at the whitespace start so whitespace becomes text
                $this->emitToken(TokenType::SyntheticClose, $start, $start);
                $this->pos = $start;  // Reset so whitespace is scanned as data
                $this->state = State::Data;

                return;
            }

            $ch = $this->source[$this->pos];
            if ($ch !== '>' && $ch !== '/') {
                // Malformed closing tag. Emit SyntheticClose at whitespace start
                $this->emitToken(TokenType::SyntheticClose, $start, $start);
                $this->pos = $start;  // Reset so whitespace is scanned as data
                $this->state = State::Data;

                return;
            }
        }

        // Emit whitespace token if we skipped any
        if ($start < $this->pos) {
            $this->emitToken(TokenType::Whitespace, $start, $this->pos);
        }

        // Check what comes after whitespace
        if ($this->pos >= $this->len) {
            // EOF. Unclosed tag, emit SyntheticClose
            $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
            $this->state = State::Data;

            return;
        }

        $ch = $this->source[$this->pos];

        // Check for Blade directive as attribute: @if($condition)
        if ($ch === '@' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
            // Look ahead to check if this is a known Blade directive
            $tempPos = $this->pos + 1; // Skip @
            $nameStart = $tempPos;

            // Scan potential directive name
            while ($tempPos < $this->len) {
                $byte = $this->source[$tempPos];
                if (ctype_alnum((string) $byte) || $byte === '_') {
                    $tempPos++;
                } else {
                    break;
                }
            }

            // Extract and check if it's a known directive
            if ($tempPos > $nameStart) {
                $name = substr($this->source, $nameStart, $tempPos - $nameStart);
                if ($this->directives()->isDirective($name)) {
                    $this->returnState = State::BeforeAttrName;
                    $this->scanDirective();

                    return;
                }
            }

            // Not a known directive, we'll treat it as the beginning of an attribute.
        }

        // Check for PHP tag first
        if ($ch === '<' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '?') {
            if ($this->tryScanPhpTag()) {
                // Successfully scanned PHP tag, stay in BeforeAttrName for the next attribute
                $this->state = State::BeforeAttrName;

                return;
            }
        }

        // Check for HTML comment (<!--) in attribute context
        if ($ch === '<' && $this->pos + 3 < $this->len
            && $this->source[$this->pos + 1] === '!'
            && $this->source[$this->pos + 2] === '-'
            && $this->source[$this->pos + 3] === '-') {
            // Emit SyntheticClose for the incomplete current tag
            $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
            // Bail. Data state will handle the comment
            $this->state = State::Data;

            return;
        }

        // Check for TSX generic type parameter: <User> or <{ id: number }>
        if ($ch === '<') {
            if ($this->tryScanTsxGenericType()) {
                // Successfully scanned generic, stay in BeforeAttrName for the next attribute
                return;
            }

            // We are starting an entirely new tag (malformed sequence). Let's handle that.
            $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
            // Emit the < and switch to TagName
            $this->emitToken(TokenType::LessThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = State::TagName;

            return;
        }

        // Check for JSX shorthand attribute: {enabled} or {...props}
        if ($ch === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
            if ($this->tryScanJsxShorthandAttribute()) {
                // Successfully scanned JSX shorthand
                return;
            }
        }

        // Check for Blade echo as an attribute or standalone: {{ $attributes }}
        if ($ch === '{' && ! $this->verbatim && ! $this->phpBlock && ! $this->phpTag) {
            $savedPos = $this->pos;

            // Check for Blade patterns: {{--, {{, {!!, {{{
            if ($this->peekAhead(1) === '{') {
                // Check for {{--
                if ($this->peekAhead(2) === '-' && $this->peekAhead(3) === '-') {
                    $this->returnState = State::BeforeAttrName;
                    $this->scanBladeCommentStart();
                    $this->scanBladeCommentContent();

                    return;
                }
                // Found {{ or {{{
                $this->returnState = State::BeforeAttrName;
                $this->scanEcho();
                // Check if echo is followed by attribute name continuation (not whitespace, >, /, =)
                if ($this->pos < $this->len) {
                    $nextCh = $this->source[$this->pos];
                    // If the next char could continue an attribute name, go to AttrName to continue scanning
                    if ($nextCh !== ' ' && $nextCh !== "\t" && $nextCh !== "\n" && $nextCh !== "\r" &&
                        $nextCh !== '>' && $nextCh !== '/' && $nextCh !== '=' && $nextCh !== '<') {
                        $this->state = State::AttrName;

                        return;
                    }
                }

                return;
            } elseif ($this->peekAhead(1) === '!' && $this->peekAhead(2) === '!') {
                // Found {!!
                $this->returnState = State::BeforeAttrName;
                $this->scanRawEcho();

                return;
            }

            // Not a Blade echo. Restore position and continue
            $this->pos = $savedPos;
        }

        // Check for XML declaration ending
        if ($this->inXmlDeclaration && $ch === '?' && $this->pos + 1 < $this->len && $this->source[$this->pos + 1] === '>') {
            // End of XML declaration
            $this->emitToken(TokenType::DeclEnd, $this->pos, $this->pos + 2);
            $this->pos += 2;
            $this->inXmlDeclaration = false;
            $this->state = State::Data;

            return;
        }

        if ($ch === '>') {
            // End of tag
            $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
            $this->pos++;
            $this->state = $this->checkRawtextMode();
        } elseif ($ch === '/') {
            // Possible self-closing tag: />
            $this->emitToken(TokenType::Slash, $this->pos, $this->pos + 1);
            $this->pos++;

            // Skip whitespace before >
            $wsStart = $this->pos;
            while ($this->pos < $this->len && ctype_space((string) $this->source[$this->pos])) {
                $this->pos++;
            }

            // Expect >

            if ($this->pos < $this->len && $this->source[$this->pos] === '>') {
                $this->emitToken(TokenType::GreaterThan, $this->pos, $this->pos + 1);
                $this->pos++;
                // Self-closing never enters rawtext
            } elseif ($this->pos >= $this->len) {
                // EOF after /
                $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
            } else {
                // Malformed self-closing tag (e.g., /\n< instead of />)
                // Emit SyntheticClose for error recovery, then reset position
                // to after the slash so whitespace becomes text content
                $this->emitToken(TokenType::SyntheticClose, $wsStart, $wsStart);
                $this->pos = $wsStart;
            }
            $this->state = State::Data;
        } else {
            $this->state = State::AttrName;
        }
    }
}
