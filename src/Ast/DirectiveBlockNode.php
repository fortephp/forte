<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDirectiveName;
use Illuminate\Support\LazyCollection;

/**
 * @property-read LazyCollection<int, DirectiveNode> $intermediateDirectives
 */
class DirectiveBlockNode extends Node
{
    use HasDirectiveName;

    /**
     * Get the opening directive's arguments.
     */
    public function arguments(): ?string
    {
        $flat = $this->flat();

        return $flat['args'] ?? null;
    }

    /**
     * Check if this block has intermediate directives (else, elseif, etc.)
     */
    public function hasIntermediates(): bool
    {
        $children = $this->getChildren();
        $directiveCount = 0;

        foreach ($children as $child) {
            if ($child instanceof DirectiveNode) {
                $directiveCount++;
            }
        }

        return $directiveCount > 2;
    }

    /**
     * Get the opening directive node (e.g., @foreach).
     */
    public function startDirective(): ?DirectiveNode
    {
        foreach ($this->children() as $child) {
            if ($child instanceof DirectiveNode && $child->nameText() === $this->nameText()) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Get the closing directive node (e.g., @endforeach, @show, @stop).
     */
    public function endDirective(): ?DirectiveNode
    {
        $children = $this->getChildren();
        if (count($children) < 2) {
            return null;
        }

        $last = end($children);

        if (! ($last instanceof DirectiveNode)) {
            return null;
        }

        return $last;
    }

    /**
     * Check if this is an @if block.
     */
    public function isIf(): bool
    {
        return $this->nameText() === 'if';
    }

    /**
     * Check if this is a @foreach block.
     */
    public function isForeach(): bool
    {
        return $this->nameText() === 'foreach';
    }

    /**
     * Check if this is a @forelse block.
     */
    public function isForelse(): bool
    {
        return $this->nameText() === 'forelse';
    }

    /**
     * Check if this is a @section block.
     */
    public function isSection(): bool
    {
        return $this->nameText() === 'section';
    }

    /**
     * Check if this block matches the given directive name.
     */
    public function isDirectiveNamed(string $name): bool
    {
        return strcasecmp($this->nameText(), $name) === 0;
    }

    /**
     * Check if this is a @for block.
     */
    public function isFor(): bool
    {
        return $this->nameText() === 'for';
    }

    /**
     * Check if this is a @while block.
     */
    public function isWhile(): bool
    {
        return $this->nameText() === 'while';
    }

    /**
     * Check if this is an @unless block.
     */
    public function isUnless(): bool
    {
        return $this->nameText() === 'unless';
    }

    /**
     * Check if this is a @switch block.
     */
    public function isSwitch(): bool
    {
        return $this->nameText() === 'switch';
    }

    /**
     * Check if this is a @push block.
     */
    public function isPush(): bool
    {
        return $this->nameText() === 'push';
    }

    /**
     * Check if this is a @once block.
     */
    public function isOnce(): bool
    {
        return $this->nameText() === 'once';
    }

    /**
     * Check if this is a @verbatim block.
     */
    public function isVerbatim(): bool
    {
        return $this->nameText() === 'verbatim';
    }

    /**
     * Get all intermediate directives in this block (e.g., @else, @elseif, @empty, @case, @default).
     *
     * @return iterable<DirectiveNode>
     */
    public function intermediateDirectives(): iterable
    {
        foreach ($this->children() as $child) {
            if ($child instanceof DirectiveNode && $child->isIntermediate()) {
                yield $child;
            }
        }
    }

    /**
     * @return array<DirectiveNode>
     */
    public function getIntermediateDirectives(): array
    {
        return iterator_to_array($this->intermediateDirectives());
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            // @phpstan-ignore-next-line
            'intermediateDirectives' => LazyCollection::make(fn () => $this->intermediateDirectives()),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'directive_block';
        $data['name'] = $this->nameText();
        $data['original_name'] = $this->name();
        $data['arguments'] = $this->arguments();
        $data['has_intermediates'] = $this->hasIntermediates();
        $data['is_if'] = $this->isIf();
        $data['is_foreach'] = $this->isForeach();
        $data['is_forelse'] = $this->isForelse();
        $data['is_section'] = $this->isSection();
        $data['is_for'] = $this->isFor();
        $data['is_while'] = $this->isWhile();
        $data['is_unless'] = $this->isUnless();
        $data['is_switch'] = $this->isSwitch();
        $data['is_push'] = $this->isPush();
        $data['is_once'] = $this->isOnce();
        $data['is_verbatim'] = $this->isVerbatim();

        $startDirective = $this->startDirective();
        $endDirective = $this->endDirective();

        $data['start_directive_name'] = $startDirective?->nameText();
        $data['end_directive_name'] = $endDirective?->nameText();

        $data['intermediate_directives'] = array_map(
            fn (DirectiveNode $d) => $d->nameText(),
            $this->getIntermediateDirectives()
        );

        return $data;
    }
}
