<?php

declare(strict_types=1);

namespace Forte\Diagnostics;

use Forte\Lexer\LexerError;
use Forte\Parser\Errors\ParseError;

readonly class Diagnostic implements \Stringable
{
    public function __construct(
        public DiagnosticSeverity $severity,
        public string $message,
        public int $start,
        public int $end,
        public string $source,
        public ?string $code = null
    ) {}

    /**
     * Check if this is an error.
     */
    public function isError(): bool
    {
        return $this->severity === DiagnosticSeverity::Error;
    }

    /**
     * Check if this is a warning.
     */
    public function isWarning(): bool
    {
        return $this->severity === DiagnosticSeverity::Warning;
    }

    /**
     * Check if this is informational.
     */
    public function isInfo(): bool
    {
        return $this->severity === DiagnosticSeverity::Info;
    }

    /**
     * Check if this is a hint.
     */
    public function isHint(): bool
    {
        return $this->severity === DiagnosticSeverity::Hint;
    }

    /**
     * Get the length of the diagnostic range.
     */
    public function length(): int
    {
        return $this->end - $this->start;
    }

    /**
     * Create an error diagnostic.
     */
    public static function error(string $message, int $start, int $end, string $source, ?string $code = null): self
    {
        return new self(DiagnosticSeverity::Error, $message, $start, $end, $source, $code);
    }

    /**
     * Create a warning diagnostic.
     */
    public static function warning(string $message, int $start, int $end, string $source, ?string $code = null): self
    {
        return new self(DiagnosticSeverity::Warning, $message, $start, $end, $source, $code);
    }

    /**
     * Create an info diagnostic.
     */
    public static function info(string $message, int $start, int $end, string $source, ?string $code = null): self
    {
        return new self(DiagnosticSeverity::Info, $message, $start, $end, $source, $code);
    }

    /**
     * Create a hint diagnostic.
     */
    public static function hint(string $message, int $start, int $end, string $source, ?string $code = null): self
    {
        return new self(DiagnosticSeverity::Hint, $message, $start, $end, $source, $code);
    }

    /**
     * Create a diagnostic from a lexer error.
     */
    public static function fromLexerError(LexerError $error): self
    {
        return new self(
            DiagnosticSeverity::Error,
            $error->getMessage(),
            $error->offset,
            $error->offset + 1,
            'lexer',
            $error->reason->name
        );
    }

    /**
     * Create a diagnostic from a parse error.
     */
    public static function fromParseError(ParseError $error): self
    {
        return new self(
            DiagnosticSeverity::Error,
            $error->message,
            $error->offset,
            $error->end(),
            'parser',
            $error->kind->name
        );
    }

    /**
     * Format the diagnostic as a string.
     */
    public function __toString(): string
    {
        $severityLabel = match ($this->severity) {
            DiagnosticSeverity::Error => 'error',
            DiagnosticSeverity::Warning => 'warning',
            DiagnosticSeverity::Info => 'info',
            DiagnosticSeverity::Hint => 'hint',
        };

        $code = $this->code !== null ? "[{$this->code}] " : '';

        return "{$severityLabel}({$this->start}-{$this->end}): {$code}{$this->message} [{$this->source}]";
    }
}
