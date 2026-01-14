<?php

declare(strict_types=1);

namespace Forte\Lexer;

enum State: int
{
    case Data = 0;
    case TagOpen = 1;
    case TagName = 2;
    case BeforeAttrName = 3;
    case AttrName = 4;
    case AfterAttrName = 5;
    case BeforeAttrValue = 6;
    case AttrValueQuoted = 7;
    case AttrValueUnquoted = 8;
    case EchoContent = 9;
    case RawEchoContent = 10;
    case TripleEchoContent = 11;
    case RawText = 12;
    case Comment = 13;
    case BladeComment = 14;
    case Verbatim = 15;
    case PhpBlock = 16;
    case PhpTag = 17;

    public function label(): string
    {
        return $this->name;
    }
}
