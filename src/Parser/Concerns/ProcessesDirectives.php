<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Parser\Directives\DirectiveHelper;
use Forte\Parser\Directives\StructureRole;
use Forte\Parser\NodeKind;

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

        $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);
    }

    protected function isClosingDirective(string $directiveName): bool
    {
        if (empty($this->openDirectives)) {
            return false;
        }

        foreach (array_reverse($this->openDirectives) as $frame) {
            $directive = $this->directives->getDirective($frame['name']);
            if ($directive !== null && ! empty($directive->terminators)) {
                if (in_array($directiveName, $directive->terminators, true)) {
                    return true;
                }
            }

            $terminator = $this->directives->getTerminator($frame['name']);
            if (strtolower((string) $terminator) === $directiveName) {
                return true;
            }
        }

        return false;
    }

    protected function openPairedDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
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

        // Create opening directive node as first child of block
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

        $this->checkDirectiveDepth();
        $this->openDirectives[] = [
            'blockIdx' => $blockIdx,
            'startDirectiveIdx' => $startDirectiveIdx,
            'name' => $directiveName,
            'elementStackBase' => $elementStackBase,
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

        foreach (array_reverse($this->openDirectives) as $frame) {
            $directive = $this->directives->getDirective($frame['name']);
            if ($directive !== null && $directive->hasConditionLikeBranches) {
                if (in_array($directiveName, $directive->conditionLikeBranches, true)) {
                    return true;
                }
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
            $frame = $this->openDirectives[$i];
            $directive = $this->directives->getDirective($frame['name']);
            if ($directive !== null && $directive->hasConditionLikeBranches) {
                if (in_array($directiveName, $directive->conditionLikeBranches, true)) {
                    $matchedIdx = $i;
                    break;
                }
            }
        }

        if ($matchedIdx === -1) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $frame = &$this->openDirectives[$matchedIdx];
        $blockIdx = $frame['blockIdx'];
        $startDirectiveIdx = $frame['startDirectiveIdx'];

        $this->popIfTop($startDirectiveIdx);

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

        $frame['startDirectiveIdx'] = $branchIdx;

        $this->pos += $tokenCount;
    }

    protected function closeDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        $matchedIdx = -1;

        for ($i = count($this->openDirectives) - 1; $i >= 0; $i--) {
            $frame = $this->openDirectives[$i];

            $directive = $this->directives->getDirective($frame['name']);
            if ($directive === null) {
                continue;
            }

            if (! empty($directive->terminators) && in_array($directiveName, $directive->terminators, true)) {
                $matchedIdx = $i;
                break;
            }

            $terminator = $this->directives->getTerminator($frame['name']);
            if (strtolower((string) $terminator) === $directiveName) {
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

        // Close all directives from the matched one to the top
        for ($i = count($this->openDirectives) - 1; $i >= $matchedIdx; $i--) {
            $frame = array_pop($this->openDirectives);
            if ($frame === null) {
                continue;
            }
            $blockIdx = $frame['blockIdx'];
            $startDirectiveIdx = $frame['startDirectiveIdx'];

            // Elements opened inside a directive cannot remain open when the directive closes.
            // +2 because both block and start directive are on the stack
            $this->popElementsToDepth($frame['elementStackBase'] + 2);

            $this->popIfTop($startDirectiveIdx);

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

            // Pop block from element stack
            $this->popIfTop($blockIdx);

            // Update the block's tokenCount to include all tokens up to and including the end directive
            $endPos = $startPos + $tokenCount;
            $newTokenCount = $endPos - $this->nodes[$blockIdx]['tokenStart'];
            $this->nodes[$blockIdx]['tokenCount'] = $newTokenCount;
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
        // Close the remaining switches first
        while (! empty($this->openSwitches)) {
            $frame = array_pop($this->openSwitches);

            if ($frame['currentCaseIdx'] !== null) {
                $this->popIfTop($frame['currentCaseIdx']);
            }

            $this->popIfTop($frame['switchDirectiveIdx']);
            $this->popIfTop($frame['blockIdx']);

            $blockIdx = $frame['blockIdx'];
            $endPos = count($this->tokens);
            $newTokenCount = $endPos - $this->nodes[$blockIdx]['tokenStart'];
            $this->nodes[$blockIdx]['tokenCount'] = $newTokenCount;
        }

        while (! empty($this->openConditions)) {
            $frame = array_pop($this->openConditions);

            $this->popIfTop($frame['currentBranchIdx']);
            $this->popIfTop($frame['blockIdx']);

            $blockIdx = $frame['blockIdx'];
            $endPos = count($this->tokens);
            $newTokenCount = $endPos - $this->nodes[$blockIdx]['tokenStart'];
            $this->nodes[$blockIdx]['tokenCount'] = $newTokenCount;
        }

        while (! empty($this->openDirectives)) {
            $frame = array_pop($this->openDirectives);
            $blockIdx = $frame['blockIdx'];
            $startDirectiveIdx = $frame['startDirectiveIdx'];

            $this->popIfTop($startDirectiveIdx);
            $this->popIfTop($blockIdx);

            $endPos = count($this->tokens);
            $newTokenCount = $endPos - $this->nodes[$blockIdx]['tokenStart'];
            $this->nodes[$blockIdx]['tokenCount'] = $newTokenCount;
        }
    }

    protected function isConditionRelatedDirective(string $directiveName): bool
    {
        if ($this->directives->isCondition($directiveName)) {
            return true;
        }

        if (! empty($this->openConditions)) {
            $currentCondition = end($this->openConditions);
            $branches = $this->directives->getBranches($currentCondition['name']);

            foreach ($branches as $branch) {
                if (strtolower((string) $branch) === $directiveName) {
                    return true;
                }
            }
        }

        $terminators = $this->directives->getConditionTerminators();

        return in_array($directiveName, $terminators, true);
    }

    protected function processConditionDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        $terminators = $this->directives->getConditionTerminators();
        $isFinalTerminator = in_array($directiveName, $terminators, true);

        if ($isFinalTerminator && ! empty($this->openConditions)) {
            $this->closeCondition($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if (! empty($this->openConditions)) {
            $currentCondition = end($this->openConditions);
            $branches = $this->directives->getBranches($currentCondition['name']);

            foreach ($branches as $branch) {
                if (strtolower((string) $branch) === $directiveName) {
                    $this->openConditionBranch($directiveName, $startPos, $tokenCount, $argsContent);

                    return;
                }
            }
        }

        $directive = $this->directives->getDirective($directiveName);
        if ($this->directives->isCondition($directiveName) &&
            $directive !== null &&
            $directive->role === StructureRole::Opening) {
            $this->openCondition($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);
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

        $conditionIdx = count($this->openConditions) - 1;
        $frame = &$this->openConditions[$conditionIdx];

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

        $frame['currentBranchIdx'] = $branchIdx;

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
        $newTokenCount = $endPos - $this->nodes[$blockIdx]['tokenStart'];
        $this->nodes[$blockIdx]['tokenCount'] = $newTokenCount;

        $this->pos += $tokenCount;
    }
}
