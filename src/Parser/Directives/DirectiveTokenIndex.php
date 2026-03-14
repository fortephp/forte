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
     * @var int[]
     */
    private array $allPositions = [];

    /**
     * @var string[]
     */
    private array $allNames = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $nameSetCache = [];

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
            $this->allPositions[] = $idx;
            $this->allNames[] = $name;
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

    public function existsBetween(string $name, int $minIdx, int $maxIdxExclusive): bool
    {
        $name = self::normalize($name);

        if (! isset($this->byName[$name])) {
            return false;
        }

        $positions = $this->byName[$name];

        if ($positions === [] || $positions[array_key_last($positions)] < $minIdx) {
            return false;
        }

        $firstIdx = $this->findFirstIndexGte($positions, $minIdx);
        if ($firstIdx === null) {
            return false;
        }

        return $positions[$firstIdx] < $maxIdxExclusive;
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
        array $terminators,
        ?int $maxIdxExclusive = null,
        int $initialNesting = 0
    ): ?int {
        return $this->findMatchingTerminatorForOpeners(
            [$directiveName],
            $startIdx,
            $terminators,
            $maxIdxExclusive,
            $initialNesting
        );
    }

    /**
     * Find a matching terminator for one or more opener names.
     *
     * @param  string[]  $openerNames
     * @param  string[]  $terminators
     */
    public function findMatchingTerminatorForOpeners(
        array $openerNames,
        int $startIdx,
        array $terminators,
        ?int $maxIdxExclusive = null,
        int $initialNesting = 0
    ): ?int {
        $openerSet = $this->getNameSet($openerNames);

        $firstIdx = $this->findFirstIndexGte($this->allPositions, $startIdx);
        if ($firstIdx === null) {
            return null;
        }

        $terminatorSet = $this->getNameSet($terminators);
        $nesting = $initialNesting;

        for ($i = $firstIdx; $i < count($this->allPositions); $i++) {
            if ($maxIdxExclusive !== null && $this->allPositions[$i] >= $maxIdxExclusive) {
                return null;
            }

            $name = $this->allNames[$i];
            if (isset($openerSet[$name])) {
                $nesting++;

                continue;
            }

            if (! isset($terminatorSet[$name])) {
                continue;
            }

            if ($nesting === 0) {
                return $this->allPositions[$i];
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
        array $terminators,
        ?int $maxIdxExclusive = null
    ): bool {
        return $this->findMatchingTerminator($directiveName, $startIdx, $terminators, $maxIdxExclusive) !== null;
    }

    /**
     * Find the first zero-depth branch or terminator boundary for a directive.
     *
     * @param  string[]  $terminatorNames
     * @param  string[]  $branchNames
     */
    public function findMatchingBoundary(
        string $directiveName,
        int $startIdx,
        array $terminatorNames,
        array $branchNames = [],
        ?int $maxIdxExclusive = null,
        int $initialNesting = 0
    ): ?int {
        return $this->findMatchingBoundaryForOpeners(
            [$directiveName],
            $startIdx,
            $terminatorNames,
            $branchNames,
            $maxIdxExclusive,
            $initialNesting
        );
    }

    /**
     * Find the first zero-depth branch or terminator boundary for opener families.
     *
     * @param  string[]  $openerNames
     * @param  string[]  $terminatorNames
     * @param  string[]  $branchNames
     */
    public function findMatchingBoundaryForOpeners(
        array $openerNames,
        int $startIdx,
        array $terminatorNames,
        array $branchNames = [],
        ?int $maxIdxExclusive = null,
        int $initialNesting = 0
    ): ?int {
        $openerSet = $this->getNameSet($openerNames);

        $firstIdx = $this->findFirstIndexGte($this->allPositions, $startIdx);
        if ($firstIdx === null) {
            return null;
        }

        $terminatorSet = $this->getNameSet($terminatorNames);
        $branchSet = $this->getNameSet($branchNames);
        $nesting = $initialNesting;

        for ($i = $firstIdx; $i < count($this->allPositions); $i++) {
            if ($maxIdxExclusive !== null && $this->allPositions[$i] >= $maxIdxExclusive) {
                return null;
            }

            $name = $this->allNames[$i];
            if (isset($openerSet[$name])) {
                $nesting++;

                continue;
            }

            if (isset($branchSet[$name])) {
                if ($nesting === 0) {
                    return $this->allPositions[$i];
                }

                continue;
            }

            if (! isset($terminatorSet[$name])) {
                continue;
            }

            if ($nesting === 0) {
                return $this->allPositions[$i];
            }

            $nesting--;
        }

        return null;
    }

    /**
     * Analyze an unknown directive for its first reachable branch and closer.
     *
     * @param  string[]  $terminatorNames
     * @param  string[]  $branchNames
     * @return array{terminatorIdx: int|null, terminatorName: string|null, branchIdx: int|null}
     */
    public function analyzeUnknownDirective(
        string $directiveName,
        int $startIdx,
        array $terminatorNames,
        array $branchNames = [],
        ?int $maxIdxExclusive = null
    ): array {
        return $this->analyzeUnknownDirectiveFamily(
            [$directiveName],
            $startIdx,
            $terminatorNames,
            $branchNames,
            $maxIdxExclusive
        );
    }

    /**
     * Analyze an unknown directive family for its first reachable branch and closer.
     *
     * @param  string[]  $openerNames
     * @param  string[]  $terminatorNames
     * @param  string[]  $branchNames
     * @return array{terminatorIdx: int|null, terminatorName: string|null, branchIdx: int|null}
     */
    public function analyzeUnknownDirectiveFamily(
        array $openerNames,
        int $startIdx,
        array $terminatorNames,
        array $branchNames = [],
        ?int $maxIdxExclusive = null
    ): array {
        $openerSet = $this->getNameSet($openerNames);

        $firstIdx = $this->findFirstIndexGte($this->allPositions, $startIdx);
        if ($firstIdx === null) {
            return [
                'terminatorIdx' => null,
                'terminatorName' => null,
                'branchIdx' => null,
            ];
        }

        $terminatorSet = $this->getNameSet($terminatorNames);
        $branchSet = $this->getNameSet($branchNames);
        $nesting = 0;
        $branchIdx = null;

        for ($i = $firstIdx; $i < count($this->allPositions); $i++) {
            if ($maxIdxExclusive !== null && $this->allPositions[$i] >= $maxIdxExclusive) {
                return [
                    'terminatorIdx' => null,
                    'terminatorName' => null,
                    'branchIdx' => null,
                ];
            }

            $name = $this->allNames[$i];
            if (isset($openerSet[$name])) {
                $nesting++;

                continue;
            }

            if (isset($terminatorSet[$name])) {
                if ($nesting === 0) {
                    return [
                        'terminatorIdx' => $this->allPositions[$i],
                        'terminatorName' => $name,
                        'branchIdx' => $branchIdx,
                    ];
                }

                $nesting--;

                continue;
            }

            if ($branchIdx === null && $nesting === 0 && isset($branchSet[$name])) {
                $branchIdx = $this->allPositions[$i];
            }
        }

        return [
            'terminatorIdx' => null,
            'terminatorName' => null,
            'branchIdx' => null,
        ];
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

    /**
     * @param  string[]  $names
     * @return array<string, true>
     */
    private function getNameSet(array $names): array
    {
        $key = self::normalize(implode('|', $names));
        if (isset($this->nameSetCache[$key])) {
            return $this->nameSetCache[$key];
        }

        $set = [];
        foreach ($names as $name) {
            $set[self::normalize($name)] = true;
        }

        return $this->nameSetCache[$key] = $set;
    }

    private static function normalize(string $name): string
    {
        return StringInterner::lower($name);
    }
}
