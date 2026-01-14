<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\TextNode;

describe('Directive Scope Isolation with Split HTML Elements', function (): void {
    it('handles opening element in one directive and closing in another', function (): void {
        $template = <<<'BLADE'
@if ($thing)
    <div>
        <p>Hello, world.</p>
@endif

<p>Content.</p>

@if ($thing)
    </div>
@endif
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(5)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->nameText())->toBe('if');

        $firstIfChildren = $nodes[0]->getChildren();
        expect($firstIfChildren)->toHaveCount(2)
            ->and($firstIfChildren[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($firstIfChildren[0]->asDirective()->nameText())->toBe('if')
            ->and($firstIfChildren[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($firstIfChildren[1]->asDirective()->nameText())->toBe('endif');

        $ifContent = $firstIfChildren[0]->getChildren();
        expect($ifContent)->toHaveCount(2)
            ->and($ifContent[0])->toBeInstanceOf(TextNode::class)
            ->and($ifContent[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($ifContent[1]->asElement()->tagNameText())->toBe('div')
            ->and($ifContent[1]->asElement()->isPaired())->toBeFalse();

        $divChildren = $ifContent[1]->getChildren();
        expect($divChildren)->toHaveCount(3)
            ->and($divChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[1]->asElement()->tagNameText())->toBe('p')
            ->and($divChildren[1]->asElement()->isPaired())->toBeTrue()
            ->and($divChildren[2])->toBeInstanceOf(TextNode::class);

        $pChildren = $divChildren[1]->getChildren();
        expect($pChildren)->toHaveCount(1)
            ->and($pChildren[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $pChildren[0]->asText()->getContent()))->toBe('Hello, world.')
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($nodes[2]->asElement()->tagNameText())->toBe('p')
            ->and($nodes[2]->asElement()->isPaired())->toBeTrue();

        $contentPChildren = $nodes[2]->getChildren();
        expect($contentPChildren)->toHaveCount(1)
            ->and(trim((string) $contentPChildren[0]->getContent()))->toBe('Content.')
            ->and($nodes[3])->toBeInstanceOf(TextNode::class)
            ->and($nodes[4]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[4]->asDirectiveBlock()->nameText())->toBe('if');

        $secondIfChildren = $nodes[4]->getChildren();
        expect($secondIfChildren)->toHaveCount(2)
            ->and($secondIfChildren[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($secondIfChildren[0]->asDirective()->nameText())->toBe('if')
            ->and($secondIfChildren[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($secondIfChildren[1]->asDirective()->nameText())->toBe('endif');

        $secondIfContent = $secondIfChildren[0]->getChildren();
        expect($secondIfContent)->toHaveCount(3)
            ->and($secondIfContent[0])->toBeInstanceOf(TextNode::class)
            ->and($secondIfContent[1]->asStrayClosingTag())->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($secondIfContent[1]->asStrayClosingTag()->tagNameText())->toBe('div')
            ->and($secondIfContent[2])->toBeInstanceOf(TextNode::class);
    });

    test('it preserves structure correctly', function (): void {
        $template = <<<'BLADE'
@if ($thing)
    <div>
        <p>Hello, world.</p>
@endif

<p>Content.</p>

@if ($thing)
    </div>
@endif
BLADE;

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('handles multiple split elements across directives', function (): void {
        $template = <<<'BLADE'
@if ($a)
    <section>
        <div>
@endif
            <p>Middle content</p>
@if ($b)
        </div>
    </section>
@endif
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(5)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $firstIfContent = $nodes[0]->getChildren()[0]->getChildren();
        expect($firstIfContent)->toHaveCount(2)
            ->and($firstIfContent[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($firstIfContent[1]->asElement()->tagNameText())->toBe('section')
            ->and($firstIfContent[1]->asElement()->isPaired())->toBeFalse();

        $sectionChildren = $firstIfContent[1]->getChildren();
        expect($sectionChildren)->toHaveCount(2)
            ->and($sectionChildren[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($sectionChildren[1]->asElement()->tagNameText())->toBe('div')
            ->and($sectionChildren[1]->asElement()->isPaired())->toBeFalse()
            ->and($nodes[4])->toBeInstanceOf(DirectiveBlockNode::class);

        $secondIfContent = $nodes[4]->getChildren()[0]->getChildren();
        expect($secondIfContent)->toHaveCount(5)
            ->and($secondIfContent[1]->asStrayClosingTag())->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($secondIfContent[1]->asStrayClosingTag()->tagNameText())->toBe('div')
            ->and($secondIfContent[3]->asStrayClosingTag())->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($secondIfContent[3]->asStrayClosingTag()->tagNameText())->toBe('section')
            ->and($secondIfContent[4])->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($template);
    });

    it('isolates HTML scope within foreach directive', function (): void {
        $template = <<<'BLADE'
@foreach ($items as $item)
    <li>
        {{ $item->name }}
@endforeach
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->asDirectiveBlock()->nameText())->toBe('foreach');

        $foreachChildren = $nodes[0]->getChildren();
        expect($foreachChildren)->toHaveCount(2)
            ->and($foreachChildren[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($foreachChildren[0]->asDirective()->nameText())->toBe('foreach')
            ->and($foreachChildren[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($foreachChildren[1]->asDirective()->nameText())->toBe('endforeach');

        $foreachContent = $foreachChildren[0]->getChildren();
        expect($foreachContent)->toHaveCount(2)
            ->and($foreachContent[0])->toBeInstanceOf(TextNode::class)
            ->and($foreachContent[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($foreachContent[1]->asElement()->tagNameText())->toBe('li')
            ->and($foreachContent[1]->asElement()->isPaired())->toBeFalse()
            ->and($doc->render())->toBe($template);
    });

    it('handles table structure split across forelse branches', function (): void {
        $template = <<<'BLADE'
@forelse ($rows as $row)
    <tr>
        <td>{{ $row->value }}</td>
    </tr>
@empty
    <tr>
        <td colspan="5">No data</td>
    </tr>
@endforelse
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->asDirectiveBlock()->nameText())->toBe('forelse')
            ->and($nodes[0]->asDirectiveBlock()->hasIntermediates())->toBeTrue();

        $forelseChildren = $nodes[0]->getChildren();
        expect($forelseChildren)->toHaveCount(3)
            ->and($forelseChildren[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($forelseChildren[0]->asDirective()->nameText())->toBe('forelse')
            ->and($forelseChildren[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($forelseChildren[1]->asDirective()->nameText())->toBe('empty')
            ->and($forelseChildren[2]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($forelseChildren[2]->asDirective()->nameText())->toBe('endforelse');

        $forelseContent = $forelseChildren[0]->getChildren();
        expect($forelseContent)->toHaveCount(3)
            ->and($forelseContent[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($forelseContent[1]->asElement()->tagNameText())->toBe('tr')
            ->and($forelseContent[1]->asElement()->isPaired())->toBeTrue()
            ->and($forelseContent[2])->toBeInstanceOf(TextNode::class);

        $emptyContent = $forelseChildren[1]->getChildren();
        expect($emptyContent)->toHaveCount(3)
            ->and($emptyContent[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($emptyContent[1]->asElement()->tagNameText())->toBe('tr')
            ->and($emptyContent[1]->asElement()->isPaired())->toBeTrue()
            ->and($emptyContent[2])->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($template);
    });

    it('handles nested directives with split elements', function (): void {
        $template = <<<'BLADE'
@if ($outer)
    <div class="outer">
        @if ($inner)
            <span>
        @endif
        Content
        @if ($inner)
            </span>
        @endif
    </div>
@endif
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->nameText())->toBe('if');

        $outerIfContent = $nodes[0]->getChildren()[0]->getChildren();
        expect($outerIfContent)->toHaveCount(3)
            ->and($outerIfContent[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($outerIfContent[1]->asElement()->tagNameText())->toBe('div')
            ->and($outerIfContent[1]->asElement()->isPaired())->toBeTrue()
            ->and($outerIfContent[2])->toBeInstanceOf(TextNode::class);

        $divChildren = $outerIfContent[1]->getChildren();
        expect($divChildren)->toHaveCount(5)
            ->and($divChildren[1])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($divChildren[3])->toBeInstanceOf(DirectiveBlockNode::class);

        $innerIf1Content = $divChildren[1]->getChildren()[0]->getChildren();
        expect($innerIf1Content)->toHaveCount(2)
            ->and($innerIf1Content[1]->asElement())->toBeInstanceOf(ElementNode::class)
            ->and($innerIf1Content[1]->asElement()->tagNameText())->toBe('span')
            ->and($innerIf1Content[1]->asElement()->isPaired())->toBeFalse();

        $innerIf2Content = $divChildren[3]->getChildren()[0]->getChildren();
        expect($innerIf2Content)->toHaveCount(3)
            ->and($innerIf2Content[1]->asStrayClosingTag())->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($innerIf2Content[1]->asStrayClosingTag()->tagNameText())->toBe('span')
            ->and($innerIf2Content[2])->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($template);
    });
});
