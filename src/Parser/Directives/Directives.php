<?php

declare(strict_types=1);

namespace Forte\Parser\Directives;

use DirectoryIterator;
use Forte\Lexer\Tokens;
use Forte\Lexer\Tokens\TokenType;
use Forte\Support\StringInterner;
use Illuminate\View\Compilers\BladeCompiler;

class Directives
{
    /**
     * @var array<string, bool>
     */
    protected array $conditions = [];

    /**
     * @var array<string, int>
     */
    protected array $finalTerminators = [];

    /**
     * @var array<string, DiscoveredDirective>
     */
    protected array $directives = [];

    /**
     * @var string[]|null
     */
    protected ?array $bladeConditions = null;

    /**
     * If true, accept all @word patterns as directives.
     */
    private bool $acceptAll = false;

    public static function withDefaults(): self
    {
        $instance = new self;
        $directivesPath = __DIR__.'/../../../resources/directives';

        if (is_dir($directivesPath)) {
            $instance->loadDirectory($directivesPath);
        }

        return $instance;
    }

    public static function acceptAll(): self
    {
        $instance = new self;
        $instance->acceptAll = true;

        return $instance;
    }

    /**
     * Set accept-all mode
     */
    public function setAcceptAll(bool $acceptAll): void
    {
        $this->acceptAll = $acceptAll;
    }

    /**
     * Check if accept-all mode is enabled
     */
    public function acceptsAllDirectives(): bool
    {
        return $this->acceptAll;
    }

    /**
     * Check if a directive name is known
     * In accept-all mode, always returns true
     */
    public function isDirective(string $name): bool
    {
        if ($this->acceptAll) {
            return true;
        }

        return $this->getDirective($name) !== null;
    }

    /**
     * Get all known directive names
     *
     * @return array<string, true>
     */
    public function allDirectives(): array
    {
        $all = [];
        foreach ($this->directives as $name => $directive) {
            $all[$name] = true;
        }

        return $all;
    }

    /**
     * Register a single directive programmatically
     */
    public function registerDirective(string $name): void
    {
        $name = StringInterner::lower($name);

        if (isset($this->directives[$name])) {
            return;
        }

        $directive = new DiscoveredDirective;
        $directive->name = $name;
        $directive->args = ArgumentRequirement::Optional;
        $directive->role = StructureRole::None;
        $directive->isCondition = false;
        $directive->terminators = [];

        $this->directives[$name] = $directive;
    }

    /**
     * Train from new array-based tokens with source string.
     *
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     */
    public function train(array $tokens, string $source): void
    {
        $directives = [];

        foreach ($tokens as $token) {
            if ($token['type'] === TokenType::Directive) {
                $name = StringInterner::lower(substr($source, $token['start'], $token['end'] - $token['start']));
                // Strip the @ prefix if present
                if (str_starts_with($name, '@')) {
                    $name = substr($name, 1);
                }
                $directives[$name] = true;
            }
        }

        $this->trainFromDirectiveNames($directives);
    }

    /**
     * Common training logic from directive names.
     *
     * @param  array<string, mixed>  $directives
     */
    protected function trainFromDirectiveNames(array $directives): void
    {
        foreach ($directives as $directiveName => $_) {
            // Check for possible custom condition.
            $elseName = 'else'.$directiveName;

            if (array_key_exists($elseName, $directives) && ! $this->isCondition($directiveName)) {
                $this->addConditionDirective($directiveName);
            }

            if (str_starts_with($directiveName, 'end') && strlen($directiveName) > 3) {
                $openingCandidate = StringInterner::lower(substr($directiveName, 3));

                if (array_key_exists($openingCandidate, $this->directives)) {
                    continue;
                }

                $this->addPairedDirective($openingCandidate, $directiveName);
            }
        }
    }

    public function syncLaravelDirectives(): self
    {
        $compiler = app(BladeCompiler::class);
        $customDirectives = $compiler->getCustomDirectives();

        foreach (array_keys($customDirectives) as $directiveName) {
            $this->registerDirective($directiveName);
        }

        $this->resolveBladeConditions();

        return $this;
    }

    protected function resolveBladeConditions(): void
    {
        if ($this->bladeConditions != null) {
            return;
        }

        $this->bladeConditions = array_keys((fn () => $this->conditions)->call(app(BladeCompiler::class)));

        foreach ($this->bladeConditions as $condition) {
            $this->addConditionDirective($condition);
        }
    }

