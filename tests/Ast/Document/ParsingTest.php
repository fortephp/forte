<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Document Parsing', function (): void {
    it('parses simple HTML element', function (): void {
        $doc = $this->parse('<div>Hello</div>');

        $children = $doc->getChildren();
        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class)
            ->and($children[0]->asElement()->tagNameText())->toBe('div');
    });

    it('parses element with attributes', function (): void {
        $doc = $this->parse('<div class="foo" id="bar">content</div>');

        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe('div')
            ->and($element->getAttribute('class'))->toBe('foo')
            ->and($element->getAttribute('id'))->toBe('bar')
            ->and($element->hasAttribute('class'))->toBeTrue()
            ->and($element->hasAttribute('missing'))->toBeFalse();
    });

    it('parses bound attributes', function (): void {
        $doc = $this->parse('<div :class="dynamic"></div>');

        $children = $doc->getChildren();
        $element = $children[0]->asElement();
        $attrs = $element->attributes();

        expect($attrs->has('class'))->toBeTrue();
        $attr = $attrs->get('class');
        expect($attr->isBound())->toBeTrue()
            ->and($attr->valueText())->toBe('dynamic');
    });

    it('parses text nodes', function (): void {
        $doc = $this->parse('Hello World');

        $children = $doc->getChildren();
        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->asText()->getContent())->toBe('Hello World');
    });

    it('parses echo statements', function (): void {
        $doc = $this->parse('{{ $name }}');

        $children = $doc->getChildren();
        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->isEscaped())->toBeTrue()
            ->and($children[0]->asEcho()->expression())->toBe('$name');
    });

    it('parses raw echo statements', function (): void {
        $doc = $this->parse('{!! $html !!}');

        $children = $doc->getChildren();
        expect($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->isRaw())->toBeTrue()
            ->and($children[0]->asEcho()->expression())->toBe('$html');
    });

    it('parses standalone directives', function (): void {
        $doc = $this->parse('@include("header")');

        $children = $doc->getChildren();
        expect($children[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[0]->asDirective()->nameText())->toBe('include');
    });

    it('parses directive blocks', function (): void {
        $doc = $this->parse('@if($show)visible@endif');

        $children = $doc->getChildren();
        expect($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[0]->asDirectiveBlock()->nameText())->toBe('if');
    });

    it('parses HTML comments', function (): void {
        $doc = $this->parse('<!-- comment -->');

        $children = $doc->getChildren();
        expect($children[0])->toBeInstanceOf(CommentNode::class)
            ->and($children[0]->asComment()->content())->toBe(' comment ');
    });

    it('parses Blade comments', function (): void {
        $doc = $this->parse('{{-- comment --}}');

        $children = $doc->getChildren();
        expect($children[0])->toBeInstanceOf(BladeCommentNode::class)
            ->and($children[0]->asBladeComment()->content())->toBe(' comment ')
            ->and($children[0]->asBladeComment()->text())->toBe('comment')
            ->and($children[0]->render())->toBe('{{-- comment --}}');
    });

    it('handles nested elements', function (): void {
        $doc = $this->parse('<div><span>text</span></div>');

        $children = $doc->getChildren();
        $div = $children[0]->asElement();

        expect($div->tagNameText())->toBe('div')
            ->and($div->hasChildren())->toBeTrue();

        $divChildren = $div->getChildren();
        expect($divChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[0]->asElement()->tagNameText())->toBe('span');
    });

    it('provides descendants iterator', function (): void {
        $doc = $this->parse('<div><span>text</span></div>');

        $children = $doc->getChildren();
        $div = $children[0];

        $descendants = $div->getDescendants();

        expect(count($descendants))->toBeGreaterThanOrEqual(1);
    });

    it('caches nodes for identity', function (): void {
        $doc = $this->parse('<div></div>');

        $children1 = $doc->getChildren();
        $children2 = $doc->getChildren();

        expect($children1[0])->toBe($children2[0]);
    });

    it('renders document back to string', function (): void {
        $template = '<div class="foo">Hello</div>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    it('handles self-closing elements', function (): void {
        $doc = $this->parse('<img src="test.jpg" />');

        $children = $doc->getChildren();
        $img = $children[0]->asElement();

        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->tagNameText())->toBe('img')
            ->and($img->isSelfClosing())->toBeTrue();
    });

    it('detects component elements', function (): void {
        $doc = $this->parse('<x-button>Click</x-button>');

        $children = $doc->getChildren();
        expect($children[0]->asElement()->isComponent())->toBeTrue();
    });

    test('type checking methods work', function (): void {
        $doc = $this->parse('<div>text</div>');

        $children = $doc->getChildren();
        $div = $children[0];

        expect($div->isElement())->toBeTrue()
            ->and($div->isText())->toBeFalse()
            ->and($div->isDirective())->toBeFalse()
            ->and($div->asElement())->toBe($div)
            ->and($div->asText())->toBeNull();
    });

    it('handles simple tag names', function (): void {
        $doc = $this->parse('<div>text</div>');
        $children = $doc->getChildren();
        $tagName = $children[0]->asElement()->tagName();

        expect((string) $tagName)->toBe('div')
            ->and($tagName->isSimple())->toBeTrue()
            ->and($tagName->isComplex())->toBeFalse()
            ->and($tagName->staticText())->toBe('div');
    });

    it('handles complex tag names with echo', function (): void {
        $doc = $this->parse('<div-{{ $type }}>text</div-{{ $type }}>');
        $children = $doc->getChildren();
        $tagName = $children[0]->asElement()->tagName();

        expect((string) $tagName)->toBe('div-{{ $type }}')
            ->and($tagName->isComplex())->toBeTrue()
            ->and($tagName->isSimple())->toBeFalse();

        $parts = $tagName->getParts();
        expect(count($parts))->toBeGreaterThanOrEqual(2);
    });

    test('tagName is Stringable in string context', function (): void {
        $doc = $this->parse('<span>text</span>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $result = 'Tag: '.$element->tagName();
        expect($result)->toBe('Tag: span');
    });

    it('handles simple attribute values', function (): void {
        $doc = $this->parse('<div class="foo bar">text</div>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $attr = $element->attributes()->get('class');
        expect($attr)->not->toBeNull()
            ->and($attr->valueText())->toBe('foo bar')
            ->and($attr->hasComplexValue())->toBeFalse();

        $valueObj = $attr->value();
        expect((string) $valueObj)->toBe('foo bar')
            ->and($valueObj->isSimple())->toBeTrue();
    });

    it('handles complex attribute values with echo', function (): void {
        $doc = $this->parse('<div class="prefix-{{ $var }}-suffix">text</div>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $attr = $element->attributes()->get('class');
        expect($attr)->not->toBeNull()
            ->and($attr->valueText())->toBe('prefix-{{ $var }}-suffix')
            ->and($attr->hasComplexValue())->toBeTrue();

        $valueObj = $attr->value();
        expect((string) $valueObj)->toBe('prefix-{{ $var }}-suffix')
            ->and($valueObj->isComplex())->toBeTrue();

        $parts = $valueObj->getParts();
        expect(count($parts))->toBeGreaterThanOrEqual(2);
    });

    it('handles bound attributes with complex values', function (): void {
        $doc = $this->parse('<div :class="{ active: {{ $isActive }} }">text</div>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $attr = $element->attributes()->get('class');
        expect($attr)->not->toBeNull()
            ->and($attr->isBound())->toBeTrue()
            ->and($attr->hasComplexValue())->toBeTrue();
    });

    test('attributeValue and staticText returns only static portions', function (): void {
        $doc = $this->parse('<div class="foo-{{ $var }}-bar">text</div>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $valueObj = $element->attributes()->get('class')->value();
        expect($valueObj->staticText())->toBe('foo--bar');
    });

    it('handles simple attribute names', function (): void {
        $doc = $this->parse('<div data-value="test">text</div>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $attr = $element->attributes()->get('data-value');
        expect($attr)->not->toBeNull()
            ->and($attr->nameText())->toBe('data-value')
            ->and($attr->hasComplexName())->toBeFalse();

        $nameObj = $attr->name();
        expect((string) $nameObj)->toBe('data-value')
            ->and($nameObj->isSimple())->toBeTrue();
    });

    it('handles bound attribute names', function (): void {
        $doc = $this->parse('<div :class="foo">text</div>');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $attr = $element->attributes()->get('class');
        $nameObj = $attr->name();

        expect((string) $nameObj)->toBe('class')
            ->and($nameObj->rawName())->toBe(':class')
            ->and($nameObj->isBound())->toBeTrue();
    });

    test('boolean attributes have null value', function (): void {
        $doc = $this->parse('<input disabled />');
        $children = $doc->getChildren();
        $element = $children[0]->asElement();

        $attr = $element->attributes()->get('disabled');
        expect($attr)->not->toBeNull()
            ->and($attr->valueText())->toBeNull()
            ->and($attr->isBoolean())->toBeTrue()
            ->and($attr->valueText())->toBeNull();
    });
});
