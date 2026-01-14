<?php

declare(strict_types=1);

namespace Forte\Parser\Extension;

use Forte\Parser\NodeKindRegistry;

class ExtensionStack
{
    /**
     * @var TreeExtension[]
     */
    private array $extensions = [];

    private bool $sorted = true;

    public function add(TreeExtension $extension): self
    {
        $this->extensions[] = $extension;
        $this->sorted = false;

        return $this;
    }

    /**
     * Register node kinds for all extensions.
     */
    public function registerNodeKinds(NodeKindRegistry $registry): void
    {
        foreach ($this->extensions as $extension) {
            $extension->registerNodeKinds($registry);
        }
    }

    /**
     * Check if the stack has any extensions.
     */
    public function isEmpty(): bool
    {
        return empty($this->extensions);
    }

    /**
     * Get the number of extensions.
     */
    public function count(): int
    {
        return count($this->extensions);
    }

    /**
     * Get all extensions sorted by priority (highest first).
     *
     * @return TreeExtension[]
     */
    public function all(): array
    {
        $this->ensureSorted();

        return $this->extensions;
    }

    /**
     * Try to handle the current token with an extension.
     *
     * Returns the number of tokens consumed, or 0 if no extension handled it.
     */
    public function tryHandle(TreeContext $ctx): int
    {
        $this->ensureSorted();

        foreach ($this->extensions as $extension) {
            if ($extension->canHandle($ctx)) {
                $consumed = $extension->handle($ctx);
                if ($consumed > 0) {
                    return $consumed;
                }
            }
        }

        return 0;
    }

    /**
     * Ensure extensions are sorted by priority.
     */
    protected function ensureSorted(): void
    {
        if (! $this->sorted) {
            usort($this->extensions, fn ($a, $b) => $b->priority() <=> $a->priority());
            $this->sorted = true;
        }
    }
}
