<?php

declare(strict_types=1);

namespace Forte\Rewriting;

use Forte\Ast\Node;
use Forte\Rewriting\Builders\NodeBuilder;

class Operation
{
    /** @var array<NodeBuilder> */
    public array $insertBefore = [];

    /** @var array<NodeBuilder> */
    public array $insertAfter = [];

    /** @var array<NodeBuilder> */
    public array $prependChildren = [];

    /** @var array<NodeBuilder> */
    public array $appendChildren = [];

    /** @var list<array{before: array<NodeBuilder>, after: array<NodeBuilder>}> */
    public array $wrapStack = [];

    /** @var array<string, string|null> */
    public array $attributeChanges = [];

    public ?string $newTagName = null;

    /**
     * @param  array<NodeBuilder>|null  $replacement
     */
    public function __construct(
        public OperationType $type,
        public Node $node,
        public ?array $replacement = null,
        public ?NodeBuilder $wrapper = null,
    ) {}
}
