<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\DirectiveHelper;
use Forte\Parser\Directives\DirectiveTokenIndex;
use Forte\Parser\Directives\StructureRole;
use Forte\Parser\NodeKind;
use Forte\Support\StringInterner;

trait ProcessesDirectives
{
    protected function processDirective(): void
    {
        $startPos = $this->pos;
        $directiveToken = $this->tokens[$this->pos];

        $directiveName = DirectiveHelper::extractDirectiveName($directiveToken, $this->source);

        $tokenCount = 1;
        $argsContent = null;
        $checkIdx = $this->pos + 1;

        $argsInfo = DirectiveHelper::checkDirectiveArgs($this->tokens, $this->source, $checkIdx, count($this->tokens));
        $tokenCount += $argsInfo['consumed'];
        $argsContent = $argsInfo['argsContent'];

        if ($this->isSwitchRelatedDirective($directiveName)) {
            $this->processSwitchDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->isConditionRelatedDirective($directiveName)) {
            $this->processConditionDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->directives->isConditionalPair($directiveName)) {
            $this->processConditionalPairingDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->isBranchOfOpenDirective($directiveName)) {
            $this->openDirectiveBranch($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->isClosingDirective($directiveName)) {
            $this->closeDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->directives->isPaired($directiveName)) {
            $this->openPairedDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->tryOpenDiscoveredDirective($directiveName, $startPos, $tokenCount, $argsContent)) {
            return;
        }

        $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);
    }

    protected function tryOpenDiscoveredDirective(
        string $directiveName,
        int $startPos,
        int $tokenCount,
        ?string $argsContent = null
    ): bool {
        $family = $this->resolveDiscoveredDirectiveFamily($directiveName);
        if ($family === null) {
            return false;
        }
        $hasAdvisoryCondition = $family['hasAdvisoryCondition'];
        $elseName = $family['elseName'];
        $endName = $family['endName'];
        $openerNames = $family['openers'];
        $directiveIndex = $this->directiveIndex();
        $directiveStart = $startPos + $tokenCount;
        $searchEnd = $this->getDiscoveredDirectiveSearchEnd($openerNames, $directiveStart);

        if (! $hasAdvisoryCondition) {
            if (! $this->hasDirectiveInSearchRange($directiveIndex, $endName, $directiveStart, $searchEnd)) {
                return false;
            }

            $terminatorIdx = $directiveIndex->findMatchingTerminatorForOpeners(
                $openerNames,
                $directiveStart,
                [$endName],
                $searchEnd
            );

            if ($terminatorIdx === null) {
                return false;
            }

            $this->openPairedDirective($directiveName, $startPos, $tokenCount, $argsContent, [
                'terminators' => [$endName],
                'branches' => [],
                'openers' => $openerNames,
            ]);

            return true;
        }

        $genericConditionTerminators = $this->directives->getConditionTerminators();
        $terminators = in_array($endName, $genericConditionTerminators, true)
            ? $genericConditionTerminators
            : array_merge([$endName], $genericConditionTerminators);
        $branches = [$elseName, 'else', 'elseif'];

        $hasPossibleBoundary = $this->hasDirectiveInSearchRange($directiveIndex, $endName, $directiveStart, $searchEnd)
            || $this->hasDirectiveInSearchRange($directiveIndex, $elseName, $directiveStart, $searchEnd);

        if (! $hasPossibleBoundary) {
            foreach ($genericConditionTerminators as $name) {
                if ($this->hasDirectiveInSearchRange($directiveIndex, $name, $directiveStart, $searchEnd)) {
                    $hasPossibleBoundary = true;
                    break;
                }
            }
        }

        if (! $hasPossibleBoundary) {
            return false;
        }

        $analysis = $directiveIndex->analyzeUnknownDirectiveFamily(
            $openerNames,
            $directiveStart,
            $terminators,
            $branches,
            $searchEnd
        );

        if ($analysis['terminatorIdx'] === null || $analysis['terminatorName'] === null) {
            return false;
        }

        $opensAsConditionLike = $analysis['branchIdx'] !== null || $analysis['terminatorName'] !== $endName;

        $this->openPairedDirective($directiveName, $startPos, $tokenCount, $argsContent, [
            'terminators' => $opensAsConditionLike ? $terminators : [$endName],
            'branches' => $opensAsConditionLike ? $branches : [],
            'openers' => $openerNames,
        ]);

        return true;
    }

