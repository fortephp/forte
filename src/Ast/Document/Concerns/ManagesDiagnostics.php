<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Diagnostics\Diagnostic;
use Forte\Diagnostics\DiagnosticBag;
use Forte\Parser\Errors\ParseError;

trait ManagesDiagnostics
{
    /**
     * Get diagnostics from all sources.
     */
    public function diagnostics(): DiagnosticBag
    {
        $bag = new DiagnosticBag;

        foreach ($this->lexerErrors as $error) {
            $bag->add(Diagnostic::fromLexerError($error));
        }

        foreach ($this->parseErrors as $error) {
            $bag->add(Diagnostic::fromParseError($error));
        }

        return $bag;
    }

    /**
     * Check if any errors were recorded for this document.
     */
    public function hasErrors(): bool
    {
        return $this->diagnostics()->hasErrors();
    }

    /**
     * Add a parse error to the document's error collection.
     *
     * @internal
     */
    public function addParseError(ParseError $error): void
    {
        $this->parseErrors[] = $error;
    }
}
