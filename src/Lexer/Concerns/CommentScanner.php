<?php

declare(strict_types=1);

namespace Forte\Lexer\Concerns;

use Forte\Lexer\LexerError;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;

trait CommentScanner
{
    protected function scanComment(): void
    {
        $start = $this->pos;

        // Emit comment start token: <!--
        $this->emitToken(TokenType::CommentStart, $start, $start + 4);
        $this->pos += 4; // Skip <!--

        $contentStart = $this->pos;

        // Scan for closing -->
        while ($this->pos < $this->len) {
            $closePos = strpos($this->source, '-->', $this->pos);

            if ($closePos === false) {
                // EOF. Unclosed comment.
                if ($contentStart < $this->len) {
                    $this->emitToken(TokenType::Text, $contentStart, $this->len);
                }
                $this->logError(LexerError::unexpectedEof(
                    State::Comment,
                    $this->len
                ));
                $this->pos = $this->len;
                $this->state = State::Data;

                return;
            }

            // Found closing -->
            if ($contentStart < $closePos) {
                $this->emitToken(TokenType::Text, $contentStart, $closePos);
            }

            // Emit comment end token: -->
            $this->emitToken(TokenType::CommentEnd, $closePos, $closePos + 3);
            $this->pos = $closePos + 3;
            $this->state = State::Data;

            return;
        }

        // EOF without closing. Emit remaining content and error
        if ($contentStart < $this->len) {
            $this->emitToken(TokenType::Text, $contentStart, $this->len);
        }
        $this->pos = $this->len;
        $this->logError(LexerError::unexpectedEof(
            State::Comment,
            $this->len
        ));
        $this->state = State::Data;
    }

    protected function tryScanBogusComment(): bool
    {
        if ($this->pos + 1 >= $this->len) {
            return false;
        }

        $start = $this->pos;

        if ($this->source[$this->pos] === '<' && $this->source[$this->pos + 1] === '?') {
            // Check if this is a PHP tag
            $tagLen = $this->phpTagStartLength($this->pos);
            if ($tagLen > 0) {
                return false; // This is a PHP tag, not a bogus comment
            }

            $this->pos += 2; // Skip the opening sequence

            // Bogus comments end at the first > character
            while ($this->pos < $this->len) {
                if ($this->source[$this->pos] === '>') {
                    $this->pos++;
                    $this->emitToken(TokenType::BogusComment, $start, $this->pos);

                    return true;
                }
                $this->pos++;
            }

            // EOF without closing. Restore position and don't treat as bogus comment
            $this->pos = $start;

            return false;
        }

        // Check for <- ... ->
        if ($this->source[$this->pos] === '<' && $this->source[$this->pos + 1] === '-') {
            // Make sure this is not <!--
            if ($this->pos + 2 < $this->len && $this->source[$this->pos + 2] === '-') {
                return false; // This is <!--, not a bogus comment
            }

            $this->pos += 2; // Skip <-

            // Scan until ->
            while ($this->pos + 1 < $this->len) {
                if ($this->source[$this->pos] === '-' && $this->source[$this->pos + 1] === '>') {
                    $this->pos += 2;
                    $this->emitToken(TokenType::BogusComment, $start, $this->pos);

                    return true;
                }
                $this->pos++;
            }

            // EOF without closing. Restore position and don't treat as bogus comment
            $this->pos = $start;

            return false;
        }

        return false;
    }

