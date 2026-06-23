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

    it('keeps shared component outside conditionally split wrapper tags', function (): void {
        $template = <<<'BLADE'
<div class="relative inline-block">
    @unless ($unlinked)
        <a href="{{ $url }}">
    @endunless

    <flux:avatar src="{{ $src }}" />

    @unless ($unlinked)
        </a>
    @endunless
</div>
BLADE;

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $rootChildren = $doc->getChildren();
        expect($rootChildren)->toHaveCount(1)
            ->and($rootChildren[0])->toBeInstanceOf(ElementNode::class);

        $outerDiv = $rootChildren[0]->asElement();
        expect($outerDiv->tagNameText())->toBe('div')
            ->and($outerDiv->isPaired())->toBeTrue();

        $divChildren = $outerDiv->getChildren();
        expect($divChildren)->toHaveCount(7)
            ->and($divChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[1])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($divChildren[2])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[3])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[4])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[5])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($divChildren[6])->toBeInstanceOf(TextNode::class);

        $firstUnless = $divChildren[1]->asDirectiveBlock();
        $avatar = $divChildren[3]->asElement();
        $secondUnless = $divChildren[5]->asDirectiveBlock();

        expect($firstUnless->nameText())->toBe('unless')
            ->and($firstUnless->getParent())->toBe($outerDiv)
            ->and($avatar->tagNameText())->toBe('flux:avatar')
            ->and($avatar->isSelfClosing())->toBeTrue()
            ->and($avatar->getParent())->toBe($outerDiv)
            ->and($secondUnless->nameText())->toBe('unless')
            ->and($secondUnless->getParent())->toBe($outerDiv);

        $firstUnlessChildren = $firstUnless->getChildren();
        expect($firstUnlessChildren)->toHaveCount(2)
            ->and($firstUnlessChildren[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($firstUnlessChildren[1])->toBeInstanceOf(DirectiveNode::class);

        $firstUnlessOpen = $firstUnlessChildren[0]->asDirective();
        $firstUnlessClose = $firstUnlessChildren[1]->asDirective();

        expect($firstUnlessOpen->nameText())->toBe('unless')
            ->and($firstUnlessOpen->getParent())->toBe($firstUnless)
            ->and($firstUnlessClose->nameText())->toBe('endunless')
            ->and($firstUnlessClose->getParent())->toBe($firstUnless);

        $firstUnlessOpenChildren = $firstUnlessOpen->getChildren();
        expect($firstUnlessOpenChildren)->toHaveCount(2)
            ->and($firstUnlessOpenChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($firstUnlessOpenChildren[1])->toBeInstanceOf(ElementNode::class);

        $anchor = $firstUnlessOpenChildren[1]->asElement();
        expect($anchor->tagNameText())->toBe('a')
            ->and($anchor->isPaired())->toBeFalse()
            ->and($anchor->getParent())->toBe($firstUnlessOpen)
            ->and($anchor->getDocumentContent())->toBe('<a href="{{ $url }}">')
            ->and($anchor->getDocumentContent())->not()->toContain('<flux:avatar');

        $secondUnlessChildren = $secondUnless->getChildren();
        expect($secondUnlessChildren)->toHaveCount(2)
            ->and($secondUnlessChildren[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($secondUnlessChildren[1])->toBeInstanceOf(DirectiveNode::class);

        $secondUnlessOpen = $secondUnlessChildren[0]->asDirective();
        $secondUnlessClose = $secondUnlessChildren[1]->asDirective();

        expect($secondUnlessOpen->nameText())->toBe('unless')
            ->and($secondUnlessOpen->getParent())->toBe($secondUnless)
            ->and($secondUnlessClose->nameText())->toBe('endunless')
            ->and($secondUnlessClose->getParent())->toBe($secondUnless);

        $secondUnlessOpenChildren = $secondUnlessOpen->getChildren();
        expect($secondUnlessOpenChildren)->toHaveCount(3)
            ->and($secondUnlessOpenChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($secondUnlessOpenChildren[1])->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($secondUnlessOpenChildren[2])->toBeInstanceOf(TextNode::class);

        $strayAnchorClose = $secondUnlessOpenChildren[1]->asStrayClosingTag();
        expect($strayAnchorClose->tagNameText())->toBe('a')
            ->and($strayAnchorClose->getParent())->toBe($secondUnlessOpen)
            ->and($strayAnchorClose->getDocumentContent())->toBe('</a>');
    });

    it('keeps shared body outside alternate conditionally split wrapper tags', function (): void {
        $template = <<<'BLADE'
<div>
    @if ($linked)
        <a href="{{ $url }}">
    @else
        <button type="button">
    @endif

    <span>{{ $label }}</span>

    @if ($linked)
        </a>
    @else
        </button>
    @endif
</div>
BLADE;

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $rootChildren = $doc->getChildren();
        expect($rootChildren)->toHaveCount(1)
            ->and($rootChildren[0])->toBeInstanceOf(ElementNode::class);

        $outerDiv = $rootChildren[0]->asElement();
        expect($outerDiv->tagNameText())->toBe('div')
            ->and($outerDiv->isPaired())->toBeTrue();

        $divChildren = $outerDiv->getChildren();
        expect($divChildren)->toHaveCount(7)
            ->and($divChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[1])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($divChildren[2])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[3])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[4])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[5])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($divChildren[6])->toBeInstanceOf(TextNode::class);

        $openingIf = $divChildren[1]->asDirectiveBlock();
        $span = $divChildren[3]->asElement();
        $closingIf = $divChildren[5]->asDirectiveBlock();

        expect($openingIf->nameText())->toBe('if')
            ->and($openingIf->getParent())->toBe($outerDiv)
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeTrue()
            ->and($span->getParent())->toBe($outerDiv)
            ->and($closingIf->nameText())->toBe('if')
            ->and($closingIf->getParent())->toBe($outerDiv);

        $openingIfChildren = $openingIf->getChildren();
        expect($openingIfChildren)->toHaveCount(3)
            ->and($openingIfChildren[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($openingIfChildren[1])->toBeInstanceOf(DirectiveNode::class)
            ->and($openingIfChildren[2])->toBeInstanceOf(DirectiveNode::class);

        $ifOpen = $openingIfChildren[0]->asDirective();
        $elseBranch = $openingIfChildren[1]->asDirective();
        $ifClose = $openingIfChildren[2]->asDirective();

        expect($ifOpen->nameText())->toBe('if')
            ->and($ifOpen->getParent())->toBe($openingIf)
            ->and($elseBranch->nameText())->toBe('else')
            ->and($elseBranch->getParent())->toBe($openingIf)
            ->and($ifClose->nameText())->toBe('endif')
            ->and($ifClose->getParent())->toBe($openingIf);

        $ifOpenChildren = $ifOpen->getChildren();
        expect($ifOpenChildren)->toHaveCount(2)
            ->and($ifOpenChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($ifOpenChildren[1])->toBeInstanceOf(ElementNode::class);

        $anchor = $ifOpenChildren[1]->asElement();
        expect($anchor->tagNameText())->toBe('a')
            ->and($anchor->isPaired())->toBeFalse()
            ->and($anchor->getParent())->toBe($ifOpen)
            ->and($anchor->getDocumentContent())->toBe('<a href="{{ $url }}">')
            ->and($anchor->getDocumentContent())->not()->toContain('<span');

        $elseChildren = $elseBranch->getChildren();
        expect($elseChildren)->toHaveCount(2)
            ->and($elseChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($elseChildren[1])->toBeInstanceOf(ElementNode::class);

        $button = $elseChildren[1]->asElement();
        expect($button->tagNameText())->toBe('button')
            ->and($button->isPaired())->toBeFalse()
            ->and($button->getParent())->toBe($elseBranch)
            ->and($button->getDocumentContent())->toBe('<button type="button">')
            ->and($button->getDocumentContent())->not()->toContain('<span');

        $closingIfChildren = $closingIf->getChildren();
        expect($closingIfChildren)->toHaveCount(3)
            ->and($closingIfChildren[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($closingIfChildren[1])->toBeInstanceOf(DirectiveNode::class)
            ->and($closingIfChildren[2])->toBeInstanceOf(DirectiveNode::class);

        $closingIfOpen = $closingIfChildren[0]->asDirective();
        $closingElse = $closingIfChildren[1]->asDirective();
        $closingIfEnd = $closingIfChildren[2]->asDirective();

        expect($closingIfOpen->nameText())->toBe('if')
            ->and($closingIfOpen->getParent())->toBe($closingIf)
            ->and($closingElse->nameText())->toBe('else')
            ->and($closingElse->getParent())->toBe($closingIf)
            ->and($closingIfEnd->nameText())->toBe('endif')
            ->and($closingIfEnd->getParent())->toBe($closingIf);

        $closingIfOpenChildren = $closingIfOpen->getChildren();
        expect($closingIfOpenChildren)->toHaveCount(3)
            ->and($closingIfOpenChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($closingIfOpenChildren[1])->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($closingIfOpenChildren[2])->toBeInstanceOf(TextNode::class);

        $strayAnchorClose = $closingIfOpenChildren[1]->asStrayClosingTag();
        expect($strayAnchorClose->tagNameText())->toBe('a')
            ->and($strayAnchorClose->getParent())->toBe($closingIfOpen)
            ->and($strayAnchorClose->getDocumentContent())->toBe('</a>');

        $closingElseChildren = $closingElse->getChildren();
        expect($closingElseChildren)->toHaveCount(3)
            ->and($closingElseChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($closingElseChildren[1])->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($closingElseChildren[2])->toBeInstanceOf(TextNode::class);

        $strayButtonClose = $closingElseChildren[1]->asStrayClosingTag();
        expect($strayButtonClose->tagNameText())->toBe('button')
            ->and($strayButtonClose->getParent())->toBe($closingElse)
            ->and($strayButtonClose->getDocumentContent())->toBe('</button>');
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
