<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

use Forte\Lexer\Tokens\TokenType;
use Forte\Support\StringInterner;

class DirectiveTokenIndex
{
    /**
     * @var array<string, int[]>
     */
    private array $byName = [];

    /**
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @param  string  $source  The source code for extracting directive names
     */
    public function __construct(array $tokens, string $source)
    {
        $this->build($tokens, $source);
    }

    /**
     * Build the index by scanning all tokens at once.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     */
    protected function build(array $tokens, string $source): void
    {
        foreach ($tokens as $idx => $token) {
            if (! $this->isDirectiveToken($token)) {
                continue;
            }

            $name = $this->extractName($token, $source);
            $this->byName[$name][] = $idx;
        }
    }

    /**
     * Extract directive name from token (lowercase, without @).
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    protected function extractName(array $token, string $source): string
    {
        $text = substr($source, $token['start'], $token['end'] - $token['start']);

        if (str_starts_with($text, '@')) {
            $text = substr($text, 1);
        }

        return self::normalize($text);
    }

    /**
     * Get all token indices for a directive name at or after a given index.
     *
     * @param  string  $name  Directive name (case-insensitive)
     * @param  int  $minIdx  Minimum token index (inclusive)
     * @return int[] Sorted array of token indices >= $minIdx
     */
    public function getPositionsAfter(string $name, int $minIdx): array
    {
        $name = self::normalize($name);

        if (! isset($this->byName[$name])) {
            return [];
        }

        $positions = $this->byName[$name];
        $firstIdx = $this->findFirstIndexGte($positions, $minIdx);

        return $firstIdx === null ? [] : array_slice($positions, $firstIdx);
    }

    /**
     * Check if any directive with the given name exists at or after the position.
     *
     * @param  string  $name  Directive name (case-insensitive)
     * @param  int  $minIdx  Minimum token index (inclusive)
     */
    public function existsAfter(string $name, int $minIdx): bool
    {
        $name = self::normalize($name);

        if (! isset($this->byName[$name])) {
            return false;
        }

        $positions = $this->byName[$name];

        if ($positions === [] || $positions[array_key_last($positions)] < $minIdx) {
            return false;
        }

        return $this->findFirstIndexGte($positions, $minIdx) !== null;
    }

    /**
     * Find a matching terminator using the index with proper nesting support.
     *
     * @param  string  $directiveName  The opening directive name (e.g., 'if')
     * @param  int  $startIdx  Token index to start searching from (exclusive of opener)
     * @param  string[]  $terminators  Valid terminator names (e.g., ['endif'])
     */
    public function findMatchingTerminator(
        string $directiveName,
        int $startIdx,
        array $terminators
    ): ?int {
        $directiveName = self::normalize($directiveName);

        $openerPositions = $this->getPositionsAfter($directiveName, $startIdx);

        $terminatorPositions = [];
        foreach ($terminators as $term) {
            foreach ($this->getPositionsAfter($term, $startIdx) as $pos) {
                $terminatorPositions[$pos] = true;
            }
        }

        if ($terminatorPositions === []) {
            return null;
        }

        $events = [];
        foreach ($openerPositions as $pos) {
            $events[] = ['pos' => $pos, 'delta' => 1];
        }
        foreach (array_keys($terminatorPositions) as $pos) {
            $events[] = ['pos' => $pos, 'delta' => -1];
        }

        usort($events, static fn ($a, $b) => $a['pos'] <=> $b['pos']);

        $nesting = 0;
        foreach ($events as $event) {
            if ($event['delta'] === 1) {
                $nesting++;

                continue;
            }

            if ($nesting === 0) {
                return $event['pos'];
            }

            $nesting--;
        }

        return null;
    }

    /**
     * Check if a terminator exists for a directive (quick existence check).
     *
     * @param  string  $directiveName  The opening directive name
     * @param  int  $startIdx  Token index to start searching from
     * @param  string[]  $terminators  Valid terminator names
     */
    public function hasTerminator(
        string $directiveName,
        int $startIdx,
        array $terminators
    ): bool {
        return $this->findMatchingTerminator($directiveName, $startIdx, $terminators) !== null;
    }

    /**
     * Search for the first index in an array where value >= target.
     *
     * @param  int[]  $arr  Sorted array of integers
     * @param  int  $target  Target value
     */
    protected function findFirstIndexGte(array $arr, int $target): ?int
    {
        if ($arr === []) {
            return null;
        }

        $lo = 0;
        $hi = count($arr) - 1;
        $result = null;

        while ($lo <= $hi) {
            $mid = (int) (($lo + $hi) / 2);

            if ($arr[$mid] >= $target) {
                $result = $mid;
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }

        return $result;
    }

    /**
     * @param  array{type: int, start: int, end: int}  $token
     */
    private function isDirectiveToken(array $token): bool
    {
        return $token['type'] === TokenType::Directive;
    }

    private static function normalize(string $name): string
    {
        return StringInterner::lower($name);
    }
}
