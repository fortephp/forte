<?php

declare(strict_types=1);

use Forte\Support\WhitespaceStringSplitter;

describe('String Splitter Parser', function (): void {
    it('splits simple string by spaces', function (): void {
        $result = WhitespaceStringSplitter::splitString('hello world');

        expect($result)->toBe(['hello', 'world']);
    });

    it('splits string with multiple spaces', function (): void {
        $result = WhitespaceStringSplitter::splitString('hello    world    test');

        expect($result)->toBe(['hello', 'world', 'test']);
    });

    it('splits string with tabs', function (): void {
        $result = WhitespaceStringSplitter::splitString("hello\tworld\ttest");

        expect($result)->toBe(['hello', 'world', 'test']);
    });

    it('splits string with mixed whitespace', function (): void {
        $result = WhitespaceStringSplitter::splitString("hello \t world\n\rtest");

        expect($result)->toBe(['hello', 'world', 'test']);
    });

    it('respects strings with single quotes', function (): void {
        $result = WhitespaceStringSplitter::splitString("hello 'world test' foo");

        expect($result)->toBe(['hello', "'world test'", 'foo']);
    });

    it('respects strings with double quotes', function (): void {
        $result = WhitespaceStringSplitter::splitString('hello "world test" foo');

        expect($result)->toBe(['hello', '"world test"', 'foo']);
    });

    it('respects nested parentheses', function (): void {
        $result = WhitespaceStringSplitter::splitString('func(a, b) test');

        expect($result)->toBe(['func(a, b)', 'test']);
    });

    it('respects deeply nested parentheses', function (): void {
        $result = WhitespaceStringSplitter::splitString('func(a, fn(b, c)) test');

        expect($result)->toBe(['func(a, fn(b, c))', 'test']);
    });

    it('respects arrays with brackets', function (): void {
        $result = WhitespaceStringSplitter::splitString('[1, 2, 3] as');

        expect($result)->toBe(['[1, 2, 3]', 'as']);
    });

    it('respects nested brackets', function (): void {
        $result = WhitespaceStringSplitter::splitString('[[1, 2], [3, 4]] as');

        expect($result)->toBe(['[[1, 2], [3, 4]]', 'as']);
    });

    it('respects braces', function (): void {
        $result = WhitespaceStringSplitter::splitString('{a: 1, b: 2} test');

        expect($result)->toBe(['{a: 1, b: 2}', 'test']);
    });

    it('respects complex nested structures', function (): void {
        $result = WhitespaceStringSplitter::splitString('explode(", ", "as,as,as,as") as');

        expect($result)->toBe(['explode(", ", "as,as,as,as")', 'as']);
    });

    it('handles string with escaped quotes', function (): void {
        $result = WhitespaceStringSplitter::splitString('test "hello \\"world\\"" foo');

        expect($result)->toBe(['test', '"hello \\"world\\""', 'foo']);
    });

    it('handles empty string', function (): void {
        $result = WhitespaceStringSplitter::splitString('');

        expect($result)->toBe([]);
    });

    it('handles string with only whitespace', function (): void {
        $result = WhitespaceStringSplitter::splitString('   ');

        expect($result)->toBe([]);
    });

    it('handles single word', function (): void {
        $result = WhitespaceStringSplitter::splitString('hello');

        expect($result)->toBe(['hello']);
    });

    it('handles array access', function (): void {
        $result = WhitespaceStringSplitter::splitString('$data["key"] as');

        expect($result)->toBe(['$data["key"]', 'as']);
    });

    it('handles method calls', function (): void {
        $result = WhitespaceStringSplitter::splitString('$obj->method() as');

        expect($result)->toBe(['$obj->method()', 'as']);
    });

    it('handles complex expression with multiple nested structures', function (): void {
        $result = WhitespaceStringSplitter::splitString('array_filter($users, fn($u) => $u->active) as');

        expect($result)->toBe(['array_filter($users, fn($u) => $u->active)', 'as']);
    });

    it('handles ternary operator', function (): void {
        $result = WhitespaceStringSplitter::splitString('$a ? $b : $c as');

        expect($result)->toBe(['$a', '?', '$b', ':', '$c', 'as']);
    });

    it('handles null coalescing operator', function (): void {
        $result = WhitespaceStringSplitter::splitString('$a ?? $b as');

        expect($result)->toBe(['$a', '??', '$b', 'as']);
    });

    it('handles arrow functions', function (): void {
        $result = WhitespaceStringSplitter::splitString('fn($x) => $x * 2 as');

        expect($result)->toBe(['fn($x)', '=>', '$x', '*', '2', 'as']);
    });

    it('splits on newlines', function (): void {
        $result = WhitespaceStringSplitter::splitString("hello\nworld");

        expect($result)->toBe(['hello', 'world']);
    });

    it('handles mixed quotes and parentheses', function (): void {
        $result = WhitespaceStringSplitter::splitString('func("test (nested)", $b) as');

        expect($result)->toBe(['func("test (nested)", $b)', 'as']);
    });

    it('handles consecutive strings', function (): void {
        $result = WhitespaceStringSplitter::splitString('"hello" "world" test');

        expect($result)->toBe(['"hello"', '"world"', 'test']);
    });

    it('handles empty parentheses', function (): void {
        $result = WhitespaceStringSplitter::splitString('func() as');

        expect($result)->toBe(['func()', 'as']);
    });

    it('handles empty brackets', function (): void {
        $result = WhitespaceStringSplitter::splitString('[] as');

        expect($result)->toBe(['[]', 'as']);
    });

    it('handles empty braces', function (): void {
        $result = WhitespaceStringSplitter::splitString('{} as');

        expect($result)->toBe(['{}', 'as']);
    });

    it('handles unicode characters', function (): void {
        $result = WhitespaceStringSplitter::splitString('hello 世界 test');

        expect($result)->toBe(['hello', '世界', 'test']);
    });

    it('handles operators without spaces', function (): void {
        $result = WhitespaceStringSplitter::splitString('$a+$b as');

        expect($result)->toBe(['$a+$b', 'as']);
    });

    it('handles range function', function (): void {
        $result = WhitespaceStringSplitter::splitString('range(1, 10) as');

        expect($result)->toBe(['range(1, 10)', 'as']);
    });

    it('handles static method calls', function (): void {
        $result = WhitespaceStringSplitter::splitString('Class::method() as');

        expect($result)->toBe(['Class::method()', 'as']);
    });

    it('handles namespace separator', function (): void {
        $result = WhitespaceStringSplitter::splitString('\\Namespace\\Class as');

        expect($result)->toBe(['\\Namespace\\Class', 'as']);
    });
});
