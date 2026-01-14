<?php

declare(strict_types=1);

namespace Forte\Diagnostics;

enum DiagnosticSeverity: int
{
    case Error = 1;
    case Warning = 2;
    case Info = 3;
    case Hint = 4;

    public function isAtLeast(self $other): bool
    {
        return $this->value <= $other->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Error => 'error',
            self::Warning => 'warning',
            self::Info => 'info',
            self::Hint => 'hint',
        };
    }
}
