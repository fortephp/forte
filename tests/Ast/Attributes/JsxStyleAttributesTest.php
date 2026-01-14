<?php

declare(strict_types=1);

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\ElementNode;

describe('JSX-style Attributes', function (): void {
    it('parses parentheses wrapping braces in attribute value', function (): void {
        $html = '<div data=({count: 5}) />';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('data')
            ->and($attr->valueText())->toBe('({count: 5})');
    });

    it('parses nested braces in attribute value', function (): void {
        $html = '<div data={a: {b: {c: 1}}} />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->valueText())->toBe('{a: {b: {c: 1}}}');
    });

    it('parses complex parentheses and braces combinations', function (): void {
        $html = '<div data=({a: (1 + 2), b: {c: [1, 2]}}) />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->valueText())->toBe('({a: (1 + 2), b: {c: [1, 2]}})');
    });

    it('handles strings within paired structures', function (): void {
        $html = '<div data=({msg: "hello {world}"}) />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->valueText())->toBe('({msg: "hello {world}"})');
    });

    it('handles multiple complex attributes together', function (): void {
        $html = '<Component data=({count: 5}) onClick={() => alert("hi")} {enabled} />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(3)
            ->and($attrs[0]->nameText())->toBe('data')
            ->and($attrs[0]->valueText())->toBe('({count: 5})')
            ->and($attrs[1]->nameText())->toBe('onClick')
            ->and($attrs[1]->valueText())->toBe('{() => alert("hi")}');
    });

    it('parses arrow function with multiple parameters', function (): void {
        $html = '<div onChange={(e, index) => handleChange(e, index)} />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->valueText())->toBe('{(e, index) => handleChange(e, index)}');
    });

    it('handles empty arrow function body', function (): void {
        $html = '<div onClick={() => {}} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{() => {}}');
    });

    it('handles arrow function with array destructuring', function (): void {
        $html = '<List onSort={([a, b]) => a - b} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{([a, b]) => a - b}');
    });

    it('handles async arrow function', function (): void {
        $html = '<Form onSubmit={async (e) => { await save(e) }} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{async (e) => { await save(e) }}');
    });

    it('parses object spread in attribute value', function (): void {
        $html = '<div data={{...props, extra: true}} />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($el->render())->toBe($html);
    });

    it('parses ternary operator in attribute value', function (): void {
        $html = '<div className={isActive ? "active" : "inactive"} />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->valueText())->toBe('{isActive ? "active" : "inactive"}');
    });

    it('handles empty brace-wrapped attribute nameText', function (): void {
        $html = '<div {}>content</div>';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);
    });

    it('parses brace-wrapped attribute nameText with no value', function (): void {
        $html = '<if {count > 0}>true</if>';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->isExpression())->toBeTrue()
            ->and($attr->render())->toBe('{count > 0}')
            ->and($attr->valueText())->toBeNull();
    });

    it('parses brace-wrapped attribute with comparison operators', function (): void {
        $html = '<div {count >= 10}>content</div>';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->isExpression())->toBeTrue()
            ->and($attr->render())->toBe('{count >= 10}');
    });

    it('parses brace-wrapped attribute with logical operators', function (): void {
        $html = '<div {enabled && visible || fallback}>content</div>';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        expect($attr->isExpression())->toBeTrue()
            ->and($attr->render())->toBe('{enabled && visible || fallback}');
    });

    it('handles JSX spread attribute syntax', function (): void {
        $html = '<Component {...props} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->isExpression())->toBeTrue()
            ->and($attr->render())->toBe('{...props}');
    });

    it('parses arrow function in attribute value', function (): void {
        $html = '<div model={() => { count++ }} />';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];

        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->nameText())->toBe('model')
            ->and($attr->valueText())->toBe('{() => { count++ }}');
    });

    it('parses multiline arrow function in attribute', function (): void {
        $html = '<button onClick={() => {
            console.log("clicked");
            handleClick();
        }} />';

        $el = $this->parseElement($html);

        $attrs = $el->attributes()->all();
        $attr = $attrs[0];

        $value = $attr->valueText();
        expect($value)->toContain('console.log("clicked")')
            ->and($value)->toContain('handleClick()');
    });

    it('handles immediately invoked function expression', function (): void {
        $html = '<div value={(() => compute())()} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{(() => compute())()}');
    });

    it('handles object with method shorthand', function (): void {
        $html = '<div handlers={{onClick() { handle() }}} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($el->render())->toBe($html);
    });

    it('preserves whitespace in arrow function body', function (): void {
        $html = '<div onClick={() => {
    const x = 1;
    return x;
}} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        $value = $attr->valueText();
        expect($value)->toContain('const x = 1;')
            ->and($value)->toContain('return x;');
    });

    it('recovers from unclosed brace in attribute name', function (): void {
        $html = '<if {count > 0>content</if>';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);
    });

    it('recovers from unclosed paren in attribute value', function (): void {
        $html = '<div data=({count: 5 />other</div>';

        // Parsing should not crash
        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);
    });

    it('recovers from unclosed brace in attribute value', function (): void {
        $html = '<div data={{a: 1 />other</div>';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);
    });

    it('handles mismatched paired delimiters', function (): void {
        $html = '<div data=({count: 5}) />';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class)
            ->and($el->render())->toBe($html);
    });

    it('continues parsing after error in attribute', function (): void {
        $html = '<div data=({broken></div>';

        $div = $this->parseElement($html);
        expect($div)->toBeInstanceOf(ElementNode::class);
    });

    it('handles EOF in middle of paired structure', function (): void {
        $html = '<div data=({count:';

        $el = $this->parseElement($html);
        expect($el)->toBeInstanceOf(ElementNode::class);
    });

    it('handles nested unclosed braces gracefully', function (): void {
        $html = '<div data={{a: {b: {c>broken</div>';

        expect($this->parseElement($html))->toBeInstanceOf(ElementNode::class);
    });

    test('document content offset is correct for parentheses-wrapped braces', function (): void {
        $html = '<div data=({count: 5}) />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('({count: 5})');
    });

    test('document content offset is correct for simple brace expression', function (): void {
        $html = '<div value={count} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{count}');
    });

    test('document content offset is correct with leading content', function (): void {
        $html = '<div class="test" onClick={() => handle()} />';

        $el = $this->parseElement($html);
        $attrs = $el->attributes()->all();

        expect($attrs[1]->valueText())->toBe('{() => handle()}');
    });

    test('document content offset is correct for multiple JSX attributes', function (): void {
        $html = '<Comp a={1} b={() => 2} c={{d: 3}} />';

        $el = $this->parseElement($html);
        $attrs = $el->attributes()->all();

        expect($attrs[0]->valueText())->toBe('{1}')
            ->and($attrs[1]->valueText())->toBe('{() => 2}')
            ->and($el->render())->toBe($html);
    });

    test('document content offset handles deeply nested structures', function (): void {
        $html = '<div fn={() => { return { a: { b: () => c } } }} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{() => { return { a: { b: () => c } } }}');
    });

    test('document content offset with strings containing braces', function (): void {
        $html = '<div onClick={() => alert("{ not a brace }")} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{() => alert("{ not a brace }")}');
    });

    test('document content offset with template literals', function (): void {
        $html = '<div data={`template ${var}`} />';

        $el = $this->parseElement($html);
        $attr = $el->attributes()->all()[0];

        expect($attr->valueText())->toBe('{`template ${var}`}');
    });
});
