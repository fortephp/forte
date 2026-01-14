<?php

declare(strict_types=1);

namespace Forte\Lexer;

enum Construct: string
{
    case Echo = 'echo';
    case RawEcho = 'raw_echo';
    case TripleEcho = 'triple_echo';
    case BladeComment = 'blade_comment';
    case Directive = 'directive';
    case PhpTag = 'php_tag';

    public function name(): string
    {
        return match ($this) {
            self::Echo => 'echo',
            self::RawEcho => 'raw echo',
            self::TripleEcho => 'triple echo',
            self::BladeComment => 'blade comment',
            self::Directive => 'directive',
            self::PhpTag => 'PHP tag',
        };
    }
}
