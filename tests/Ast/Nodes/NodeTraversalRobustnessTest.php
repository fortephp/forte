<?php

declare(strict_types=1);

use Forte\Ast\Node;

describe('Node Traversal Robustness', function (): void {
    it('handles mixed directive children without crashing descendant traversal', function (): void {
        $input = '@for>@switch<H<n>@endswitch:';
        $doc = $this->parse($input);

        expect($doc->render())->toBe($input);

        // These calls previously crashed when a DirectiveNode child was an Attribute.
        $elementCount = $doc->elements->count();
        $allNodes = $doc->allOfType(Node::class, true);
        $first = $doc->firstChild();
        $descendants = $first?->getDescendants() ?? [];

        expect($elementCount)->toBeInt()
            ->and($allNodes->count())->toBeGreaterThan(0)
            ->and($descendants)->toBeArray();
    });
});
