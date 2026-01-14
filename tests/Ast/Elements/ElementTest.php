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
            ->and($el->attributes()->has('class'))->toBeTrue()
            ->and($el->attributes()->has('id'))->toBeTrue()
            ->and($el->attributes()->get('class')->valueText())->toBe('test')
            ->and($el->attributes()->get('id')->valueText())->toBe('main');
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
