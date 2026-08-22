<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Element Parsing', function (): void {
    it('parses simple opening tag', function (): void {
        $el = $this->parseElement('<div>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('div');
    });

    it('parses self-closing tag', function (): void {
        $el = $this->parseElement('<br />');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('br')
            ->and($el->isSelfClosing())->toBeTrue();
    });

    it('parses void element without closing tag', function (): void {
        $el = $this->parseElement('<br>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('br');
    });

    it('parses matched opening and closing tags', function (): void {
        $el = $this->parseElement('<div></div>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses nested elements', function (): void {
        $div = $this->parseElement('<div><span></span></div>');
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div');

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span');
    });

    it('processes element with text content', function (): void {
        $div = $this->parseElement('<div>Hello</div>');

        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1)
            ->and($divChildren[0])->toBeInstanceOf(TextNode::class);
    });

    it('processes element with attributes', function (): void {
        $el = $this->parseElement('<div class="test" id="main">');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->hasAttribute('class'))->toBeTrue()
            ->and($el->hasAnyAttribute(['missing', 'id']))->toBeTrue()
            ->and($el->hasAllAttributes(['class', 'id']))->toBeTrue()
            ->and($el->hasAllAttributes(['class', 'missing']))->toBeFalse()
            ->and($el->attribute('class')?->valueText())->toBe('test')
            ->and($el->attribute('id')?->valueText())->toBe('main');
    });

    it('offers exact case-insensitive tag and static attribute helpers', function (): void {
        $el = $this->parseElement('<DIV title="A&amp;B" :class="$classes" data-id="{{ $id }}">');

        expect($el->isTag('div'))->toBeTrue()
            ->and($el->isTag(['span', 'DIV']))->toBeTrue()
            ->and($el->isTag('span'))->toBeFalse()
            ->and($el->isAny(['span', 'D*']))->toBeTrue()
            ->and($el->staticAttributeValue('title'))->toBe('A&B')
            ->and($el->staticAttributeValue('class'))->toBeNull()
            ->and($el->staticAttributeValue('data-id'))->toBeNull();
    });

    it('returns the first duplicate attribute kept by HTML', function (): void {
        $el = $this->parseElement('<div class="first" CLASS="ignored">');

        expect($el->getAttributes())->toHaveCount(2)
            ->and($el->attribute('class')?->decodedValueText())->toBe('first')
            ->and($el->getAttribute('class'))->toBe('ignored');
    });

    it('exposes decoded static attribute values without losing source spelling', function (): void {
        $el = $this->parseElement('<div title="A&amp;B" role="but&#116;on" data-copy="&copy test" data-ambiguous="&copy=x">');

        expect($el->attributes()->get('title')->valueText())->toBe('A&amp;B')
            ->and($el->attributes()->get('title')->decodedValueText())->toBe('A&B')
            ->and($el->attributes()->get('role')->decodedValueText())->toBe('button')
            ->and($el->attributes()->get('data-copy')->decodedValueText())->toBe('© test')
            ->and($el->attributes()->get('data-ambiguous')->decodedValueText())->toBe('&copy=x');
    });

    it('applies HTML numeric character-reference replacement rules', function (): void {
        $el = $this->parseElement('<div data-value="&#0; &#x80; &#x1F642;">');

        expect($el->attributes()->get('data-value')->decodedValueText())->toBe('� € 🙂');
    });

    it('preserves unknown and ambiguous named references', function (): void {
        $el = $this->parseElement('<div data-one="&unknown;" data-two="&notit;">');

        expect($el->attributes()->get('data-one')->decodedValueText())->toBe('&unknown;')
            ->and($el->attributes()->get('data-two')->decodedValueText())->toBe('&notit;');
    });

    it('parses self-closing element correctly', function (): void {
        $el = $this->parseElement('<br />');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('br')
            ->and($el->isSelfClosing())->toBeTrue();

    });

    it('parses nested elements with children', function (): void {
        $div = $this->parseElement('<div><span>Test</span></div>');

        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div');

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span');
    });

    it('renders element and back to source', function (): void {
        $source = '<div class="test">Hello</div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();
        $el = $children[0]->asElement();

        expect($children)->toHaveCount(1)
            ->and($el->render())->toBe($source);
    });
});