    protected function tryScanConditionalComment(): bool
    {
        // Must start with <!--[
        if ($this->pos + 5 >= $this->len) {
            return false;
        }

        if (substr($this->source, $this->pos, 5) !== '<!--[') {
            return false;
        }

        $start = $this->pos;
        $this->pos += 5; // Skip <!--[

        // Scan until we find "]>", which ends the conditional comment start
        while ($this->pos + 1 < $this->len) {
            if ($this->source[$this->pos] === ']' && $this->source[$this->pos + 1] === '>') {
                $this->pos += 2;

                // Check for downlevel-hidden variant: ]><!-->
                // The <!--> suffix makes content visible to non-IE browsers
                if ($this->pos + 4 <= $this->len && substr($this->source, $this->pos, 4) === '<!--') {
                    // Check if the next char is > (making it <!-->)
                    if ($this->pos + 4 < $this->len && $this->source[$this->pos + 4] === '>') {
                        $this->pos += 5; // Include <!--> in the token
                    }
                }

                $this->emitToken(TokenType::ConditionalCommentStart, $start, $this->pos);

                // After this, the lexer will continue tokenizing normally
                // until it encounters <![endif]--> or <!--<![endif]-->
                return true;
            }
            $this->pos++;
        }

        // EOF without closing "]>"
        $this->pos = $this->len;
        $this->emitToken(TokenType::ConditionalCommentStart, $start, $this->pos);

        return true;
    }

    /**
     * Try to scan a conditional comment ending: <![endif]-->
     */
    protected function tryScanConditionalCommentEnd(): bool
    {
        // Must start with <![
        if ($this->pos + 3 > $this->len) {
            return false;
        }

        if (substr($this->source, $this->pos, 3) !== '<![') {
            return false;
        }

        $start = $this->pos;
        $this->pos += 3; // Skip <![

        // Look for endif or another closing syntax
        // Scan until we find "]-->"
        return $this->scanConditionalClosing($start);
    }

    /**
     * Scan downlevel-hidden conditional comment end: <!--<![endif]-->
     *
     * This variant includes a <!-- prefix before the <![endif] marker.
     * The entire <!--<![endif]--> is emitted as a single ConditionalCommentEnd token.
     */
    protected function tryScanDownlevelHiddenConditionalCommentEnd(): bool
    {
        // Must start with <!--<![
        if ($this->pos + 7 > $this->len) {
            return false;
        }

        if (substr($this->source, $this->pos, 7) !== '<!--<![') {
            return false;
        }

        $start = $this->pos;
        $this->pos += 7; // Skip <!--<![

        return $this->scanConditionalClosing($start);
    }

    /**
     * Try to scan a CDATA section: <![CDATA[...]]>
     *
     * Returns true if a CDATA section was scanned
     */
    protected function tryScanCdata(): bool
    {
        // Must start with <![CDATA[
        if ($this->pos + 9 > $this->len) {
            return false;
        }

        if (substr($this->source, $this->pos, 9) !== '<![CDATA[') {
            return false;
        }

        $start = $this->pos;

        // Emit CDATA start token: <![CDATA[
        $this->emitToken(TokenType::CdataStart, $start, $start + 9);
        $this->pos += 9; // Skip <![CDATA[

        $contentStart = $this->pos;

        // Scan for closing "]]>"
        while ($this->pos < $this->len) {
            $closePos = strpos($this->source, ']]>', $this->pos);

            if ($closePos === false) {
                // EOF. Unclosed CDATA
                if ($contentStart < $this->len) {
                    $this->emitToken(TokenType::Text, $contentStart, $this->len);
                }
                $this->logError(LexerError::unexpectedEof(
                    State::Data,
                    $this->len
                ));
                $this->pos = $this->len;

                return true;
            }

            // Found closing ]]>
            if ($contentStart < $closePos) {
                $this->emitToken(TokenType::Text, $contentStart, $closePos);
            }

            // Emit CDATA end token: ]]>
            $this->emitToken(TokenType::CdataEnd, $closePos, $closePos + 3);
            $this->pos = $closePos + 3;

            return true;
        }

        // EOF without closing. Emit remaining content and error
        if ($contentStart < $this->len) {
            $this->emitToken(TokenType::Text, $contentStart, $this->len);
        }
        $this->pos = $this->len;
        $this->logError(LexerError::unexpectedEof(
            State::Data,
            $this->len
        ));

        return true;
    }

