<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

enum StructureRole
{
    case Opening;
    case Closing;
    case Intermediate;
    case Mixed;
    case None;
}
