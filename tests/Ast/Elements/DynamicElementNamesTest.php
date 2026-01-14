<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Dynamic Element Names', function (): void {
    test('fully dynamic element tag with echo pairs correctly', function (): void {
        $template = '<{{ $element }} class="test">content</{{ $element }}>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('{{ $element }}');

        $closingName = $element->closingTag()->name();
        expect($closingName)->not->toBeNull()
            ->and((string) $closingName)->toBe('{{ $element }}');

        $children = $element->getChildren();
        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('content');
    });

    test('partially dynamic element tag with echo pairs correctly', function (): void {
        $template = '<test-{{ $thing }} class="foo">content</test-{{ $thing }}>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('test-{{ $thing }}');

        $closingName = $element->closingTag()->name();
        expect($closingName)->not->toBeNull()
            ->and((string) $closingName)->toBe('test-{{ $thing }}');
    });

    test('fully dynamic element tag with raw echo pairs correctly', function (): void {
        $template = '<{!! $tag !!}>content</{!! $tag !!}>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('{!! $tag !!}');

        $closingName = $element->closingTag()->name();
        expect($closingName)->not->toBeNull()
            ->and((string) $closingName)->toBe('{!! $tag !!}');
    });

    test('fully dynamic element tag with triple echo pairs correctly', function (): void {
        $template = '<{{{ $escaped }}}></{{{ $escaped }}}>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('{{{ $escaped }}}');

        $closingName = $element->closingTag()->name();
        expect($closingName)->not->toBeNull()
            ->and((string) $closingName)->toBe('{{{ $escaped }}}');
    });

    test('multiple dynamic tags in name pair correctly', function (): void {
        $template = '<{{ $prefix }}-{{ $suffix }}>content</{{ $prefix }}-{{ $suffix }}>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('{{ $prefix }}-{{ $suffix }}');

        $closingName = $element->closingTag()->name();
        expect($closingName)->not->toBeNull()
            ->and((string) $closingName)->toBe('{{ $prefix }}-{{ $suffix }}');
    });

    test('mixed static and dynamic parts pair correctly', function (): void {
        $template = '<div-{{ $id }}-wrapper>content</div-{{ $id }}-wrapper>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('div-{{ $id }}-wrapper');

        $closingName = $element->closingTag()->name();
        expect($closingName)->not->toBeNull()
            ->and((string) $closingName)->toBe('div-{{ $id }}-wrapper');
    });

    test('dynamic element tags render correctly', function (): void {
        $template = '<{{ $element }} class="test">content</{{ $element }}>';

        expect($this->parse($template)->render())
            ->toBe($template);
    });

    test('nested dynamic element tags work correctly', function (): void {
        $template = '<{{ $outer }}><{{ $inner }}>text</{{ $inner }}></{{ $outer }}>';

        $outer = $this->parseElement($template);

        expect($outer->isPaired())->toBeTrue()
            ->and($outer->tagNameText())->toBe('{{ $outer }}');

        $innerElement = $outer->firstChildOfType(ElementNode::class)->asElement();

        expect($innerElement)->not->toBeNull()
            ->and($innerElement->isPaired())->toBeTrue()
            ->and($innerElement->tagNameText())->toBe('{{ $inner }}');
    });

    test('self-closing dynamic element tag works', function (): void {
        $template = '<{{ $component }} />';

        $element = $this->parseElement($template);

        expect($element->isSelfClosing())->toBeTrue()
            ->and($element->tagNameText())->toBe('{{ $component }}');
    });

    test('dynamic element with attributes parses correctly', function (): void {
        $template = '<{{ $tag }} id="main" class="container">content</{{ $tag }}>';

        $element = $this->parseElement($template);

        expect($element->isPaired())->toBeTrue()
            ->and($element->tagNameText())->toBe('{{ $tag }}')
            ->and($element->attributes()->all())->toHaveCount(2);
    });

    test('handles xml attributes correctly without including in element name', function (): void {
        $template = '<feed xmlns="http://www.w3.org/2005/Atom">test</feed>';

        $element = $this->parseElement($template);

        expect($element->tagNameText())->toBe('feed')
            ->and($element->attributes()->all())->toHaveCount(1)
            ->and($element->attributes()->first()->nameText())->toBe('xmlns');
    });

    it('supports Blade echo {{ }} in element name with matching closing', function (): void {
        $html = <<<'HTML'
<div-{{ $x }} id="a"></div-{{ $x }}>
HTML;
        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('div-{{ $x }}')
            ->and($el->attributes()->all())->toHaveCount(1)
            ->and($el->attributes()->all()[0]->nameText())->toBe('id')
            ->and($el->attributes()->all()[0]->valueText())->toBe('a');
    });

    it('supports Blade raw echo {!! !!} in element name with matching closing', function (): void {
        $html = <<<'HTML'
<section-{!! $raw !!}></section-{!! $raw !!}>
HTML;

        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('section-{!! $raw !!}')
            ->and($el->isPaired())->toBeTrue();
    });

    it('supports Blade triple echo {{{ }}} in element name with matching closing', function (): void {
        $html = <<<'HTML'
<el-{{{ $t }}} data="1"></el-{{{ $t }}}>
HTML;
        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('el-{{{ $t }}}')
            ->and($el->attributes()->all())->toHaveCount(1)
            ->and($el->attributes()->all()[0]->nameText())->toBe('data')
            ->and($el->attributes()->all()[0]->valueText())->toBe('1')
            ->and($el->isPaired())->toBeTrue();
    });

    it('captures nested generic type arguments after tag name', function (): void {
        $html = <<<'HTML'
<Component<{ id: number, items: Array<Foo<Bar>> }>>
</Component>
HTML;
        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Component')
            ->and($el->genericTypeArguments())->toBe('<{ id: number, items: Array<Foo<Bar>> }>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('combines constructs in name with generics', function (): void {
        $html = <<<'HTML'
<Comp-{{ $x }}<T> id="a"></Comp-{{ $x }}>
HTML;
        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Comp-{{ $x }}')
            ->and($el->genericTypeArguments())->toBe('<T>')
            ->and($el->attributes()->all())->toHaveCount(1)
            ->and($el->attributes()->all()[0]->nameText())->toBe('id')
            ->and($el->attributes()->all()[0]->valueText())->toBe('a')
            ->and($el->isPaired())->toBeTrue();
    });

    it('supports standard PHP tag in element name with matching closing', function (): void {
        $html = <<<'HTML'
<element-<?php echo "hi"; ?> class="mt-4"></element-<?php echo "hi"; ?>>
HTML;

        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('element-<?php echo "hi"; ?>')
            ->and($el->isPaired())->toBeTrue()
            ->and($el->attributes()->all())->toHaveCount(1)
            ->and($el->attributes()->all()[0]->nameText())->toBe('class');
    });

    it('supports short echo PHP tag in element name with matching closing', function (): void {
        $html = <<<'HTML'
<el-<?= "x" ?> id="a"></el-<?= "x" ?>>
HTML;

        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('el-<?= "x" ?>')
            ->and($el->isPaired())->toBeTrue()
            ->and($el->attributes()->all())->toHaveCount(1)
            ->and($el->attributes()->all()[0]->nameText())->toBe('id');
    });

    test('php-in-name does not confuse generics or recovery', function (): void {
        $html = <<<'HTML'
<Component<?= "G" ?><T> data="1"></Component<?= "G" ?>>
HTML;
        $el = $this->parseElement($html);

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Component<?= "G" ?>')
            ->and($el->genericTypeArguments())->toBe('<T>')
            ->and($el->attributes()->all())->toHaveCount(1)
            ->and($el->attributes()->all()[0]->nameText())->toBe('data')
            ->and($el->attributes()->all()[0]->valueText())->toBe('1')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses simple generic type argument', function (): void {
        $el = $this->parseElement('<List<T>>items</List>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('List')
            ->and($el->genericTypeArguments())->toBe('<T>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses named generic type argument', function (): void {
        $el = $this->parseElement('<List<Item>>items</List>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('List')
            ->and($el->genericTypeArguments())->toBe('<Item>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses deeply nested generic type arguments', function (): void {
        $el = $this->parseElement('<Outer<Inner<Deep>>>content</Outer>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Outer')
            ->and($el->genericTypeArguments())->toBe('<Inner<Deep>>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses generic with array type', function (): void {
        $el = $this->parseElement('<Box<Array<T>>>content</Box>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Box')
            ->and($el->genericTypeArguments())->toBe('<Array<T>>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses generic with object type syntax using braces', function (): void {
        $el = $this->parseElement('<Component<{ id: number }>>content</Component>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Component')
            ->and($el->genericTypeArguments())->toBe('<{ id: number }>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses generic with complex object type including commas in braces', function (): void {
        $el = $this->parseElement('<Component<{ a: string, b: number }>>content</Component>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Component')
            ->and($el->genericTypeArguments())->toBe('<{ a: string, b: number }>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses generic with deeply nested types in braces', function (): void {
        $el = $this->parseElement('<Component<{ items: Array<Foo<Bar>> }>>content</Component>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Component')
            ->and($el->genericTypeArguments())->toBe('<{ items: Array<Foo<Bar>> }>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('handles self-closing element with single-letter generic', function (): void {
        $el = $this->parseElement('<Input<T>/>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Input')
            ->and($el->genericTypeArguments())->toBe('<T>')
            ->and($el->isSelfClosing())->toBeTrue();
    });

    it('parses multiple single-letter type params with comma', function (): void {
        $el = $this->parseElement('<Container<A, B>>content</Container>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Container')
            ->and($el->genericTypeArguments())->toBe('<A, B>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('preserves attributes alongside generics', function (): void {
        $el = $this->parseElement('<List<T> class="items" id="main">content</List>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('List')
            ->and($el->genericTypeArguments())->toBe('<T>')
            ->and($el->attributes()->all())->toHaveCount(2);
    });

    it('parses multi-letter type names in generics', function (): void {
        $el = $this->parseElement('<Map<string, number>>map</Map>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Map')
            ->and($el->genericTypeArguments())->toBe('<string, number>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses self-closing with multi-letter type name', function (): void {
        $el = $this->parseElement('<Input<string>/>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Input')
            ->and($el->genericTypeArguments())->toBe('<string>')
            ->and($el->isSelfClosing())->toBeTrue();
    });

    it('parses square brackets in generics', function (): void {
        $el = $this->parseElement('<Container<[A, B]>>content</Container>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Container')
            ->and($el->genericTypeArguments())->toBe('<[A, B]>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('parses self-closing with space before />', function (): void {
        $el = $this->parseElement('<Input<T> />');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Input')
            ->and($el->genericTypeArguments())->toBe('<T>')
            ->and($el->isSelfClosing())->toBeTrue();
    });

    it('it parses generics with mutliple keys', function (): void {
        $el = $this->parseElement('<Map<{ key: string, value: number }>>content</Map>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('Map')
            ->and($el->genericTypeArguments())->toBe('<{ key: string, value: number }>')
            ->and($el->isPaired())->toBeTrue();
    });

    it('treats lowercase element followed by < as malformed HTML, not generic', function (): void {
        $html = '<div<span>text</span>';
        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($doc->render())->toBe($html);
    });

    it('only allows uppercase generics for lowercase elements', function (): void {
        $el = $this->parseElement('<div<T>>content</div>');

        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->tagNameText())->toBe('div')
            ->and($el->genericTypeArguments())->toBe('<T>');
    });

    it('can access individual parts of a dynamic tag name', function (): void {
        $parts = $this->parseElement('<div-{{ $type }}-wrapper></div-{{ $type }}-wrapper>')
            ->tagName()
            ->getParts();

        expect($parts)->toHaveCount(3)
            ->and($parts[0]->isText())->toBeTrue()
            ->and($parts[0]->getDocumentContent())->toBe('div-')
            ->and($parts[1]->isEcho())->toBeTrue()
            ->and($parts[1]->getDocumentContent())->toBe('{{ $type }}')
            ->and($parts[2]->isText())->toBeTrue()
            ->and($parts[2]->getDocumentContent())->toBe('-wrapper');
    });

    it('can access static text portion of complex tag name', function (): void {
        $tagName = $this->parseElement('<div-{{ $type }}-wrapper></div-{{ $type }}-wrapper>')->tagName();

        expect($tagName->staticText())
            ->toBe('div--wrapper');
    });

    it('identifies simple tag names correctly', function (): void {
        $tagName = $this->parseElement('<div class="test"></div>')->tagName();

        expect($tagName->isSimple())->toBeTrue()
            ->and($tagName->isComplex())->toBeFalse()
            ->and((string) $tagName)->toBe('div');
    });
});
