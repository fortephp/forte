<?php

declare(strict_types=1);

use Forte\Ast\EchoNode;
use Forte\Ast\Node;
use Forte\Ast\TraversalOptions;

describe('Internal Interpolation Traversal', function (): void {
    it('shows how to collect echoes from normal flow and internal element/component structures', function (): void {
        $template = <<<'BLADE'
<hello-{{ $hello }}
    data-{{ $key }}="prefix-{{ $value }}"
    @if ($this->count > 0)
        {{ $echo }}
        @if ($inner)
            aria-{{ $innerKey }}="{{ $innerValue }}"
        @endif
    @endif
>
    {{ $flowEcho }}
</hello-{{ $hello }}>

<x-alert title="{{ $componentTitle }}" data-{{ $componentKey }}="{{ $componentValue }}" />
BLADE;

        $doc = $this->parse($template);

        $normalFlowEchoes = [];
        $stack = $doc->getChildren();

        while ($stack !== []) {
            /** @var Node $node */
            $node = array_pop($stack);

            if ($node instanceof EchoNode) {
                $normalFlowEchoes[] = $node->expression();
            }

            foreach ($node->children() as $child) {
                if ($child instanceof Node) {
                    $stack[] = $child;
                }
            }
        }

        // Internal nodes are not part of normal children() traversal.
        $internalEchoes = [];

        foreach ($doc->elements as $element) {
            foreach ($element->internalEchoes() as $echo) {
                $internalEchoes[] = $echo->expression();
            }
        }

        foreach ($doc->components as $component) {
            foreach ($component->internalEchoes() as $echo) {
                $internalEchoes[] = $echo->expression();
            }
        }

        $allEchoes = array_values(array_unique(array_merge($normalFlowEchoes, $internalEchoes)));
        sort($allEchoes);
        sort($internalEchoes);

        expect($normalFlowEchoes)->toBe(['$flowEcho'])
            ->and($internalEchoes)->toBe([
                '$componentKey',
                '$componentTitle',
                '$componentValue',
                '$echo',
                '$hello',
                '$innerKey',
                '$innerValue',
                '$key',
                '$value',
            ])
            ->and($allEchoes)->toBe([
                '$componentKey',
                '$componentTitle',
                '$componentValue',
                '$echo',
                '$flowEcho',
                '$hello',
                '$innerKey',
                '$innerValue',
                '$key',
                '$value',
            ]);
    });

    it('supports allOfType() with includeInternal for one-shot typed queries', function (): void {
        $template = <<<'BLADE'
<hello-{{ $hello }}
    data-{{ $key }}="prefix-{{ $value }}"
    @if ($this->count > 0)
        {{ $echo }}
        @if ($inner)
            aria-{{ $innerKey }}="{{ $innerValue }}"
        @endif
    @endif
>
    {{ $flowEcho }}
</hello-{{ $hello }}>

<x-alert title="{{ $componentTitle }}" data-{{ $componentKey }}="{{ $componentValue }}" />
BLADE;

        $doc = $this->parse($template);

        $normalFlow = $doc->allOfType(EchoNode::class)
            ->map(fn (EchoNode $echo): string => $echo->expression())
            ->all();

        $allEchoes = $doc->allOfType(EchoNode::class, true)
            ->map(fn (EchoNode $echo): string => $echo->expression())
            ->all();

        sort($allEchoes);

        expect($normalFlow)->toBe(['$flowEcho'])
            ->and($allEchoes)->toBe([
                '$componentKey',
                '$componentTitle',
                '$componentValue',
                '$echo',
                '$flowEcho',
                '$hello',
                '$innerKey',
                '$innerValue',
                '$key',
                '$value',
            ]);
    });

    it('supports TraversalOptions::deep() for allEchoes and walk()', function (): void {
        $template = <<<'BLADE'
<hello-{{ $hello }} data-{{ $key }}="{{ $value }}">
    {{ $flowEcho }}
</hello-{{ $hello }}>
BLADE;

        $doc = $this->parse($template);

        $default = $doc->allEchoes()
            ->map(fn (EchoNode $echo): string => $echo->expression())
            ->all();

        $deep = $doc->allEchoes(TraversalOptions::deep())
            ->map(fn (EchoNode $echo): string => $echo->expression())
            ->all();

        sort($deep);

        $walked = [];
        $doc->walk(function (Node $node) use (&$walked): void {
            if ($node instanceof EchoNode) {
                $walked[] = $node->expression();
            }
        }, TraversalOptions::deep());

        sort($walked);

        expect($default)->toBe(['$flowEcho'])
            ->and($deep)->toBe(['$flowEcho', '$hello', '$key', '$value'])
            ->and($walked)->toBe(['$flowEcho', '$hello', '$key', '$value']);
    });
});
