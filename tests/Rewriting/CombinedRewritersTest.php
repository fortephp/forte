<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\AttributeDirectiveCoordinator;
use Forte\Enclaves\Rewriters\ConditionalAttributesRewriter;
use Forte\Enclaves\Rewriters\ForeachAttributeRewriter;
use Forte\Enclaves\Rewriters\ForelseAttributeRewriter;
use Forte\Rewriting\Rewriter;

function assertNestingOrder(string $rendered, string $outer_open, string $inner_open, string $inner_close, string $outer_close): void
{
    $positions = [
        $outer_open => strpos($rendered, $outer_open),
        $inner_open => strpos($rendered, $inner_open),
        $inner_close => strpos($rendered, $inner_close),
        $outer_close => strpos($rendered, $outer_close),
    ];

    foreach ($positions as $token => $pos) {
        expect($pos)->not->toBeFalse("Expected '{$token}' to be present in: {$rendered}");
    }

    expect($positions[$outer_open])->toBeLessThan($positions[$inner_open])
        ->and($positions[$inner_open])->toBeLessThan($positions[$inner_close])
        ->and($positions[$inner_close])->toBeLessThan($positions[$outer_close]);
}

function makeCoordinator(?AttributeDirectiveCoordinator $coordinator = null): AttributeDirectiveCoordinator
{
    $coordinator ??= new AttributeDirectiveCoordinator;
    $coordinator->addDirective(new ConditionalAttributesRewriter);
    $coordinator->addDirective(new ForeachAttributeRewriter);
    $coordinator->addDirective(new ForelseAttributeRewriter);

    return $coordinator;
}

