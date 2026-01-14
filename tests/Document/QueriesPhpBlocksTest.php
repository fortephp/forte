<?php

declare(strict_types=1);

use Forte\Ast\Document\NodeCollection;
use Forte\Ast\PhpBlockNode;

describe('Document PHP Blocks', function (): void {
    it('can get all php blocks', function (): void {
        $doc = $this->parse('@php $x = 1; @endphp text @php $y = 2; @endphp');

        $phpBlocks = $doc->getPhpBlocks();

        expect($phpBlocks)->toHaveCount(2)
            ->and($phpBlocks)->toBeInstanceOf(NodeCollection::class)
            ->and($phpBlocks->first())->toBeInstanceOf(PhpBlockNode::class);
    });
});
