<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

enum ArgumentRequirement
{
    case Required;
    case NotAllowed;
    case Optional;
}
