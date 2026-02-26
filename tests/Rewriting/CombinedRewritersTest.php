<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\ConditionalAttributesRewriter;
use Forte\Enclaves\Rewriters\ForeachAttributeRewriter;
use Forte\Enclaves\Rewriters\ForelseAttributeRewriter;
use Forte\Rewriting\Rewriter;

describe('Combined Rewriters', function (): void {
    describe('#foreach parent + #if child with prefixed attributes', function (): void {
        it('preserves bound attributes on both parent and child', function (): void {
            $doc = $this->parse('<ul #foreach="$items as $item" :class="$listClass"><li #if="$item->active" :class="$itemClass">{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('@foreach($items as $item)')
                ->and($result->render())->toContain(':class="$listClass"')
                ->and($result->render())->toContain('@if($item->active)')
                ->and($result->render())->toContain(':class="$itemClass"')
                ->and($result->render())->toContain('@endforeach');
        });

        it('preserves escaped and shorthand attributes through nested rewriters', function (): void {
            $doc = $this->parse('<div #foreach="$groups as $group" ::style="raw"><span #foreach="$group->items as $item" :$item>{{ $item }}</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('::style="raw"')
                ->and($result->render())->toContain(':$item');
        });
    });

    describe('#foreach + #if + #else chain with prefixed attributes', function (): void {
        it('preserves bound attributes across conditional chain inside foreach', function (): void {
            $doc = $this->parse('<div #foreach="$items as $item"><span #if="$item->active" :class="$activeClass">active</span><span #else :class="$inactiveClass">inactive</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('@foreach($items as $item)')
                ->and($result->render())->toContain(':class="$activeClass"')
                ->and($result->render())->toContain(':class="$inactiveClass"')
                ->and($result->render())->toContain('@endforeach');
        });
    });

    describe('#forelse + #if with prefixed attributes', function (): void {
        it('preserves attributes through forelse and conditional rewriters', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item"><span #if="$item->highlight" :class="$highlight">{{ $item }}</span></li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('@forelse($items as $item)')
                ->and($result->render())->toContain(':class="$highlight"')
                ->and($result->render())->toContain('@endforelse');
        });
    });
});
