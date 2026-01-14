<?php

declare(strict_types=1);

namespace Forte\Parser;

use Forte\Support\StringUtilities;
use Forte\Support\TokenSplitter;

class ArgsParser extends TokenSplitter
{
    private static ?ArgsParser $instance = null;

    public static function instance(): ArgsParser
    {
        if (! self::$instance) {
            self::$instance = new ArgsParser;
        }

        return self::$instance;
    }

    /**
     * @return array<int, string>
     */
    public static function parseArgs(string $args): array
    {
        return self::instance()->parse($args);
    }

    /**
     * @return array<int, string>
     */
    public function parse(string $arguments): array
    {
        $arguments = StringUtilities::unwrapParentheses($arguments);

        return $this->splitIntoParts($arguments);
    }

    protected function isSplitCharacter(?string $char): bool
    {
        return $char === ',';
    }

    protected function advancePastSplitCharacter(): void
    {
        $this->advanceWhile(' \t\n\r,');
    }

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    protected function processFinalResult(array $parts): array
    {
        return array_values(array_map(trim(...), array_filter($parts, fn ($part) => trim((string) $part) !== '')));
    }
}
