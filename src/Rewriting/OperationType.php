<?php

declare(strict_types=1);

namespace Forte\Rewriting;

enum OperationType
{
    case Keep;
    case Remove;
    case Replace;
    case Wrap;
    case Unwrap;
    case ReplaceChildren;
}
