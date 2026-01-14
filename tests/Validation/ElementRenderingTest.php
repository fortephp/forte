<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Elements Rendering', function (): void {
    it('renders simple element exactly', function (): void {
        $source = '<div></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->render())->toBe($source);
    });

    it('renders element with single quoted attributes exactly', function (): void {
        $source = "<div class='test' id='main'></div>";
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders element with double quoted attributes exactly', function (): void {
        $source = '<div class="test" id="main"></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders element with unquoted attributes exactly', function (): void {
        $source = '<div class=test></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders element with whitespace variations exactly', function (): void {
        $source = '<div class   =   "test"  id="main"></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders self-closing element exactly', function (): void {
        $source = '<br />';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders void element without closing tag exactly', function (): void {
        $source = '<br>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders nested elements exactly', function (): void {
        $source = '<div><span>Hello</span></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders bound attributes exactly', function (): void {
        $source = '<div :class="active"></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders boolean attributes exactly', function (): void {
        $source = '<input disabled required>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders empty attribute value exactly', function (): void {
        $source = '<div class=""></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    it('renders complex HTML structure exactly', function (): void {
        $source = '<div class="container" id="main"><h1>Title</h1><p>Content</p></div>';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $element = $children[0]->asElement();
        expect($children)->toHaveCount(1)
            ->and($element->render())->toBe($source);
    });

    test('paired element with attributes and nested children matches source', function (): void {
        $template = '<div class="a b">Hello</div>';
        $doc = $this->parse($template);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $div = $children[0]->asElement();

        expect($div->tagName()->staticText())->toBe('div')
            ->and($div->render())->toBe($div->getDocumentContent())
            ->and($div->isPaired())->toBeTrue();

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1)
            ->and($divChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($divChildren[0]->getDocumentContent())->toBe('Hello')
            ->and($div->attributes()->has('class'))->toBeTrue()
            ->and($div->attributes()->get('class')->valueText())->toBe('a b')
            ->and($doc->render())->toBe($template);
    });

    test('self-closing element matches source', function (): void {
        $html = '<input type="text"/>';
        $doc = $this->parse($html);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $input = $children[0]->asElement();

        expect($input->tagName()->staticText())->toBe('input')
            ->and($input->isSelfClosing())->toBeTrue()
            ->and($input->isPaired())->toBeFalse()
            ->and($input->render())->toBe($input->getDocumentContent())
            ->and($input->attributes()->has('type'))->toBeTrue()
            ->and($input->attributes()->get('type')->valueText())->toBe('text')
            ->and($doc->render())->toBe($html);
    });

    test('void element without slash matches source', function (): void {
        $html = '<img src="a.jpg">';
        $doc = $this->parse($html);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $img = $children[0]->asElement();

        expect($img->tagName()->staticText())->toBe('img')
            ->and($img->isSelfClosing())->toBeFalse()
            ->and($img->isPaired())->toBeFalse()
            ->and($img->render())->toBe($img->getDocumentContent())
            ->and($img->attributes()->has('src'))->toBeTrue()
            ->and($img->attributes()->get('src')->valueText())->toBe('a.jpg')
            ->and($doc->render())->toBe($html);
    });

    test('element with generic type arguments matches source', function (): void {
        $html = '<List<User> items="[]"></List>';
        $doc = $this->parse($html);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $el = $children[0]->asElement();

        expect($el->tagName()->staticText())->toBe('List')
            ->and($el->isPaired())->toBeTrue()
            ->and($el->render())->toBe($el->getDocumentContent())
            ->and($el->attributes()->has('items'))->toBeTrue()
            ->and($el->attributes()->get('items')->valueText())->toBe('[]')
            ->and($doc->render())->toBe($html);
    });
});
