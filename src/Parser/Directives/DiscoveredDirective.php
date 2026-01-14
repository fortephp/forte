<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

class DiscoveredDirective
{
    public string $name;

    public ArgumentRequirement $args;

    public StructureRole $role = StructureRole::None;

    public bool $isCondition = false;

    public bool $hasConditionLikeBranches = false;

    /**
     * @var string[]
     */
    public array $terminators = [];

    /**
     * @var string[]
     */
    public array $conditionLikeBranches = [];

    public ?string $terminator = null;

    public bool $isSwitch = false;

    public ?string $switchParent = null;

    public bool $isSwitchBranch = false;

    public bool $isSwitchTerminator = false;

    public bool $isConditionalPair = false;

    public ?string $pairingStrategy = null;

    public bool $isConditionalClose = false;
}
