<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;

describe('Escaped Quotes', function (): void {
    test('escaped double quote in attribute value', function (): void {
        $html = '<div data-value="hello \"world\" goodbye"></div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)->not->toBeNull();

        $value = substr($html, $attrValueToken['start'], $attrValueToken['end'] - $attrValueToken['start']);
        expect($value)
            ->toBe('hello \"world\" goodbye')
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('escaped single quote in attribute value', function (): void {
        $html = "<div data-value='hello \\'world\\' goodbye'></div>";

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)->not->toBeNull();

        $value = substr($html, $attrValueToken['start'], $attrValueToken['end'] - $attrValueToken['start']);
        expect($value)->toBe("hello \\'world\\' goodbye")
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('double backslash followed by quote ends attribute', function (): void {
        $html = '<div data-value="hello\\\\">after</div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)->not->toBeNull();

        $value = substr($html, $attrValueToken['start'], $attrValueToken['end'] - $attrValueToken['start']);
        expect($value)->toBe('hello\\\\')
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('triple backslash escapes quote', function (): void {
        $html = '<div data-value="hello\\\\\\">world"></div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)->not->toBeNull()
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('four backslashes does NOT escape quote', function (): void {
        $html = '<div data-value="test\\\\\\\\">after</div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)->not->toBeNull();

        $value = substr($html, $attrValueToken['start'], $attrValueToken['end'] - $attrValueToken['start']);
        expect($value)->toBe('test\\\\\\\\')
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('backslash at start of value followed by quote', function (): void {
        $html = '<div data-value="\\">test"></div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)
            ->not->toBeNull()
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('multiple escaped quotes in sequence', function (): void {
        $html = '<div data-value="a\"b\"c\"d"></div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        $attrValueToken = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::AttributeValue);

        expect($attrValueToken)->not->toBeNull();

        $value = substr($html, $attrValueToken['start'], $attrValueToken['end'] - $attrValueToken['start']);
        expect($value)->toBe('a\"b\"c\"d')
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('unclosed quote with escapes handles gracefully', function (): void {
        $html = '<div data-value="test\"';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        expect($result->tokens)->not->toBeEmpty()
            ->and($this->parse($html)->render())->toBe($html);
    });

    test('empty value with just escaped quote', function (): void {
        $html = '<div data-value="\""></div>';

        $lexer = new Lexer($html);
        $result = $lexer->tokenize();

        expect($result->tokens)->not->toBeEmpty()
            ->and($this->parse($html)->render())->toBe($html);
    });

    it('correctly identifies attribute boundaries with mixed content', function (): void {
        $html = '<input type="text" value="say \"hello\"" class="btn">';

        $doc = $this->parse($html);
        $element = $doc->elements->first();

        expect($element->getAttribute('type'))->toBe('text')
            ->and($element->getAttribute('class'))->toBe('btn')
            ->and($this->parse($html)->render())->toBe($html);
    });

    it('handles blade echoes with escaped quotes', function (): void {
        $html = '<div data-config="{ \"key\": {{ $value }} }"></div>';

        $doc = $this->parse($html);
        $element = $doc->elements->first();

        expect($element)->not->toBeNull()
            ->and($this->parse($html)->render())->toBe($html);
    });
});
