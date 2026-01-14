<?php

declare(strict_types=1);

namespace Forte\Ast;

use Forte\Ast\Concerns\HasDirectiveName;
use Forte\Ast\Elements\Attribute;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\StructureRole;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
class DirectiveNode extends Node
{
    use HasDirectiveName;

    /**
     * Get the directive arguments, if available.
     */
    public function arguments(): ?string
    {
        $flat = $this->flat();

        return $flat['args'] ?? null;
    }

    /**
     * Check if this directive has arguments.
     */
    public function hasArguments(): bool
    {
        return $this->arguments() !== null;
    }

    /**
     * Get the whitespace between directive name and arguments, or null if none.
     */
    public function whitespaceBetweenNameAndArgs(): ?string
    {
        $flat = $this->flat();
        $tokenStart = $flat['tokenStart'];
        $tokenCount = $flat['tokenCount'];

        if ($tokenCount >= 3) {
            $whitespaceToken = $this->document->getToken($tokenStart + 1);
            if ($whitespaceToken['type'] === TokenType::Whitespace) {
                return $this->document->getSourceSlice($whitespaceToken['start'], $whitespaceToken['end']);
            }
        }

        return null;
    }

    /**
     * Get all child nodes including Attributes and whitespace.
     *
     * @return iterable<Node|Attribute>
     */
    public function children(): iterable
    {
        $flat = $this->flat();
        $childIdx = $flat['firstChild'] ?? -1;

        while ($childIdx !== -1) {
            $childFlat = $this->document->getFlatNode($childIdx);
            $kind = $childFlat['kind'];

            if ($kind === NodeKind::Attribute) {
                yield new Attribute($this->document, $childIdx);
            } else {
                yield $this->document->getNode($childIdx);
            }

            $childIdx = $childFlat['nextSibling'] ?? -1;
        }
    }

    protected function renderComposed(): string
    {
        $result = '@'.$this->nameText();

        $args = $this->arguments();
        if ($args !== null) {
            $ws = $this->whitespaceBetweenNameAndArgs() ?? '';
            $result .= $ws.$args;
        }

        foreach ($this->children() as $child) {
            $result .= $child->render();
        }

        return $result;
    }

    /**
     * Get the structural role of this directive within a block.
     */
    public function role(): StructureRole
    {
        return $this->flat()['role'] ?? StructureRole::None;
    }

    /**
     * Check if this is an opening directive (starts a block).
     */
    public function isOpening(): bool
    {
        return $this->role() === StructureRole::Opening;
    }

    /**
     * Check if this is an intermediate directive (branch within a block).
     */
    public function isIntermediate(): bool
    {
        return $this->role() === StructureRole::Intermediate;
    }

    /**
     * Check if this is a closing directive (ends a block).
     */
    public function isClosing(): bool
    {
        return $this->role() === StructureRole::Closing;
    }

    /**
     * Check if this is a standalone directive (not part of a block).
     */
    public function isStandalone(): bool
    {
        return $this->role() === StructureRole::None;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['type'] = 'directive';
        $data['name'] = $this->nameText();
        $data['original_name'] = $this->name();
        $data['arguments'] = $this->arguments();
        $data['has_arguments'] = $this->hasArguments();
        $data['role'] = $this->role()->name;
        $data['is_opening'] = $this->isOpening();
        $data['is_intermediate'] = $this->isIntermediate();
        $data['is_closing'] = $this->isClosing();
        $data['is_standalone'] = $this->isStandalone();

        return $data;
    }
}
