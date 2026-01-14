<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('HTML Elements', function (): void {
    test('simple opening tag', function (): void {
        $source = '<div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty('Should have no errors')
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('div')
            ->and($result->tokens[2]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('opening tag with text', function (): void {
        $source = '<div>Hello</div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(8)
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and($result->tokens[2]['type'])->toBe(TokenType::GreaterThan)
            ->and($result->tokens[3]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[3], $source))->toBe('Hello')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('closing tag', function (): void {
        $source = '</div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(4)
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Slash)
            ->and($result->tokens[2]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[2], $source))->toBe('div')
            ->and($result->tokens[3]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('self closing tag no space', function (): void {
        $source = '<br/>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(4)
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('br')
            ->and($result->tokens[2]['type'])->toBe(TokenType::Slash)
            ->and($result->tokens[3]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('self closing tag with space', closure: function (): void {
        $source = '<br />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(5)
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('br')
            ->and($result->tokens[2]['type'])->toBe(TokenType::Whitespace)
            ->and($result->tokens[3]['type'])->toBe(TokenType::Slash)
            ->and($result->tokens[4]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with dash', function (): void {
        $source = '<my-component>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('my-component')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with colon', function (): void {
        $source = '<svg:path>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('svg:path')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag mixed case', function (): void {
        $source = '<DiV>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('DiV')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('nested tags', function (): void {
        $source = '<div><span>text</span></div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('div')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('empty tag', function (): void {
        $source = '<>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag at eof', function (): void {
        $source = 'text<div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[1]['type'])->toBe(TokenType::LessThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('single char tags', function (): void {
        $source = '<a><p><b>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(9)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with multiple spaces before close', function (): void {
        $source = '<div   >';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(4)
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and($result->tokens[2]['type'])->toBe(TokenType::Whitespace)
            ->and($result->tokens[3]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with newline', function (): void {
        $source = "<div\n>";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(4)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with tab', function (): void {
        $source = "<div\t>";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(4)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('self closing with multiple spaces', function (): void {
        $source = '<br   />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(5)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with numbers', function (): void {
        $source = '<h1>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('h1')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag with underscore', function (): void {
        $source = '<_component>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('_component')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag all uppercase', function (): void {
        $source = '<DIV>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('DIV')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('complex tag name', function (): void {
        $source = '<my-custom_component123>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('my-custom_component123')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('namespace with multiple colons', function (): void {
        $source = '<ns:sub:element>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[1], $source))->toBe('ns:sub:element')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple consecutive opening tags', function (): void {
        $source = '<div><span><p>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(9)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple consecutive closing tags', function (): void {
        $source = '</div></span></p>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(12)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('deeply nested tags', function (): void {
        $source = '<div><div><div><span>text</span></div></div></div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('mixed tags and text', function (): void {
        $source = 'text1<div>text2</div>text3';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('self closing tags sequence', function (): void {
        $source = '<br/><hr/><img/>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(12)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('tag at start', function (): void {
        $source = '<div>content';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('closing tag at start', function (): void {
        $source = '</div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(4)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('only opening tag', function (): void {
        $source = '<div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('common void elements', function (): void {
        $source = '<br><hr><img><input><meta><link>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
