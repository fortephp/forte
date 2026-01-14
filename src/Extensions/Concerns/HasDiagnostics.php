<?php

declare(strict_types=1);

namespace Forte\Extensions\Concerns;

use Forte\Diagnostics\Diagnostic;
use Forte\Diagnostics\DiagnosticSeverity;

trait HasDiagnostics
{
    /**
     * @var Diagnostic[]
     */
    protected array $diagnostics = [];

    /**
     * Report a diagnostic.
     */
    protected function report(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /**
     * Report a warning at a position.
     */
    protected function warn(string $message, int $start, int $end): void
    {
        $this->report(new Diagnostic(
            DiagnosticSeverity::Warning,
            $message,
            $start,
            $end,
            $this->id()
        ));
    }

    /**
     * Report an error at a position.
     */
    protected function error(string $message, int $start, int $end): void
    {
        $this->report(new Diagnostic(
            DiagnosticSeverity::Error,
            $message,
            $start,
            $end,
            $this->id()
        ));
    }

    /**
     * Report an info diagnostic at a position.
     */
    protected function info(string $message, int $start, int $end): void
    {
        $this->report(new Diagnostic(
            DiagnosticSeverity::Info,
            $message,
            $start,
            $end,
            $this->id()
        ));
    }

    /**
     * Get all collected diagnostics.
     *
     * @return Diagnostic[]
     */
    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Clear collected diagnostics.
     */
    public function clearDiagnostics(): void
    {
        $this->diagnostics = [];
    }

    /**
     * Get the extension ID.
     */
    abstract public function id(): string;
}
