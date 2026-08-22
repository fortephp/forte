<?php

declare(strict_types=1);

use Forte\Ast\EchoNode;
use Forte\Ast\Node;
use Forte\Ast\TraversalOptions;

describe('Typed traversal cache', function (): void {
    it('returns isolated collections while reusing the same node instances', function (): void {
        $document = $this->parse('{{ $first }} {{ $second }}');

        $firstQuery = $document->allOfType(EchoNode::class);
        $expected = $firstQuery->all();

        $firstQuery->pop();
        $secondQuery = $document->allOfType(EchoNode::class);

        expect($firstQuery)->toHaveCount(1)
            ->and($secondQuery)->toHaveCount(2)
            ->and($secondQuery->all())->toBe($expected)
            ->and($secondQuery[0])->toBe($expected[0])
            ->and($secondQuery[1])->toBe($expected[1]);
    });

    it('keys cached queries by every traversal option', function (): void {
        $document = $this->parse(<<<'BLADE'
<hello-{{ $internal }}>{{ $flow }}</hello-{{ $internal }}>
BLADE);

        $default = $document->allOfType(EchoNode::class);
        $deep = $document->allOfType(EchoNode::class, true);
        $explicitDeep = $document->allOfType(EchoNode::class, TraversalOptions::deep());
        $withoutSynthetic = $document->allOfType(EchoNode::class, new TraversalOptions(
            includeInternal: true,
            includeSynthetic: false,
        ));
        $withTrivia = $document->allOfType(EchoNode::class, new TraversalOptions(
            includeInternal: true,
            includeTrivia: true,
        ));
        $depthZero = $document->allOfType(Node::class, new TraversalOptions(
            includeInternal: true,
            maxDepth: 0,
        ));
        $depthOne = $document->allOfType(Node::class, new TraversalOptions(
            includeInternal: true,
            maxDepth: 1,
        ));

        expect($default->map->expression()->all())->toBe(['$flow'])
            ->and($deep->map->expression()->all())->toBe(['$flow', '$internal'])
            ->and($explicitDeep->all())->toBe($deep->all())
            ->and($withoutSynthetic->all())->toBe($deep->all())
            ->and($withTrivia->all())->toBe($deep->all())
            ->and($depthZero)->toHaveCount(1)
            ->and($depthOne->count())->toBeGreaterThan($depthZero->count());
    });
});
