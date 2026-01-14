<?php

declare(strict_types=1);

namespace Forte\Parser\Errors;

enum ParseErrorKind: int
{
    case UnclosedElement = 1;

    case UnclosedDirective = 2;

    case MismatchedClosingTag = 3;

    case UnexpectedClosingTag = 4;

    case InvalidNesting = 5;

    case MissingContent = 6;

    case InvalidAttribute = 7;

    case SyntaxError = 8;

    public function label(): string
    {
        return match ($this) {
            self::UnclosedElement => 'Unclosed element',
            self::UnclosedDirective => 'Unclosed directive',
            self::MismatchedClosingTag => 'Mismatched closing tag',
            self::UnexpectedClosingTag => 'Unexpected closing tag',
            self::InvalidNesting => 'Invalid nesting',
            self::MissingContent => 'Missing content',
            self::InvalidAttribute => 'Invalid attribute',
            self::SyntaxError => 'Syntax error',
        };
    }
}
