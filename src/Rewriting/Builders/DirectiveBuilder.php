<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Forte\Ast\DirectiveNode;
use Forte\Parser\NodeKind;

class DirectiveBuilder extends NodeBuilder
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $arguments = null,
        private readonly ?string $whitespaceBetweenNameAndArgs = null,
        private readonly bool $safeSpacing = false
    ) {}

    public static function fromDirective(
        DirectiveNode $directive,
        ?string $newArguments = null
    ): self {
        return new self(
            $directive->name(),
            $newArguments ?? $directive->arguments(),
            $directive->whitespaceBetweenNameAndArgs()
        );
    }

    /**
     * Create a DirectiveBuilder with safe spacing.
     */
    public static function safe(string $name, ?string $arguments = null): self
    {
        return new self($name, $arguments, null, true);
    }

    /**
     * Return a copy with safe spacing enabled.
     */
    public function withSafeSpacing(): self
    {
        return new self(
            $this->name,
            $this->arguments,
            $this->whitespaceBetweenNameAndArgs,
            true
        );
    }

    public function kind(): int
    {
        return NodeKind::Directive;
    }

    public function toSource(): string
    {
        $buffer = '@'.$this->name;

        if ($this->whitespaceBetweenNameAndArgs !== null) {
            $buffer .= $this->whitespaceBetweenNameAndArgs;
        }

        if ($this->arguments !== null) {
            $buffer .= $this->arguments;
        }

        return $buffer;
    }

    /**
     * Whether this directive needs contextual safe spacing.
     */
    public function needsLeadingSeparator(): bool
    {
        return $this->safeSpacing;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): ?string
    {
        return $this->arguments;
    }
}