    /**
     * @return array{baseName: string, elseName: string, endName: string, openers: string[], hasAdvisoryPair: bool, hasAdvisoryCondition: bool}|null
     */
    protected function resolveDiscoveredDirectiveFamily(string $directiveName): ?array
    {
        $directiveName = StringInterner::lower($directiveName);

        if (array_key_exists($directiveName, $this->discoveredDirectiveFamilyCache)) {
            return $this->discoveredDirectiveFamilyCache[$directiveName];
        }

        if ($this->directives->hasExplicitDirective($directiveName)) {
            $this->discoveredDirectiveFamilyCache[$directiveName] = null;

            return null;
        }

        $baseName = $directiveName;
        $unlessName = 'unless'.$directiveName;
        $isUnlessVariant = false;

        if (str_starts_with($directiveName, 'unless') && strlen($directiveName) > 6) {
            $candidateBase = substr($directiveName, 6);
            if ($candidateBase === '' || $this->directives->hasExplicitDirective($candidateBase)) {
                $this->discoveredDirectiveFamilyCache[$directiveName] = null;

                return null;
            }

            $baseName = $candidateBase;
            $unlessName = $directiveName;
            $isUnlessVariant = true;
        }

        $elseName = 'else'.$baseName;
        $endName = 'end'.$baseName;
        $hasAdvisoryPair = $this->directives->hasAdvisoryPair($baseName)
            || $this->directives->hasSeenDirective($endName);
        $hasAdvisoryCondition = $this->directives->hasAdvisoryCondition($baseName)
            || $this->directives->hasSeenDirective($elseName);

        if (! $hasAdvisoryPair && ! $hasAdvisoryCondition) {
            $this->discoveredDirectiveFamilyCache[$directiveName] = null;

            return null;
        }

        $openers = $isUnlessVariant || $this->directives->hasSeenDirective($unlessName)
            ? [$baseName, $unlessName]
            : [$directiveName];

        return $this->discoveredDirectiveFamilyCache[$directiveName] = [
            'baseName' => $baseName,
            'elseName' => $elseName,
            'endName' => $endName,
            'openers' => $openers,
            'hasAdvisoryPair' => $hasAdvisoryPair,
            'hasAdvisoryCondition' => $hasAdvisoryCondition,
        ];
    }

    protected function hasDirectiveInSearchRange(
        DirectiveTokenIndex $index,
        string $directiveName,
        int $minIdx,
        ?int $maxIdxExclusive
    ): bool {
        return $maxIdxExclusive === null
            ? $index->existsAfter($directiveName, $minIdx)
            : $index->existsBetween($directiveName, $minIdx, $maxIdxExclusive);
    }

    /**
     * @param  string[]  $openerNames
     */
    protected function getDiscoveredDirectiveSearchEnd(
        array $openerNames,
        int $startIdx
    ): ?int {
        $searchEnd = $this->attributeRegionEnd;
        $searchEnd = $this->takeEarlierBoundary($searchEnd, $this->findOpenElementBoundary($startIdx));
        $searchEnd = $this->takeEarlierBoundary($searchEnd, $this->findOpenDirectiveBoundary($openerNames, $startIdx));

        return $this->takeEarlierBoundary($searchEnd, $this->findOpenSwitchBoundary($startIdx));
    }

