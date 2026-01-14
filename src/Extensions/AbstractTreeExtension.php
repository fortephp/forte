<?php

declare(strict_types=1);

namespace Forte\Extensions;

use Forte\Extensions\Concerns\HasConfiguration;
use Forte\Extensions\Concerns\HasDiagnostics;
use Forte\Extensions\Concerns\ProvidesDefaultExtensionImplementation;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\NodeKindRegistry;

abstract class AbstractTreeExtension implements ForteExtension, TreeExtension
{
    use HasConfiguration;
    use HasDiagnostics;
    use ProvidesDefaultExtensionImplementation;

    /** @var int[] */
    protected array $nodeKinds = [];

    /**
     * Whether node kinds have been registered.
     */
    private bool $kindsRegistered = false;

    /**
     * Unique identifier for this extension.
     */
    abstract public function id(): string;

    /**
     * Register custom node kinds.
     */
    abstract protected function registerKinds(NodeKindRegistry $registry): void;

    /**
     * Check if this extension can handle the current token.
     */
    abstract public function canHandle(TreeContext $ctx): bool;

    /**
     * Handle the current token(s) and create appropriate nodes.
     *
     * @return int Number of tokens consumed
     */
    abstract protected function doHandle(TreeContext $ctx): int;

    public function priority(): int
    {
        return 0;
    }

    public function registerNodeKinds(NodeKindRegistry $registry): void
    {
        if (! $this->kindsRegistered) {
            $this->registerKinds($registry);
            $this->kindsRegistered = true;
        }
    }

    public function handle(TreeContext $ctx): int
    {
        return $this->doHandle($ctx);
    }

    /**
     * Register a node kind and store its ID.
     *
     * @param  class-string|null  $nodeClass
     */
    protected function registerKind(
        NodeKindRegistry $registry,
        string $name,
        ?string $nodeClass = null,
        ?string $label = null
    ): int {
        $id = $registry->register($this->id(), $name, $nodeClass, $label);
        $this->nodeKinds[] = $id;

        return $id;
    }
}