    protected function addPairedDirective(string $directiveName, string $terminator): void
    {
        $open = new DiscoveredDirective;
        $open->name = $directiveName;
        $open->args = ArgumentRequirement::Optional;
        $open->role = StructureRole::Opening;
        $open->isCondition = false;
        $open->terminators = [$terminator];
        $open->terminator = $terminator;

        $close = new DiscoveredDirective;
        $close->name = $terminator;
        $close->args = ArgumentRequirement::NotAllowed;
        $close->role = StructureRole::Closing;
        $close->isCondition = false;

        $this->directives[$directiveName] = $open;
        $this->directives[$terminator] = $close;
    }

    protected function addConditionDirective(string $condition): void
    {
        $condition = StringInterner::lower($condition);
        $elseCondition = 'else'.$condition;
        $endCondition = 'end'.$condition;
        $unlessCondition = 'unless'.$condition;

        $this->conditions[$condition] = true;
        $this->conditions[$unlessCondition] = true;

        $directive = new DiscoveredDirective;
        $directive->name = $condition;
        $directive->args = ArgumentRequirement::Required;
        $directive->role = StructureRole::Opening;
        $directive->isCondition = true;
        $directive->terminators = [$elseCondition, $endCondition];

        $elseDirective = new DiscoveredDirective;
        $elseDirective->name = $elseCondition;
        $elseDirective->args = ArgumentRequirement::Required;
        $elseDirective->role = StructureRole::Mixed;
        $elseDirective->isCondition = true;
        $elseDirective->terminators = [$elseCondition, $endCondition];

        $endDirective = new DiscoveredDirective;
        $endDirective->name = $endCondition;
        $endDirective->args = ArgumentRequirement::NotAllowed;
        $endDirective->role = StructureRole::Closing;
        $endDirective->isCondition = true;

        $unlessDirective = new DiscoveredDirective;
        $unlessDirective->name = $unlessCondition;
        $unlessDirective->args = ArgumentRequirement::Required;
        $unlessDirective->role = StructureRole::Opening;
        $unlessDirective->isCondition = true;
        $unlessDirective->terminators = [$elseCondition, 'endunless'];

        $this->directives[$condition] = $directive;
        $this->directives[$elseCondition] = $elseDirective;
        $this->directives[$endCondition] = $endDirective;
        $this->directives[$unlessCondition] = $unlessDirective;
    }

    public function isCondition(string $directive): bool
    {
        return array_key_exists(StringInterner::lower($directive), $this->conditions);
    }

    public function isPaired(string $directive): bool
    {
        $directive = StringInterner::lower($directive);

        if (! array_key_exists($directive, $this->directives)) {
            return false;
        }

        $instance = $this->directives[$directive];

        return count($instance->terminators) === 1 && $instance->terminator !== null;
    }

    public function isFinalTerminator(string $directive): bool
    {
        return array_key_exists(StringInterner::lower($directive), $this->finalTerminators);
    }

    public function getTerminator(string $directive): string
    {
        $directive = StringInterner::lower($directive);

        if ($this->isFinalTerminator($directive)) {
            return $directive;
        }

        $defaultTerminator = 'end'.$directive;

        $dir = $this->directives[$directive] ?? null;
        if ($dir !== null && $dir->terminator !== null) {
            return $dir->terminator;
        }

        return $defaultTerminator;
    }

    /**
     * Get all valid terminators for a directive.
     *
     * @return string[]
     */
    public function getTerminators(string $directive): array
    {
        $directive = StringInterner::lower($directive);

        $dir = $this->directives[$directive] ?? null;

        if ($dir === null) {
            return [];
        }

        return $dir->terminators;
    }

    /**
     * @return string[]
     */
    public function getConditionTerminators(): array
    {
        $terminators = [];

        foreach ($this->directives as $directive) {
            if (! $directive->isCondition || $directive->role != StructureRole::Closing) {
                continue;
            }

            $terminators[] = StringInterner::lower($directive->name);
        }

        return $terminators;
    }

    /**
     * @return string[]
     */
    public function getBranches(string $directiveName): array
    {
        $directiveName = StringInterner::lower($directiveName);

        $directive = $this->directives[$directiveName] ?? null;

        if ($directive == null || ! $directive->isCondition) {
            return [];
        }

        return $this->getAllConditionBranches();
    }

    /**
     * @return string[]
     */
    public function getUnpairedDirectiveNames(): array
    {
        return collect($this->directives)
            ->filter(static fn (DiscoveredDirective $directive) => $directive->role == StructureRole::None)
            ->values()
            ->map(static fn (DiscoveredDirective $directive) => $directive->name)
            ->all();
    }

    public function getDirective(string $directive): ?DiscoveredDirective
    {
        $directive = StringInterner::lower($directive);

        return $this->directives[$directive] ?? null;
    }