describe('Combined Rewriters', function (): void {
    describe('#foreach parent + #if child with prefixed attributes', function (): void {
        it('preserves bound attributes on both parent and child', function (): void {
            $doc = $this->parse('<ul #foreach="$items as $item" :class="$listClass"><li #if="$item->active" :class="$itemClass">{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('foreach(')
                ->and($result->render())->toContain(':class="$listClass"')
                ->and($result->render())->toContain('<?php if($item->active): ?>')
                ->and($result->render())->toContain(':class="$itemClass"')
                ->and($result->render())->toContain('endforeach;');
        });

        it('preserves escaped and shorthand attributes through nested rewriters', function (): void {
            $doc = $this->parse('<div #foreach="$groups as $group" ::style="raw"><span #foreach="$group->items as $item" :$item>{{ $item }}</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());

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
            $rewriter->addVisitor(makeCoordinator());

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('foreach(')
                ->and($result->render())->toContain(':class="$activeClass"')
                ->and($result->render())->toContain(':class="$inactiveClass"')
                ->and($result->render())->toContain('endforeach;');
        });
    });

    describe('#foreach + #if on the same element (attribute-order nesting)', function (): void {
        it('nests #if outermost when #if is leftmost attribute', function (): void {
            $doc = $this->parse('<ul #if="$show" #foreach="$items as $item"><li>{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('foreach(')
                ->toContain('<?php if($show): ?>')
                ->toContain('<?php endif; ?>')
                ->toContain('endforeach;')
                ->not->toContain('#if=')
                ->not->toContain('#foreach=');

            assertNestingOrder($rendered, '<?php if(', '<?php $__currentLoopData', 'endforeach;', 'endif;');
        });

        it('nests #foreach outermost when #foreach is leftmost attribute', function (): void {
            $doc = $this->parse('<ul #foreach="$items as $item" #if="$show"><li>{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('foreach(')
                ->toContain('<?php if($show): ?>')
                ->toContain('<?php endif; ?>')
                ->toContain('endforeach;')
                ->not->toContain('#if=')
                ->not->toContain('#foreach=');

            assertNestingOrder($rendered, '<?php $__currentLoopData', '<?php if(', 'endif;', 'endforeach;');
        });

        it('attribute order determines nesting regardless of directive registration order', function (): void {
            $template = '<ul #if="$show" #foreach="$items as $item"><li>{{ $item }}</li></ul>';

            $coordA = new AttributeDirectiveCoordinator;
            $coordA->addDirective(new ForeachAttributeRewriter);
            $coordA->addDirective(new ConditionalAttributesRewriter);

            $docA = $this->parse($template);
            $rA = new Rewriter;
            $rA->addVisitor($coordA);
            $renderedA = $rA->rewrite($docA)->render();

            $coordB = new AttributeDirectiveCoordinator;
            $coordB->addDirective(new ConditionalAttributesRewriter);
            $coordB->addDirective(new ForeachAttributeRewriter);

            $docB = $this->parse($template);
            $rB = new Rewriter;
            $rB->addVisitor($coordB);
            $renderedB = $rB->rewrite($docB)->render();

            foreach ([$renderedA, $renderedB] as $rendered) {
                assertNestingOrder($rendered, '<?php if(', '<?php $__currentLoopData', 'endforeach;', 'endif;');
            }
        });

        it('preserves bound attributes when #foreach and #if are on the same element', function (): void {
            $doc = $this->parse('<ul #if="$show" #foreach="$items as $item" :class="$listClass"><li>{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());

            $result = $rewriter->rewrite($doc);
            $rendered = $result->render();

            expect($rendered)
                ->toContain('foreach(')
                ->and($rendered)->toContain('<?php if($show): ?>')
                ->and($rendered)->toContain(':class="$listClass"')
                ->and($rendered)->not->toContain('#if=')
                ->and($rendered)->not->toContain('#foreach=');
        });
    });

    describe('#forelse + #if on the same element', function (): void {
        it('produces correct nesting with forelse + empty branch', function (): void {
            $template = '<li #forelse="$items as $item" #if="$item->active">{{ $item }}</li><li #empty>None</li>';

            $doc = $this->parse($template);
            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('foreach(')
                ->toContain('<?php if($item->active): ?>')
                ->toContain('<?php endif; ?>')
                ->toContain('endforeach;');

            assertNestingOrder($rendered, '$__empty_', '<?php if($item->active):', 'endif;', 'endforeach;');
        });

        it('preserves bound attributes with forelse + if on same element', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item" #if="$item->highlight" :class="$highlight">{{ $item }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());

            $result = $rewriter->rewrite($doc);
            $rendered = $result->render();

            expect($rendered)
                ->toContain(':class="$highlight"')
                ->and($rendered)->toContain('foreach(')
                ->and($rendered)->toContain('<?php if($item->highlight): ?>')
                ->and($rendered)->not->toContain('#forelse=')
                ->and($rendered)->not->toContain('#if=');
        });

        it('handles if leftmost with forelse on same element', function (): void {
            $template = '<li #if="$item->active" #forelse="$items as $item">{{ $item }}</li><li #empty>None</li>';

            $doc = $this->parse($template);
            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('foreach(')
                ->toContain('<?php if($item->active): ?>')
                ->toContain('<?php endif; ?>')
                ->not->toContain('#if=')
                ->not->toContain('#forelse=');
        });
    });

    describe('duplicate directive names on same element', function (): void {
        it('preserves distinct conditions when multiple #if attributes exist on same element', function (): void {
            $doc = $this->parse('<ul #if="5 > 2" #foreach="[5,2,2] as $number" #if="$number > 2"><li>{{ $number }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('<?php if(5 > 2): ?>')
                ->toContain('<?php if($number > 2): ?>')
                ->toContain('foreach(')
                ->not->toContain('#if=')
                ->not->toContain('#foreach=');

            $outerIfPos = strpos($rendered, '<?php if(5 > 2):');
            $foreachPos = strpos($rendered, '<?php $__currentLoopData');
            $innerIfPos = strpos($rendered, '<?php if($number > 2):');
            $innerEndifPos = strpos($rendered, '<?php endif; ?>');
            $endforeachPos = strpos($rendered, 'endforeach;');
            $outerEndifPos = strrpos($rendered, '<?php endif; ?>');

            expect($outerIfPos)->toBeLessThan($foreachPos)
                ->and($foreachPos)->toBeLessThan($innerIfPos)
                ->and($innerIfPos)->toBeLessThan($innerEndifPos)
                ->and($innerEndifPos)->toBeLessThan($endforeachPos)
                ->and($endforeachPos)->toBeLessThan($outerEndifPos);
        });

        it('only leftmost #if connects to else-branch siblings', function (): void {
            $doc = $this->parse('<ul #if="5> 2" #foreach="[5,2,2] as $number" #if="$number > 2"><li>{{ $number }}</li></ul><p #else-if="true == true">Whazzup</p><div #else>Hi</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('<?php if(5> 2): ?>')
                ->toContain('<?php if($number > 2): ?>')
                ->toContain('<?php endif; ?>')
                ->toContain('<?php elseif(true == true): ?>')
                ->toContain('<?php else: ?>')
                ->not->toContain('#if=')
                ->not->toContain('#foreach=')
                ->not->toContain('#else-if=')
                ->not->toContain('#else');

            $foreachPos = strpos($rendered, '<?php $__currentLoopData');
            $innerIfPos = strpos($rendered, '<?php if($number > 2):');
            $innerEndifPos = strpos($rendered, '<?php endif; ?>');
            $endforeachPos = strpos($rendered, 'endforeach;');
            $elseIfPos = strpos($rendered, '<?php elseif(true == true):');
            $elsePos = strpos($rendered, '<?php else:');

            expect($foreachPos)->toBeLessThan($innerIfPos)
                ->and($innerIfPos)->toBeLessThan($innerEndifPos)
                ->and($innerEndifPos)->toBeLessThan($endforeachPos)
                ->and($endforeachPos)->toBeLessThan($elseIfPos)
                ->and($elseIfPos)->toBeLessThan($elsePos);
        });
    });

    describe('#forelse + #if with prefixed attributes', function (): void {
        it('preserves attributes through forelse and conditional rewriters', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item"><span #if="$item->highlight" :class="$highlight">{{ $item }}</span></li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('foreach(')
                ->and($result->render())->toContain(':class="$highlight"')
                ->and($result->render())->toContain('endforeach;');
        });
    });

    describe('#if/#else across sibling elements', function (): void {
        it('wraps if/else chain correctly across different element types', function (): void {
            $doc = $this->parse('<div #if="count($thing) > 5">abc</div><p #else> other </p>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('<?php if(count($thing) > 5): ?>')
                ->toContain('<?php else: ?>')
                ->toContain('<?php endif; ?>')
                ->not->toContain('#if=')
                ->not->toContain('#else');

            $ifPos = strpos($rendered, '<?php if(');
            $divPos = strpos($rendered, '<div>');
            $elsePos = strpos($rendered, '<?php else:');
            $pPos = strpos($rendered, '<p>');
            $endifPos = strpos($rendered, '<?php endif;');

            expect($ifPos)->toBeLessThan($divPos)
                ->and($divPos)->toBeLessThan($elsePos)
                ->and($elsePos)->toBeLessThan($pPos)
                ->and($pPos)->toBeLessThan($endifPos);
        });

        it('handles if/else-if/else chain across siblings', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('<?php if($a): ?>')
                ->toContain('<?php elseif($b): ?>')
                ->toContain('<?php else: ?>')
                ->toContain('<?php endif; ?>')
                ->not->toContain('#if=')
                ->not->toContain('#else-if=')
                ->not->toContain('#else');
        });
    });

    describe('#forelse/#empty across sibling elements', function (): void {
        it('wraps forelse/empty chain correctly', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item">{{ $item }}</li><li #empty>No items</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('$__empty_')
                ->toContain('foreach(')
                ->toContain('endforeach;')
                ->toContain('<?php endif; ?>')
                ->not->toContain('#forelse=')
                ->not->toContain('#empty');

            $forelsePos = strpos($rendered, '$__empty_');
            $emptyPos = strpos($rendered, 'endforeach;');
            $endforelsePos = strpos($rendered, '<?php endif; ?>');

            expect($forelsePos)->toBeLessThan($emptyPos)
                ->and($emptyPos)->toBeLessThan($endforelsePos);
        });

        it('handles forelse with conditional inside the loop body', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item"><span #if="$item->active">{{ $item }}</span></li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(makeCoordinator());
            $rendered = $rewriter->rewrite($doc)->render();

            expect($rendered)
                ->toContain('$__empty_')
                ->toContain('foreach(')
                ->toContain('<?php if($item->active): ?>')
                ->toContain('<?php endif; ?>')
                ->toContain('endforeach;');

            assertNestingOrder($rendered, '$__empty_', '<?php if($item->active):', '<?php endif; ?>', 'endforeach;');
        });
    });
});
