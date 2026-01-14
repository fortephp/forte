<?php

declare(strict_types=1);

namespace Forte\Enclaves;

class PathMatcher
{
    private const SCORE_PER_SEGMENT = 5;

    private const SCORE_EXACT_SEGMENT_BONUS = 5;

    /**
     * Compute a heuristic specificity score for a glob-like pattern
     *
     * Scoring rules (higher means more specific):
     * - Each segment that is not a ** contributes a base of 5 points.
     * - Literal characters in a segment (excluding *) add 1 point each.
     * - A fully literal segment, without wildcards, receives an extra 5-point bonus.
     * - The total number of segments is added as a small bias toward deeper paths.
     *
     * @param  string  $pattern  The glob-like pattern to score
     */
    public static function specificityScore(string $pattern): int
    {
        $normalized = self::normalize($pattern);

        if ($normalized === '') {
            return 0;
        }

        $score = 0;
        $segments = explode('/', $normalized);

        foreach ($segments as $segment) {
            if ($segment === '**') {
                continue;
            }

            $score += self::SCORE_PER_SEGMENT;

            $score += strlen(str_replace('*', '', $segment));

            if (! str_contains($segment, '*')) {
                $score += self::SCORE_EXACT_SEGMENT_BONUS;
            }
        }

        return $score + count($segments);
    }

    /**
     * Normalize a path or pattern string for matching
     *
     * - Converts backslashes to forward slashes.
     * - Collapses repeated slashes to a single slash.
     * - Trims a trailing slash (except when the whole string is "/").
     * - Lowercases the result to make matching case-insensitive.
     *
     * @param  string  $s  Raw path or glob pattern
     */
    public static function normalize(string $s): string
    {
        $normalized = str_replace('\\', '/', $s);
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        return strtolower($normalized);
    }

    /**
     * Check whether a path matches a glob pattern
     *
     * Rules:
     * - Matching is case-insensitive and uses '/' as the separator.
     * - '*' matches any sequence of characters within a single segment.
     * - '**' matches zero or more whole segments with backtracking.
     *
     * @param  string  $pattern  Glob-like pattern supporting '*' and '**'
     * @param  string  $path  Path to test against the pattern
     */
    public static function match(string $pattern, string $path): bool
    {
        $normalizedPattern = self::normalize($pattern);
        $normalizedPath = self::normalize($path);

        $patternSegments = $normalizedPattern === '' ? [] : explode('/', $normalizedPattern);
        $pathSegments = $normalizedPath === '' ? [] : explode('/', $normalizedPath);

        return self::matchSegments($patternSegments, $pathSegments);
    }

    /**
     * @param  list<string>  $patternSegments  Ordered list of pattern segments
     * @param  list<string>  $pathSegments  Ordered list of path segments
     */
    private static function matchSegments(array $patternSegments, array $pathSegments): bool
    {
        $collapsedPattern = self::collapseConsecutiveWildcards($patternSegments);

        $patternIndex = 0;
        $pathIndex = 0;
        $patternLength = count($collapsedPattern);
        $pathLength = count($pathSegments);
        $lastWildcardPatternIndex = -1;
        $lastWildcardPathIndex = -1;

        while ($pathIndex < $pathLength) {
            if ($patternIndex < $patternLength && $collapsedPattern[$patternIndex] === '**') {
                $lastWildcardPatternIndex = $patternIndex++;
                $lastWildcardPathIndex = $pathIndex;

                continue;
            }

            if ($patternIndex < $patternLength && self::matchSegment($collapsedPattern[$patternIndex], $pathSegments[$pathIndex])) {
                $patternIndex++;
                $pathIndex++;

                continue;
            }

            if ($lastWildcardPatternIndex !== -1) {
                $patternIndex = $lastWildcardPatternIndex + 1;
                $pathIndex = ++$lastWildcardPathIndex;

                continue;
            }

            return false;
        }

        while ($patternIndex < $patternLength && $collapsedPattern[$patternIndex] === '**') {
            $patternIndex++;
        }

        return $patternIndex === $patternLength;
    }

    /**
     * @param  list<string>  $segments  Pattern segments in order
     * @return list<string>
     */
    private static function collapseConsecutiveWildcards(array $segments): array
    {
        $collapsed = [];
        $previousWasDoubleWildcard = false;

        foreach ($segments as $segment) {
            if ($segment === '**') {
                if ($previousWasDoubleWildcard) {
                    continue;
                }
                $previousWasDoubleWildcard = true;
            } else {
                $previousWasDoubleWildcard = false;
            }
            $collapsed[] = $segment;
        }

        return $collapsed;
    }

    /**
     * @param  string  $patternSegment  Pattern part for one segment may include a wildcard
     * @param  string  $pathSegment  Path part for the corresponding segment
     */
    private static function matchSegment(string $patternSegment, string $pathSegment): bool
    {
        if (! str_contains($patternSegment, '*')) {
            return $patternSegment === $pathSegment;
        }

        $quotedPattern = preg_quote($patternSegment, '#');
        $regex = '#^'.str_replace('\*', '.*', $quotedPattern).'$#';

        return (bool) preg_match($regex, $pathSegment);
    }
}
