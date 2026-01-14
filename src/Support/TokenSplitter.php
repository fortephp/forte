<?php

declare(strict_types=1);

namespace Forte\Support;

abstract class TokenSplitter extends TextScanner
{
    /**
     * @var array<int, string>
     */
    protected array $parts = [];

    protected int $currentPartIndex = 0;

    /**
     * Check if a character should trigger a token split.
     */
    abstract protected function isSplitCharacter(?string $char): bool;

    abstract protected function advancePastSplitCharacter(): void;

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    abstract protected function processFinalResult(array $parts): array;

    protected function resetState(): void
    {
        $this->parts = [];
        $this->currentPartIndex = 0;
    }

    protected function appendToCurrentPart(string $content): void
    {
        if (! isset($this->parts[$this->currentPartIndex])) {
            $this->parts[$this->currentPartIndex] = '';
        }

        $this->parts[$this->currentPartIndex] .= $content;
    }

    protected function moveToNextPart(): void
    {
        if (isset($this->parts[$this->currentPartIndex]) && $this->parts[$this->currentPartIndex] !== '') {
            $this->currentPartIndex++;
        }
    }

    protected function getCurrentPartAsString(): string
    {
        $result = $this->parts[$this->currentPartIndex] ?? '';
        unset($this->parts[$this->currentPartIndex]);
        $this->currentPartIndex++;

        return $result;
    }

    /**
     * @return array<int, string>
     */
    protected function splitIntoParts(string $value): array
    {
        $this->resetState();
        $this->initializeScanner($value);

        while (! $this->isAtEnd()) {
            if ($this->isStartingHeredoc()) {
                $this->appendToCurrentPart($this->advanceHereNowDoc());

                continue;
            }

            if ($this->isStartingString() && $this->curChar !== null) {
                $this->appendToCurrentPart($this->advanceString($this->curChar));

                continue;
            }

            if ($this->curChar === '[') {
                $this->appendToCurrentPart($this->advancePairedStructure('[', ']'));

                continue;
            }

            if ($this->curChar === '(') {
                $this->appendToCurrentPart($this->advancePairedStructure('(', ')'));

                continue;
            }

            if ($this->curChar === '{') {
                $this->appendToCurrentPart($this->advancePairedStructure('{', '}'));

                continue;
            }

            if ($this->isSplitCharacter($this->curChar)) {
                $this->moveToNextPart();
                $this->advancePastSplitCharacter();

                continue;
            }

            $this->appendToCurrentPart($this->advance());
        }

        return $this->processFinalResult($this->parts);
    }
}