    /**
     * @return string[]
     */
    protected function getAllConditionBranches(): array
    {
        $branches = ['else'];

        foreach ($this->directives as $directive) {
            if (! $directive->isCondition || $directive->role != StructureRole::Opening) {
                continue;
            }

            $branches = array_merge($branches, $directive->terminators);
        }

        return $branches;
    }

    public function isConditionalBranch(string $directive): bool
    {
        return in_array($directive, $this->getAllConditionBranches());
    }

    public function isBranchedDirective(string $directive): bool
    {
        $directive = StringInterner::lower($directive);

        return $this->directives[$directive]->hasConditionLikeBranches ?? false;
    }

    /**
     * Check if a directive is a switch opening directive (@switch).
     */
    public function isSwitch(string $name): bool
    {
        $directive = $this->getDirective($name);

        if ($directive === null) {
            return false;
        }

        return $directive->isSwitch ?? false;
    }

    /**
     * Get branch directives for a switch (@case, @default).
     *
     * @return string[]
     */
    public function getSwitchBranches(string $switchName): array
    {
        $switchName = StringInterner::lower($switchName);

        $directive = $this->directives[$switchName] ?? null;

        if ($directive == null) {
            return [];
        }

        $branches = [];
        foreach ($this->directives as $dir) {
            if (($dir->switchParent ?? null) === $switchName) {
                $branches[] = $dir->name;
            }
        }

        return $branches;
    }

    /**
     * Check if a directive is a switch branch (@case or @default).
     */
    public function isSwitchBranch(string $name): bool
    {
        $directive = $this->getDirective($name);

        if ($directive === null) {
            return false;
        }

        return $directive->isSwitchBranch ?? false;
    }

    /**
     * Check if a directive is a switch terminator (@break).
     */
    public function isSwitchTerminator(string $name): bool
    {
        $directive = $this->getDirective($name);

        if ($directive === null) {
            return false;
        }

        return $directive->isSwitchTerminator ?? false;
    }

    /**
     * Check if a directive supports conditional pairing (@lang, @section).
     */
    public function isConditionalPair(string $name): bool
    {
        $directive = $this->getDirective($name);

        if ($directive === null) {
            return false;
        }

        return $directive->isConditionalPair ?? false;
    }

    /**
     * Get the pairing strategy for a conditional pair directive.
     */
    public function getPairingStrategy(string $name): ?string
    {
        $directive = $this->getDirective($name);

        if ($directive === null) {
            return null;
        }

        return $directive->pairingStrategy ?? null;
    }

    /**
     * Check if a directive is a conditional close (@endlang, etc.).
     */
    public function isConditionalClose(string $name): bool
    {
        $directive = $this->getDirective($name);

        if ($directive === null) {
            return false;
        }

        return $directive->isConditionalClose ?? false;
    }

    /**
     * @param  array<string, mixed>|array<mixed, mixed>  $meta
     */
    protected function getArgumentRequirement(array $meta): ArgumentRequirement
    {
        $args = $meta['args'] ?? null;

        if ($args === null) {
            return ArgumentRequirement::Optional;
        } elseif ($args === true) {
            return ArgumentRequirement::Required;
        } elseif ($args === false) {
            return ArgumentRequirement::NotAllowed;
        }

        if (! is_array($args)) {
            return ArgumentRequirement::Optional;
        }

        $argsAllowed = $args['allowed'] ?? false;
        $argsRequired = $args['required'] ?? false;

        if (! $argsAllowed) {
            return ArgumentRequirement::NotAllowed;
        }

        if ($argsRequired) {
            return ArgumentRequirement::Required;
        }

        return ArgumentRequirement::Optional;
    }

    protected function getStructureRole(string $role): StructureRole
    {
        return match ($role) {
            'open', 'conditional_pair' => StructureRole::Opening,
            'close', 'conditional_close' => StructureRole::Closing,
            'mixed' => StructureRole::Mixed,
            default => StructureRole::None,
        };
    }

    protected function addDirective(DiscoveredDirective $directive): void
    {
        $directiveName = StringInterner::lower($directive->name);

        if ($directive->role == StructureRole::Closing && count($directive->terminators) == 0) {
            $this->finalTerminators[$directiveName] = 1;
        }

        $this->directives[$directiveName] = $directive;

        if ($directive->isCondition) {
            $this->conditions[$directiveName] = true;
        }
    }

