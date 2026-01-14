<?php

declare(strict_types=1);

namespace Forte\Parser\Concerns;

use Forte\Parser\Directives\StructureRole;
use Forte\Parser\NodeKind;

trait ProcessesSwitchDirectives
{
    /**
     * @var array<int, array{blockIdx: int, switchDirectiveIdx: int, currentCaseIdx: int|null, name: string, elementStackBase: int}>
     */
    private array $openSwitches = [];

    protected function isSwitchRelatedDirective(string $directiveName): bool
    {
        if ($this->directives->isSwitch($directiveName)) {
            return true;
        }

        if ($directiveName === 'endswitch') {
            return true;
        }

        if (! empty($this->openSwitches)) {
            if ($this->directives->isSwitchBranch($directiveName)) {
                return true;
            }

            if ($this->directives->isSwitchTerminator($directiveName)) {
                return true;
            }
        }

        return false;
    }

    protected function processSwitchDirective(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if ($directiveName === 'endswitch' && ! empty($this->openSwitches)) {
            $this->closeSwitch($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->directives->isSwitchTerminator($directiveName) && ! empty($this->openSwitches)) {
            $this->processSwitchBreak($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if (! empty($this->openSwitches) && $this->directives->isSwitchBranch($directiveName)) {
            $this->openSwitchCase($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        if ($this->directives->isSwitch($directiveName)) {
            $this->openSwitch($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);
    }

    protected function openSwitch(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
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

        $switchDirectiveNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: $blockIdx,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $switchDirectiveNode['name'] = $directiveName;
        $switchDirectiveNode['args'] = $argsContent;
        $switchDirectiveNode['role'] = StructureRole::Opening;

        $switchDirectiveIdx = $this->addChild($switchDirectiveNode);

        $this->checkDirectiveDepth();
        $this->openSwitches[] = [
            'blockIdx' => $blockIdx,
            'switchDirectiveIdx' => $switchDirectiveIdx,
            'currentCaseIdx' => null,
            'name' => $directiveName,
            'elementStackBase' => $elementStackBase,
        ];

        $this->checkElementDepth();
        $this->openElements[] = $switchDirectiveIdx;

        $this->pos += $tokenCount;
    }

    protected function openSwitchCase(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if (empty($this->openSwitches)) {
            // Treat as standalone
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $frame = &$this->openSwitches[array_key_last($this->openSwitches)];
        $switchDirectiveIdx = $frame['switchDirectiveIdx'];

        // +2 because both block and @switch directive are on the stack
        $this->popElementsToDepth($frame['elementStackBase'] + 2);

        if ($frame['currentCaseIdx'] !== null) {
            $this->popIfTop($frame['currentCaseIdx']);
        }

        $caseNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: $switchDirectiveIdx,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $caseNode['name'] = $directiveName;
        $caseNode['args'] = $argsContent;
        $caseNode['role'] = StructureRole::Intermediate;

        $caseIdx = $this->addChild($caseNode);

        $frame['currentCaseIdx'] = $caseIdx;

        $this->checkElementDepth();
        $this->openElements[] = $caseIdx;

        $this->pos += $tokenCount;
    }

    protected function processSwitchBreak(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if (empty($this->openSwitches)) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $frame = $this->openSwitches[array_key_last($this->openSwitches)];
        $currentCaseIdx = $frame['currentCaseIdx'];

        if ($currentCaseIdx === null) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $breakNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: $currentCaseIdx,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $breakNode['name'] = $directiveName;
        $breakNode['args'] = $argsContent;
        $breakNode['role'] = StructureRole::Intermediate;

        $this->addChild($breakNode);

        $this->pos += $tokenCount;
    }

    protected function closeSwitch(string $directiveName, int $startPos, int $tokenCount, ?string $argsContent = null): void
    {
        if (empty($this->openSwitches)) {
            $this->createStandaloneDirective($directiveName, $startPos, $tokenCount, $argsContent);

            return;
        }

        $frame = array_pop($this->openSwitches);
        $blockIdx = $frame['blockIdx'];
        $switchDirectiveIdx = $frame['switchDirectiveIdx'];

        // +2 because both block and @switch directive are on the stack
        $this->popElementsToDepth($frame['elementStackBase'] + 2);

        if ($frame['currentCaseIdx'] !== null) {
            $this->popIfTop($frame['currentCaseIdx']);
        }

        // Pop @switch directive from openElements
        $this->popIfTop($switchDirectiveIdx);

        $endSwitchNode = $this->createNode(
            kind: NodeKind::Directive,
            parent: $blockIdx,
            tokenStart: $startPos,
            tokenCount: $tokenCount
        );
        $endSwitchNode['name'] = $directiveName;
        $endSwitchNode['args'] = $argsContent;
        $endSwitchNode['role'] = StructureRole::Closing;

        $this->addChild($endSwitchNode);

        // Pop block from openElements
        $this->popIfTop($blockIdx);

        $this->nodes[$blockIdx]['tokenCount'] = ($startPos + $tokenCount) - $this->nodes[$blockIdx]['tokenStart'];

        // Advance position past directive
        $this->pos += $tokenCount;
    }
}