    protected function takeEarlierBoundary(?int $current, ?int $candidate): ?int
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null || $candidate < $current) {
            return $candidate;
        }

        return $current;
    }

    protected function findOpenElementBoundary(int $startIdx): ?int
    {
        $containingTagName = null;

        for ($i = count($this->openElements) - 1; $i >= 0; $i--) {
            $elementIdx = $this->openElements[$i];
            if (($this->nodes[$elementIdx]['kind'] ?? null) !== NodeKind::Element) {
                continue;
            }

            $containingTagName = $this->tagNames[$elementIdx] ?? null;
            if ($containingTagName !== null) {
                break;
            }
        }

        if ($containingTagName === null) {
            return null;
        }

        $containingTagDepth = 0;

        for ($i = $startIdx; $i < $this->tokenTotal; $i++) {
            if ($this->tokens[$i]['type'] !== TokenType::LessThan) {
                continue;
            }

            if ($i + 2 >= $this->tokenTotal) {
                continue;
            }

            if ($this->tokens[$i + 1]['type'] === TokenType::Slash) {
                if ($this->tokens[$i + 2]['type'] !== TokenType::TagName) {
                    continue;
                }

                $closingTagName = StringInterner::lower(substr(
                    $this->source,
                    $this->tokens[$i + 2]['start'],
                    $this->tokens[$i + 2]['end'] - $this->tokens[$i + 2]['start']
                ));

                if ($closingTagName !== $containingTagName) {
                    continue;
                }

                if ($containingTagDepth === 0) {
                    return $i;
                }

                $containingTagDepth--;

                continue;
            }

            if ($this->tokens[$i + 1]['type'] !== TokenType::TagName) {
                continue;
            }

            $openingTagName = StringInterner::lower(substr(
                $this->source,
                $this->tokens[$i + 1]['start'],
                $this->tokens[$i + 1]['end'] - $this->tokens[$i + 1]['start']
            ));

            if ($openingTagName !== $containingTagName) {
                continue;
            }

            if (! $this->isSelfClosingElementAt($i, $this->tokenTotal)) {
                $containingTagDepth++;
            }
        }

        return null;
    }

    protected function isSelfClosingElementAt(int $startIdx, int $limitIdx): bool
    {
        for ($i = $startIdx + 1; $i < $limitIdx; $i++) {
            $type = $this->tokens[$i]['type'];

            if ($type === TokenType::GreaterThan || $type === TokenType::SyntheticClose) {
                return ($this->tokens[$i - 1]['type'] ?? null) === TokenType::Slash;
            }

            if ($type === TokenType::LessThan) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $openerNames
     */
    protected function findOpenDirectiveBoundary(
        array $openerNames,
        int $startIdx
    ): ?int {
        if (empty($this->openDirectives)) {
            return null;
        }

        $index = $this->directiveIndex();
        $boundary = null;

        foreach ($this->openDirectives as $frame) {
            if ($frame['terminators'] === [] && $frame['branches'] === []) {
                continue;
            }

            $initialNesting = $this->directiveFamiliesIntersect($frame['openers'], $openerNames) ? 1 : 0;
            $boundaryIdx = $frame['branches'] !== []
                ? $index->findMatchingBoundaryForOpeners(
                    $frame['openers'],
                    $startIdx,
                    $frame['terminators'],
                    $frame['branches'],
                    null,
                    $initialNesting
                )
                : $index->findMatchingTerminatorForOpeners(
                    $frame['openers'],
                    $startIdx,
                    $frame['terminators'],
                    null,
                    $initialNesting
                );

            if ($boundaryIdx !== null) {
                $boundary = $this->takeEarlierBoundary($boundary, $boundaryIdx);
            }
        }

        return $boundary;
    }

    protected function findOpenSwitchBoundary(int $startIdx): ?int
    {
        if (empty($this->openSwitches)) {
            return null;
        }

        $index = $this->directiveIndex();
        $boundary = null;

        foreach ($this->openSwitches as $frame) {
            $boundaries = array_merge($this->directives->getSwitchBranches($frame['name']), ['endswitch']);
            $boundaryIdx = $index->findMatchingTerminator($frame['name'], $startIdx, $boundaries);

            if ($boundaryIdx !== null) {
                $boundary = $this->takeEarlierBoundary($boundary, $boundaryIdx);
            }
        }

        return $boundary;
    }

    /**
     * @param  string[]  $left
     * @param  string[]  $right
     */
    protected function directiveFamiliesIntersect(array $left, array $right): bool
    {
        if (($left[0] ?? null) === ($right[0] ?? null)) {
            return true;
        }

        $leftSecond = $left[1] ?? null;
        $rightSecond = $right[1] ?? null;

        return $leftSecond === ($right[0] ?? null)
            || $rightSecond === ($left[0] ?? null)
            || ($leftSecond !== null && $leftSecond === $rightSecond);
    }

    protected function isClosingDirective(string $directiveName): bool
    {
        if (empty($this->openDirectives)) {
            return false;
        }

        for ($i = count($this->openDirectives) - 1; $i >= 0; $i--) {
            if (in_array($directiveName, $this->openDirectives[$i]['terminators'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{terminators: string[], branches: string[], openers: string[]}|null  $frameMeta
     */
    protected function openPairedDirective(
        string $directiveName,
        int $startPos,
        int $tokenCount,
        ?string $argsContent = null,
        ?array $frameMeta = null
    ): void {
        $blockNode = $this->createNode(
            kind: NodeKind::DirectiveBlock,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $blockNode['name'] = $directiveName;
        $blockNode['args'] = $argsContent;

        $blockIdx = $this->addChild($blockNode);

        $elementStackBase = count($this->openElements);

        $this->checkElementDepth();
        $this->openElements[] = $blockIdx;

        $startDirectiveNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: $blockIdx,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $startDirectiveNode['name'] = $directiveName;
        $startDirectiveNode['args'] = $argsContent;
        $startDirectiveNode['role'] = StructureRole::Opening;

        $startDirectiveIdx = $this->addChild($startDirectiveNode);

        $directive = $this->directives->getDirective($directiveName);
        $terminators = $frameMeta['terminators'] ?? $directive->terminators ?? [];
        $branches = $frameMeta['branches'] ?? $directive->conditionLikeBranches ?? [];
        $openers = $frameMeta['openers'] ?? [$directiveName];

        $this->checkDirectiveDepth();
        $this->openDirectives[] = [
            'blockIdx' => $blockIdx,
            'startDirectiveIdx' => $startDirectiveIdx,
            'name' => $directiveName,
            'elementStackBase' => $elementStackBase,
            'terminators' => $terminators,
            'branches' => $branches,
            'openers' => $openers,
        ];

        $this->checkElementDepth();
        $this->openElements[] = $startDirectiveIdx;

        $this->pos += $tokenCount;
    }

    protected function isBranchOfOpenDirective(string $directiveName): bool
    {
        if (empty($this->openDirectives)) {
            return false;
        }

        for ($i = count($this->openDirectives) - 1; $i >= 0; $i--) {
            if (in_array($directiveName, $this->openDirectives[$i]['branches'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function openDirectiveBranch(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if (empty($this->openDirectives)) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $matchedIdx = -1;
        for ($i = count($this->openDirectives) - 1; $i >= 0; $i--) {
            if (in_array($directiveName, $this->openDirectives[$i]['branches'], true)) {
                $matchedIdx = $i;
                break;
            }
        }

        if ($matchedIdx === -1) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $frame = $this->openDirectives[$matchedIdx];
        $blockIdx = $frame['blockIdx'];

        $this->popIfTop($frame['startDirectiveIdx']);
        $this->popElementsToDepth($frame['elementStackBase'] + 1);

        $branchNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: $blockIdx,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $branchNode['name'] = $directiveName;
        $branchNode['args'] = $argsContent;
        $branchNode['role'] = StructureRole::Intermediate;

        $branchIdx = $this->addChild($branchNode);

        $this->checkElementDepth();
        $this->openElements[] = $branchIdx;
        $this->openDirectives[$matchedIdx]['startDirectiveIdx'] = $branchIdx;

        $this->pos += $tokenCount;
    }

    protected function closeDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        $matchedIdx = -1;

        for ($i = count($this->openDirectives) - 1; $i >= 0; $i--) {
            if (in_array($directiveName, $this->openDirectives[$i]['terminators'], true)) {
                $matchedIdx = $i;
                break;
            }
        }

        if ($matchedIdx === -1) {
            $this->createStandaloneDirective(
                $directiveName,
                $startPos,
                $tokenCount,
                $argsContent
            );

            return;
        }

        for ($i = count($this->openDirectives) - 1; $i >= $matchedIdx; $i--) {
            $frame = array_pop($this->openDirectives);
            if ($frame === null) {
                continue;
            }

            $blockIdx = $frame['blockIdx'];

            $this->popElementsToDepth($frame['elementStackBase'] + 2);
            $this->popIfTop($frame['startDirectiveIdx']);

            $endDirectiveNode = $this->createNode(
                kind: NodeKind::Directive,
                parent: $blockIdx,
                tokenStart: $startPos,
                tokenCount: $tokenCount
            );
            $endDirectiveNode['name'] = $directiveName;
            $endDirectiveNode['args'] = $argsContent;
            $endDirectiveNode['role'] = StructureRole::Closing;

            $this->addChild($endDirectiveNode);

            $this->popIfTop($blockIdx);

            $endPos = $startPos + $tokenCount;
            $this->nodes[$blockIdx]['tokenCount'] = $endPos - $this->nodes[$blockIdx]['tokenStart'];
        }

        $this->pos += $tokenCount;
    }

    protected function createStandaloneDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        $node = $this->createNode(
            kind: NodeKind::Directive,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $node['name'] = $directiveName;
        $node['args'] = $argsContent;
        $node['role'] = StructureRole::None;

        $this->addChild($node);

        $this->pos += $tokenCount;
    }

    protected function closeRemainingDirectives(): void
    {
        while (! empty($this->openSwitches)) {
            $frame = array_pop($this->openSwitches);

            if ($frame['currentCaseIdx'] !== null) {
                $this->popIfTop($frame['currentCaseIdx']);
            }

            $this->popIfTop($frame['switchDirectiveIdx']);
            $this->popIfTop($frame['blockIdx']);

            $blockIdx = $frame['blockIdx'];
            $endPos = count($this->tokens);
            $this->nodes[$blockIdx]['tokenCount'] = $endPos - $this->nodes[$blockIdx]['tokenStart'];
        }

        while (! empty($this->openConditions)) {
            $frame = array_pop($this->openConditions);

            $this->popIfTop($frame['currentBranchIdx']);
            $this->popIfTop($frame['blockIdx']);

            $blockIdx = $frame['blockIdx'];
            $endPos = count($this->tokens);
            $this->nodes[$blockIdx]['tokenCount'] = $endPos - $this->nodes[$blockIdx]['tokenStart'];
        }

        while (! empty($this->openDirectives)) {
            $frame = array_pop($this->openDirectives);
            $blockIdx = $frame['blockIdx'];

            $this->popIfTop($frame['startDirectiveIdx']);
            $this->popIfTop($blockIdx);

            $endPos = count($this->tokens);
            $this->nodes[$blockIdx]['tokenCount'] = $endPos - $this->nodes[$blockIdx]['tokenStart'];
        }
    }

    protected function isConditionRelatedDirective(string $directiveName): bool
    {
        if ($this->directives->isCondition($directiveName)) {
            return true;
        }

        if (! empty($this->openConditions)) {
            $currentCondition = $this->openConditions[array_key_last($this->openConditions)];
            $branches = $this->directives->getBranches($currentCondition['name']);

            foreach ($branches as $branch) {
                if ($branch === $directiveName) {
                    return true;
                }
            }
        }

        return in_array($directiveName, $this->directives->getConditionTerminators(), true);
    }

    protected function processConditionDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if ($this->tryHandleConditionDirectiveWithOpenDirective($directiveName, $startPos, $tokenCount, $argsContent)) {
            return;
        }

        $terminators = $this->directives->getConditionTerminators();
        $isFinalTerminator = in_array($directiveName, $terminators, true);

        if ($isFinalTerminator && ! empty($this->openConditions)) {
            $this->closeCondition($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if (! empty($this->openConditions)) {
            $currentCondition = $this->openConditions[array_key_last($this->openConditions)];
            $branches = $this->directives->getBranches($currentCondition['name']);

            foreach ($branches as $branch) {
                if ($branch === $directiveName) {
                    $this->openConditionBranch($directiveName, $startPos, $tokenCount, $argsContent);

                    return;
                }
            }
        }

        $directive = $this->directives->getDirective($directiveName);
        if ($this->directives->isCondition($directiveName)
            && $directive !== null
            && $directive->role === StructureRole::Opening) {
            $this->openCondition($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);
    }

    protected function tryHandleConditionDirectiveWithOpenDirective(
        string $directiveName,
        int $startPos,
        int $tokenCount,
        ?string $argsContent = null
    ): bool {
        $directiveMatch = $this->getMatchingOpenDirectiveFrame($directiveName);
        if ($directiveMatch === null) {
            return false;
        }

        $conditionMatch = $this->getMatchingOpenConditionFrame($directiveName);
        if ($conditionMatch !== null
            && $this->getConditionFrameTokenStart($conditionMatch['frame']) >= $this->getDirectiveFrameTokenStart($directiveMatch['frame'])) {
            return false;
        }

        if ($directiveMatch['kind'] === 'branch') {
            $this->openDirectiveBranch($directiveName, $startPos, $tokenCount, $argsContent);

            return true;
        }

        $this->closeDirective($directiveName, $startPos, $tokenCount, $argsContent);

        return true;
    }

    /**
     * @return array{frame: array{blockIdx: int, startDirectiveIdx: int, name: string, elementStackBase: int, terminators: string[], branches: string[], openers: string[]}, kind: 'branch'|'close'}|null
     */
    protected function getMatchingOpenDirectiveFrame(string $directiveName): ?array
    {
        if (empty($this->openDirectives)) {
            return null;
        }

        for ($i = count($this->openDirectives) - 1; $i >= 0; $i--) {
            $frame = $this->openDirectives[$i];

            if (in_array($directiveName, $frame['branches'], true)) {
                return ['frame' => $frame, 'kind' => 'branch'];
            }

            if (in_array($directiveName, $frame['terminators'], true)) {
                return ['frame' => $frame, 'kind' => 'close'];
            }
        }

        return null;
    }

    /**
     * @return array{frame: array{blockIdx: int, currentBranchIdx: int, name: string, elementStackBase: int}, kind: 'branch'|'close'}|null
     */
    protected function getMatchingOpenConditionFrame(string $directiveName): ?array
    {
        if (empty($this->openConditions)) {
            return null;
        }

        $frame = $this->openConditions[array_key_last($this->openConditions)];
        $terminators = $this->directives->getConditionTerminators();

        if (in_array($directiveName, $terminators, true)) {
            return ['frame' => $frame, 'kind' => 'close'];
        }

        $branches = $this->directives->getBranches($frame['name']);
        if (in_array($directiveName, $branches, true)) {
            return ['frame' => $frame, 'kind' => 'branch'];
        }

        return null;
    }

    /**
     * @param  array{startDirectiveIdx: int}  $frame
     */
    protected function getDirectiveFrameTokenStart(array $frame): int
    {
        return $this->nodes[$frame['startDirectiveIdx']]['tokenStart'] ?? -1;
    }

    /**
     * @param  array{currentBranchIdx: int}  $frame
     */
    protected function getConditionFrameTokenStart(array $frame): int
    {
        return $this->nodes[$frame['currentBranchIdx']]['tokenStart'] ?? -1;
    }

    protected function openCondition(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        $blockNode = $this->createNode(
            kind: NodeKind::DirectiveBlock,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $blockNode['name'] = $directiveName;
        $blockNode['args'] = $argsContent;

        $blockIdx = $this->addChild($blockNode);

        $elementStackBase = count($this->openElements);

        $this->checkElementDepth();
        $this->openElements[] = $blockIdx;

        $branchNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $branchNode['name'] = $directiveName;
        $branchNode['args'] = $argsContent;
        $branchNode['role'] = StructureRole::Opening;

        $branchIdx = $this->addChild($branchNode);

        $this->checkElementDepth();
        $this->openElements[] = $branchIdx;

        $this->checkConditionDepth();
        $this->openConditions[] = [
            'blockIdx' => $blockIdx,
            'currentBranchIdx' => $branchIdx,
            'name' => $directiveName,
            'elementStackBase' => $elementStackBase,
        ];

        $this->pos += $tokenCount;
    }

    protected function openConditionBranch(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if (empty($this->openConditions)) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $conditionIdx = array_key_last($this->openConditions);
        $frame = $this->openConditions[$conditionIdx];

        $this->popIfTop($frame['currentBranchIdx']);
        $this->popElementsToDepth($frame['elementStackBase'] + 1);

        $branchNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $branchNode['name'] = $directiveName;
        $branchNode['args'] = $argsContent;
        $branchNode['role'] = StructureRole::Intermediate;

        $branchIdx = $this->addChild($branchNode);

        $this->checkElementDepth();
        $this->openElements[] = $branchIdx;
        $this->openConditions[$conditionIdx]['currentBranchIdx'] = $branchIdx;

        $this->pos += $tokenCount;
    }

    protected function closeCondition(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if (empty($this->openConditions)) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $frame = array_pop($this->openConditions);

        $this->popElementsToDepth($frame['elementStackBase'] + 1);
        $this->popIfTop($frame['currentBranchIdx']);

        $terminatorNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $terminatorNode['name'] = $directiveName;
        $terminatorNode['args'] = $argsContent;
        $terminatorNode['role'] = StructureRole::Closing;

        $this->addChild($terminatorNode);

        $this->popIfTop($frame['blockIdx']);

        $blockIdx = $frame['blockIdx'];
        $endPos = $startPos + $tokenCount;
        $this->nodes[$blockIdx]['tokenCount'] = $endPos - $this->nodes[$blockIdx]['tokenStart'];

        $this->pos += $tokenCount;
    }
}
