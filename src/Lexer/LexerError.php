<?php

declare(strict_types=1);

namespace Forte\Lexer;

use Exception;

class LexerError extends Exception
{
    public function __construct(
        public readonly State $state,
        public readonly ErrorReason $reason,
        public readonly int $offset,
    ) {
        $message = sprintf(
            'Lexer error at offset %d: %s while in state %s',
            $this->offset,
            $this->reason->label(),
            $this->state->label()
        );

        parent::__construct($message);
    }

    /**
     * Create error for unexpected nested echo
     */
    public static function unexpectedNestedEcho(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnexpectedNestedEcho, $offset);
    }

    /**
     * Create error for unexpected nested raw echo
     */
    public static function unexpectedNestedRawEcho(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnexpectedNestedRawEcho, $offset);
    }

    /**
     * Create error for unexpected nested triple echo
     */
    public static function unexpectedNestedTripleEcho(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnexpectedNestedTripleEcho, $offset);
    }

    /**
     * Create error for unexpected EOF
     */
    public static function unexpectedEof(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnexpectedEof, $offset);
    }

    /**
     * Create error for unclosed string
     */
    public static function unclosedString(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnclosedString, $offset);
    }

    /**
     * Create error for unclosed comment
     */
    public static function unclosedComment(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnclosedComment, $offset);
    }

    /**
     * Create error for unimplemented state
     */
    public static function unimplementedState(State $state, int $offset): self
    {
        return new self($state, ErrorReason::UnimplementedState, $offset);
    }

    /**
     * Create a notice for PHP close tag ?> found inside a line comment
     */
    public static function phpCloseTagInComment(State $state, int $offset): self
    {
        return new self($state, ErrorReason::PhpCloseTagInComment, $offset);
    }
}
