<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

use Forte\Lexer\Tokens\TokenType;
use Forte\Support\StringInterner;
use Forte\Support\StringUtilities;

class DirectiveHelper
{
    /**
     * Extract a directive name from a token (lowercased, without the @).
     *
     * @param  array{type: int, start: int, end: int}  $token
     */
    public static function extractDirectiveName(array $token, string $source): string
    {
        $text = substr($source, $token['start'], $token['end'] - $token['start']);

        if (str_starts_with($text, '@')) {
            $text = substr($text, 1);
        }

        return StringInterner::lower($text);
    }

    /**
     * Check if a matching terminator exists for a directive in the given token range.
     *
     * @param  string  $directiveName  The directive name, without @.
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @param  int  $startIdx  Start index (inclusive)
     * @param  int  $endIdx  End index (exclusive)
     */
    public static function hasTerminator(
        string $directiveName,
        array $tokens,
        string $source,
        int $startIdx,
        int $endIdx,
        ?Directives $directives = null,
        ?DirectiveTokenIndex $index = null
    ): bool {
        $terminators = self::getTerminatorNames($directiveName, $directives);

        if ($index !== null) {
            return $index->hasTerminator($directiveName, $startIdx, $terminators);
        }

        $primaryTerminator = self::getTerminatorName($directiveName, $directives);

        for ($i = $startIdx, $limit = min($endIdx, count($tokens)); $i < $limit; $i++) {
            if ($tokens[$i]['type'] !== TokenType::Directive) {
                continue;
            }

            $name = self::extractDirectiveName($tokens[$i], $source);
            if ($name === $primaryTerminator) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the token index of a matching terminator for a directive (handles nesting).
     *
     * @param  string  $directiveName  The directive name, without @.
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @param  int  $startIdx  Start index (inclusive)
     * @param  int  $endIdx  End index (exclusive)
     * @param  int  $maxLookahead  Max directive tokens to check (ignored if index provided)
     */
    public static function findMatchingTerminator(
        string $directiveName,
        array $tokens,
        string $source,
        int $startIdx,
        int $endIdx,
        ?Directives $directives = null,
        int $maxLookahead = PHP_INT_MAX,
        ?DirectiveTokenIndex $index = null
    ): ?int {
        $terminators = self::getTerminatorNames($directiveName, $directives);

        if ($index !== null) {
            return $index->findMatchingTerminator($directiveName, $startIdx, $terminators);
        }

        $primaryTerminator = self::getTerminatorName($directiveName, $directives);
        $needle = StringInterner::lower($directiveName);

        $nesting = 0;
        $checked = 0;

        for ($i = $startIdx, $limit = min($endIdx, count($tokens)); $i < $limit; $i++) {
            if ($tokens[$i]['type'] !== TokenType::Directive) {
                continue;
            }

            $checked++;
            if ($checked > $maxLookahead) {
                break;
            }

            $name = self::extractDirectiveName($tokens[$i], $source);

            if ($name === $needle) {
                $nesting++;

                continue;
            }

            if (in_array($name, $terminators, true) || $name === $primaryTerminator) {
                if ($nesting === 0) {
                    return $i;
                }
                $nesting--;
            }
        }

        return null;
    }

    /**
     * Collect all tokens from a directive until its matching terminator (inclusive).
     *
     * @param  string  $directiveName  The directive name, without @.
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @param  int  $startIdx  Start index (should be after the opening directive)
     * @param  int  $endIdx  End index (exclusive)
     * @return array{tokens: array<int, array{type: int, start: int, end: int}>, consumed: int, terminatorIdx: int|null}
     */
    public static function collectBlockTokens(
        string $directiveName,
        array $tokens,
        string $source,
        int $startIdx,
        int $endIdx,
        ?Directives $directives = null
    ): array {
        $primaryTerminator = self::getTerminatorName($directiveName, $directives);
        $terminators = self::getTerminatorNames($directiveName, $directives);
        $needle = StringInterner::lower($directiveName);

        $collected = [];
        $nest = 1;
        $i = $startIdx;
        $terminatorIdx = null;
        $limit = min($endIdx, count($tokens));

        while ($i < $limit && $nest > 0) {
            $token = $tokens[$i];
            $collected[] = $token;

            if ($token['type'] === TokenType::Directive) {
                $name = self::extractDirectiveName($token, $source);

                if ($name === $needle) {
                    $nest++;
                } elseif (in_array($name, $terminators, true) || $name === $primaryTerminator) {
                    $nest--;
                    if ($nest === 0) {
                        $terminatorIdx = $i;
                    }
                }
            }

            $i++;
        }

        return [
            'tokens' => $collected,
            'consumed' => $i - $startIdx,
            'terminatorIdx' => $terminatorIdx,
        ];
    }

    /**
     * Get the primary terminator name for a directive.
     */
    public static function getTerminatorName(string $directiveName, ?Directives $directives = null): string
    {
        if ($directives !== null) {
            return $directives->getTerminator($directiveName);
        }

        return 'end'.$directiveName;
    }

    /**
     * Get all valid terminator names for a directive.
     *
     * @return list<string>
     */
    public static function getTerminatorNames(string $directiveName, ?Directives $directives = null): array
    {
        if ($directives !== null) {
            $directive = $directives->getDirective($directiveName);
            if ($directive !== null && ! empty($directive->terminators)) {
                return array_values($directive->terminators);
            }
        }

        return ['end'.$directiveName];
    }

    /**
     * Check if a directive has args following it in the token stream.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @return array{hasArgs: bool, argsContent: string|null, consumed: int}
     */
    public static function checkDirectiveArgs(
        array $tokens,
        string $source,
        int $startIdx,
        int $endIdx
    ): array {
        $consumed = 0;
        $hasArgs = false;
        $argsContent = null;

        $checkIdx = $startIdx;

        if ($checkIdx < $endIdx && $tokens[$checkIdx]['type'] === TokenType::Whitespace) {
            if ($checkIdx + 1 < $endIdx && $tokens[$checkIdx + 1]['type'] === TokenType::DirectiveArgs) {
                $consumed++;
                $checkIdx++;
            }
        }

        if ($checkIdx < $endIdx && $tokens[$checkIdx]['type'] === TokenType::DirectiveArgs) {
            $hasArgs = true;
            $argsContent = substr($source, $tokens[$checkIdx]['start'], $tokens[$checkIdx]['end'] - $tokens[$checkIdx]['start']);
            $consumed++;
        }

        return [
            'hasArgs' => $hasArgs,
            'argsContent' => $argsContent,
            'consumed' => $consumed,
        ];
    }

    /**
     * Count directive arguments by counting commas in the args string.
     */
    public static function countDirectiveArgs(string $argsContent): int
    {
        if ($argsContent === '') {
            return 0;
        }

        return ArgumentScanner::countArguments(StringUtilities::unwrapParentheses($argsContent));
    }

    /**
     * Check if the args start with an array.
     */
    public static function argsStartWithArray(string $argsContent): bool
    {
        return isset($argsContent[1]) && $argsContent[1] === '[';
    }

    /**
     * Collect tokens until hitting any of the specified boundary directives (no nesting awareness).
     *
     * @param  array<int, string>  $boundaryDirectives
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @return array{tokens: array<int, array{type: int, start: int, end: int}>, consumed: int, boundaryIdx: int|null, boundaryName: string|null}
     */
    public static function collectTokensUntilBoundary(
        array $boundaryDirectives,
        array $tokens,
        string $source,
        int $startIdx,
        int $endIdx
    ): array {
        $boundaryLookup = array_flip(array_map(StringInterner::lower(...), $boundaryDirectives));

        $collected = [];
        $i = $startIdx;
        $boundaryIdx = null;
        $boundaryName = null;
        $limit = min($endIdx, count($tokens));

        while ($i < $limit) {
            $token = $tokens[$i];

            if ($token['type'] === TokenType::Directive) {
                $name = self::extractDirectiveName($token, $source);
                if (isset($boundaryLookup[$name])) {
                    $boundaryIdx = $i;
                    $boundaryName = $name;
                    break;
                }
            }

            $collected[] = $token;
            $i++;
        }

        return [
            'tokens' => $collected,
            'consumed' => $i - $startIdx,
            'boundaryIdx' => $boundaryIdx,
            'boundaryName' => $boundaryName,
        ];
    }

    /**
     * Collect tokens until hitting a boundary directive, skipping over nested pairs.
     *
     * @param  array<int, string>  $boundaryDirectives  Directive names that stop the collection
     * @param  array<int, array{string, string}>  $nestedPairs  Pairs to track, e.g. [['switch', 'endswitch']]
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @return array{tokens: array<int, array{type: int, start: int, end: int}>, consumed: int, boundaryIdx: int|null, boundaryName: string|null}
     */
    public static function collectTokensUntilBoundaryWithNesting(
        array $boundaryDirectives,
        array $nestedPairs,
        array $tokens,
        string $source,
        int $startIdx,
        int $endIdx
    ): array {
        $boundaryLookup = array_flip(array_map(StringInterner::lower(...), $boundaryDirectives));

        $nestedOpeners = [];
        $nestedClosers = [];
        foreach ($nestedPairs as [$opener, $closer]) {
            $lowerOpener = StringInterner::lower($opener);
            $lowerCloser = StringInterner::lower($closer);
            $nestedOpeners[$lowerOpener] = $lowerCloser;
            $nestedClosers[$lowerCloser] = $lowerOpener;
        }

        $collected = [];
        $i = $startIdx;
        $boundaryIdx = null;
        $boundaryName = null;
        $nestingLevels = [];
        $limit = min($endIdx, count($tokens));

        while ($i < $limit) {
            $token = $tokens[$i];

            if ($token['type'] === TokenType::Directive) {
                $name = self::extractDirectiveName($token, $source);

                if (isset($nestedOpeners[$name])) {
                    $nestingLevels[$name] = ($nestingLevels[$name] ?? 0) + 1;
                }

                if (isset($nestedClosers[$name])) {
                    $opener = $nestedClosers[$name];
                    if (isset($nestingLevels[$opener]) && $nestingLevels[$opener] > 0) {
                        $nestingLevels[$opener]--;
                        $collected[] = $token;
                        $i++;

                        continue;
                    }
                }

                if (isset($boundaryLookup[$name]) && ! self::isInsideAnyNest($nestingLevels)) {
                    $boundaryIdx = $i;
                    $boundaryName = $name;
                    break;
                }
            }

            $collected[] = $token;
            $i++;
        }

        return [
            'tokens' => $collected,
            'consumed' => $i - $startIdx,
            'boundaryIdx' => $boundaryIdx,
            'boundaryName' => $boundaryName,
        ];
    }

    /**
     * @param  array<string, int>  $nestingLevels
     */
    private static function isInsideAnyNest(array $nestingLevels): bool
    {
        foreach ($nestingLevels as $level) {
            if ($level > 0) {
                return true;
            }
        }

        return false;
    }
}
