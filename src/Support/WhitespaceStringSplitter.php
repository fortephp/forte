<?php

declare(strict_types=1);

namespace Forte\Support;

class WhitespaceStringSplitter extends TokenSplitter
{
    private static ?WhitespaceStringSplitter $instance = null;

    public static function instance(): WhitespaceStringSplitter
    {
        if (! self::$instance) {
            self::$instance = new WhitespaceStringSplitter;
        }

        return self::$instance;
    }

    /**
     * @return array<int, string>
     */
    public static function splitString(string $value): array
    {
        return self::instance()->split($value);
    }

    /**
     * @return array<int, string>
     */
    public function split(string $value): array
    {
        return $this->splitIntoParts($value);
    }

    protected function isSplitCharacter(?string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }

    protected function advancePastSplitCharacter(): void
    {
        $this->advance();
    }

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    protected function processFinalResult(array $parts): array
    {
        return array_values(array_filter($parts, fn ($part) => $part !== ''));
    }
}
