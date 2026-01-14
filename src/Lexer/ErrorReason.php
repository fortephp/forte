<?php

declare(strict_types=1);

namespace Forte\Lexer;

enum ErrorReason: int
{
    case UnexpectedNestedEcho = 0;
    case UnexpectedNestedRawEcho = 1;
    case UnexpectedNestedTripleEcho = 2;
    case UnexpectedEof = 3;
    case UnclosedString = 4;
    case UnclosedComment = 5;
    case InvalidEscape = 6;
    case UnimplementedState = 7;
    case ConstructCollision = 8;
    case PhpCloseTagInComment = 9;

    public function label(): string
    {
        return match ($this) {
            self::UnexpectedNestedEcho => 'UnexpectedNestedEcho',
            self::UnexpectedNestedRawEcho => 'UnexpectedNestedRawEcho',
            self::UnexpectedNestedTripleEcho => 'UnexpectedNestedTripleEcho',
            self::UnexpectedEof => 'UnexpectedEof',
            self::UnclosedString => 'UnclosedString',
            self::UnclosedComment => 'UnclosedComment',
            self::InvalidEscape => 'InvalidEscape',
            self::UnimplementedState => 'UnimplementedState',
            self::ConstructCollision => 'ConstructCollision',
            self::PhpCloseTagInComment => 'PhpCloseTagInComment',
        };
    }
}
