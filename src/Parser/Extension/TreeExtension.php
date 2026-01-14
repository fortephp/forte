<?php

declare(strict_types=1);

namespace Forte\Parser\Extension;

use Forte\Parser\NodeKindRegistry;

interface TreeExtension
{
    /**
     * Unique identifier for this extension.
     */
    public function name(): string;

    /**
     * Priority determines the order of extension checks.
     */
    public function priority(): int;

    /**
     * Register custom node kinds for this extension.
     *
     * @param  NodeKindRegistry  $registry  The node kind registry
     */
    public function registerNodeKinds(NodeKindRegistry $registry): void;

    /**
     * Check if this extension can handle the current token.
     *
     * @param  TreeContext  $ctx  Context for position checks
     */
    public function canHandle(TreeContext $ctx): bool;

    /**
     * Handle the current token(s) and create appropriate nodes.
     *
     * @param  TreeContext  $ctx  Full context for tree building
     */
    public function handle(TreeContext $ctx): int;
}
