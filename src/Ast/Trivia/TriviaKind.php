<?php

declare(strict_types=1);

namespace Forte\Ast\Trivia;

enum TriviaKind
{
    case LeadingWhitespace;
    case Word;
    case InnerWhitespace;
    case TrailingWhitespace;
}