    /**
     * @return string[]
     */
    protected function parseTerminators(string $terminators): array
    {
        $parts = explode(',', $terminators);
        $out = [];
        foreach ($parts as $terminator) {
            $t = StringInterner::lower(trim($terminator));
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }

    public function loadJson(string $contents): void
    {
        $data = json_decode($contents, true);

        if (! is_array($data)) {
            return;
        }

        foreach ($data as $meta) {
            if (! is_array($meta)) {
                continue;
            }
            $argRequirement = $this->getArgumentRequirement($meta);
            $nameField = $meta['name'] ?? '';
            $namesRaw = is_string($nameField) ? $nameField : '';
            $names = array_filter(array_map(
                trim(...),
                explode(',', $namesRaw)
            ));

            $structureRaw = $meta['structure'] ?? null;
            $structure = is_array($structureRaw) ? $structureRaw : null;

            $structuralRole = StructureRole::None;
            $isCondition = false;
            $terminators = [];
            $conditionLikeBranches = [];
            $hasConditionLikeBranches = false;
            $terminator = null;
            $isSwitch = false;
            $switchParent = null;
            $isSwitchBranch = false;
            $isSwitchTerminator = false;
            $isConditionalPair = false;
            $pairingStrategy = null;
            $isConditionalClose = false;

            if ($structure != null) {
                $roleVal = $structure['role'] ?? '';
                $structuralRole = $this->getStructureRole(is_string($roleVal) ? $roleVal : '');
                $isCondition = (bool) ($structure['condition'] ?? false);

                $termsVal = $structure['terminators'] ?? '';
                $branchesVal = $structure['branches'] ?? '';
                $terminators = $this->parseTerminators(is_string($termsVal) ? $termsVal : '');
                $conditionLikeBranches = $this->parseTerminators(is_string($branchesVal) ? $branchesVal : '');
                $hasConditionLikeBranches = count($conditionLikeBranches) > 0;

                if (count($terminators) > 0) {
                    $terminator = $terminators[array_key_last($terminators)];
                }

                $typeVal = $structure['type'] ?? null;
                if ($typeVal === 'switch') {
                    $isSwitch = true;
                }

                $parentVal = $structure['parent'] ?? null;
                if (is_string($parentVal)) {
                    $switchParent = StringInterner::lower($parentVal);
                }

                if ($roleVal === 'branch' && $switchParent === 'switch') {
                    $isSwitchBranch = true;
                }

                if ($roleVal === 'branch_terminator' && $switchParent === 'switch') {
                    $isSwitchTerminator = true;
                }

                if ($roleVal === 'conditional_pair') {
                    $isConditionalPair = true;
                    $strategyVal = $structure['pairing_strategy'] ?? null;
                    $pairingStrategy = is_string($strategyVal) ? $strategyVal : null;
                }

                if ($roleVal === 'conditional_close') {
                    $isConditionalClose = true;
                }
            }

            foreach ($names as $name) {
                $directive = new DiscoveredDirective;
                $directive->name = $name;
                $directive->args = $argRequirement;
                $directive->role = $structuralRole;
                $directive->isCondition = $isCondition;

                $directive->terminators = $terminators;
                $directive->conditionLikeBranches = $conditionLikeBranches;
                $directive->hasConditionLikeBranches = $hasConditionLikeBranches;
                $directive->terminator = $terminator;

                $directive->isSwitch = $isSwitch;
                $directive->switchParent = $switchParent;
                $directive->isSwitchBranch = $isSwitchBranch;
                $directive->isSwitchTerminator = $isSwitchTerminator;

                $directive->isConditionalPair = $isConditionalPair;
                $directive->pairingStrategy = $pairingStrategy;
                $directive->isConditionalClose = $isConditionalClose;

                $this->addDirective($directive);
            }
        }
    }

    public function loadDirectory(string $path): static
    {
        foreach (new DirectoryIterator($path) as $file) {
            if ($file->isDir() || $file->getExtension() != 'json') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }
            $this->loadJson($contents);
        }

        return $this;
    }

    /**
     * @return array<string, array{name: string, role: string, is_condition: bool, args: string, terminators: array<string>}>
     */
    public function getDirectiveMetadata(): array
    {
        return array_map(fn ($directive) => [
            'name' => $directive->name,
            'role' => match ($directive->role) {
                StructureRole::Opening => 'open',
                StructureRole::Closing => 'close',
                StructureRole::Mixed => 'mixed',
                StructureRole::None => 'none',
                StructureRole::Intermediate => 'intermediate',
            },
            'is_condition' => $directive->isCondition,
            'args' => match ($directive->args) {
                ArgumentRequirement::Required => 'required',
                ArgumentRequirement::Optional => 'optional',
                ArgumentRequirement::NotAllowed => 'not_allowed',
            },
            'terminators' => $directive->terminators,
        ], $this->directives);
    }
}
