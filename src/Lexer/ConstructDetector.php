<?php

declare(strict_types=1);

namespace Forte\Lexer;

trait ConstructDetector
{
    public const CONSTRUCT_NONE = 0;

    public const CONSTRUCT_ECHO = 1 << 0;

    public const CONSTRUCT_RAW_ECHO = 1 << 1;

    public const CONSTRUCT_TRIPLE_ECHO = 1 << 2;

    public const CONSTRUCT_BLADE_COMMENT = 1 << 3;

    public const CONSTRUCT_DIRECTIVE = 1 << 4;

    public const CONSTRUCT_PHP_TAG = 1 << 5;

    public const CONSTRUCT_ALL_ECHOS = self::CONSTRUCT_ECHO | self::CONSTRUCT_RAW_ECHO | self::CONSTRUCT_TRIPLE_ECHO;

    public const CONSTRUCT_ALL = self::CONSTRUCT_ECHO | self::CONSTRUCT_RAW_ECHO | self::CONSTRUCT_TRIPLE_ECHO
        | self::CONSTRUCT_BLADE_COMMENT | self::CONSTRUCT_DIRECTIVE | self::CONSTRUCT_PHP_TAG;

    /**
     * Check if @ can start a directive at the current position.
     */
    private function canStartDirective(): bool
    {
        // Check if @ is followed by alphanumeric or underscore
        $next = $this->peekAhead(1);

        if ($next === null) {
            return false;
        }

        return ctype_alnum($next) || $next === '_';
    }

    /**
     * Check if we're at the start of any construct specified in flags.
     *
     * @param  int  $flags  Bitmask of constructs to detect
     */
    protected function detectConstruct(int $flags): ?Construct
    {
        if (($flags & self::CONSTRUCT_TRIPLE_ECHO) !== 0
            && $this->peek() === '{'
            && $this->peekAhead(1) === '{'
            && $this->peekAhead(2) === '{'
        ) {
            return Construct::TripleEcho;
        }

        if (($flags & self::CONSTRUCT_BLADE_COMMENT) !== 0
            && $this->peek() === '{'
            && $this->peekAhead(1) === '{'
            && $this->peekAhead(2) === '-'
            && $this->peekAhead(3) === '-'
        ) {
            return Construct::BladeComment;
        }

        if (($flags & self::CONSTRUCT_RAW_ECHO) !== 0
            && $this->peek() === '{'
            && $this->peekAhead(1) === '!'
            && $this->peekAhead(2) === '!'
        ) {
            return Construct::RawEcho;
        }

        if (($flags & self::CONSTRUCT_ECHO) !== 0
            && $this->peek() === '{'
            && $this->peekAhead(1) === '{'
        ) {
            return Construct::Echo;
        }

        if (($flags & self::CONSTRUCT_DIRECTIVE) !== 0
            && $this->peek() === '@'
            && $this->canStartDirective()
        ) {
            return Construct::Directive;
        }

        if (($flags & self::CONSTRUCT_PHP_TAG) !== 0
            && $this->peek() === '<'
            && $this->peekAhead(1) === '?'
        ) {
            return Construct::PhpTag;
        }

        return null;
    }
}