    protected function tryScanProcessingInstruction(): bool
    {
        /* Must start with <? */
        if ($this->pos + 2 > $this->len) {
            return false;
        }

        if ($this->source[$this->pos] !== '<' || $this->source[$this->pos + 1] !== '?') {
            return false;
        }

        // Check if followed by a valid PI target
        if ($this->pos + 2 >= $this->len) {
            return false;
        }

        $thirdChar = $this->source[$this->pos + 2];

        // If space immediately after opening, it's a bogus comment, not PI
        if (ctype_space((string) $thirdChar)) {
            return false;
        }

        if (! ctype_alpha((string) $thirdChar)) {
            return false;
        }

        // Check if this is a PHP tag
        if ($this->phpTagStartLength($this->pos) > 0) {
            return false;
        }

        // Scan the target name
        $targetStart = $this->pos + 2;
        $targetEnd = $targetStart;
        while ($targetEnd < $this->len) {
            $c = $this->source[$targetEnd];
            if (ctype_alnum((string) $c) || $c === '-' || $c === '_' || $c === ':') {
                $targetEnd++;
            } else {
                break;
            }
        }

        $targetName = substr($this->source, $targetStart, $targetEnd - $targetStart);

        if (strcasecmp($targetName, 'xml') === 0) {
            return false;
        }

        $start = $this->pos;

        $piStartEnd = $targetEnd;

        // Emit PI start token
        $this->emitToken(TokenType::PIStart, $start, $piStartEnd);
        $this->pos = $piStartEnd;

        $contentStart = $this->pos;

        // Scan for closing sequence
        while ($this->pos + 1 < $this->len) {
            if ($this->source[$this->pos] === '?' && $this->source[$this->pos + 1] === '>') {
                // Found closing sequence
                if ($contentStart < $this->pos) {
                    $this->emitToken(TokenType::Text, $contentStart, $this->pos);
                }

                // Emit PI end token
                $this->emitToken(TokenType::PIEnd, $this->pos, $this->pos + 2);
                $this->pos += 2;

                return true;
            }
            $this->pos++;
        }

        // EOF without closing. Emit remaining content and error
        if ($contentStart < $this->len) {
            $this->emitToken(TokenType::Text, $contentStart, $this->len);
        }
        $this->pos = $this->len;
        $this->logError(LexerError::unexpectedEof(
            State::Data,
            $this->len
        ));

        return true;
    }

    protected function tryScanDecl(): bool
    {
        if ($this->pos + 5 > $this->len) {
            return false;
        }

        // Check for XML declaration (case-insensitive)
        if ($this->source[$this->pos] !== '<' || $this->source[$this->pos + 1] !== '?') {
            return false;
        }

        $xmlMatch = (strtolower((string) $this->source[$this->pos + 2]) === 'x')
            && (strtolower((string) $this->source[$this->pos + 3]) === 'm')
            && (strtolower((string) $this->source[$this->pos + 4]) === 'l');

        if (! $xmlMatch) {
            return false;
        }

        if ($this->pos + 5 < $this->len) {
            $next = $this->source[$this->pos + 5];
            if (ctype_alnum((string) $next) || $next === '_' || $next === '-' || $next === ':') {
                return false;
            }
        }

        $start = $this->pos;

        $this->emitToken(TokenType::DeclStart, $start, $start + 5);
        $this->pos += 5; // Skip <?xml

        // Set XML declaration mode and transition to attribute scanning
        $this->inXmlDeclaration = true;
        $this->state = State::BeforeAttrName;

        return true;
    }

    /**
     * @return true
     */
    protected function scanConditionalClosing(int $start): bool
    {
        while ($this->pos + 3 < $this->len) {
            if ($this->source[$this->pos] === ']'
                && $this->source[$this->pos + 1] === '-'
                && $this->source[$this->pos + 2] === '-'
                && $this->source[$this->pos + 3] === '>') {
                $this->pos += 4;
                $this->emitToken(TokenType::ConditionalCommentEnd, $start, $this->pos);

                return true;
            }
            $this->pos++;
        }

        // EOF without closing "]-->"
        $this->pos = $this->len;
        $this->emitToken(TokenType::ConditionalCommentEnd, $start, $this->pos);

        return true;
    }
}
