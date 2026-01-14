<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('HTML Attributes', function (): void {
    test('single double quoted attribute', function (): void {
        $source = '<div class="container">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::text($result->tokens[3], $source))->toBe('class')
            ->and($result->tokens[4]['type'])->toBe(TokenType::Equals)
            ->and($result->tokens[5]['type'])->toBe(TokenType::Quote)
            ->and($result->tokens[6]['type'])->toBe(TokenType::AttributeValue)
            ->and(Token::text($result->tokens[6], $source))->toBe('container')
            ->and($result->tokens[7]['type'])->toBe(TokenType::Quote)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('single single quoted attribute', function (): void {
        $source = "<div id='main'>";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::text($result->tokens[3], $source))->toBe('id')
            ->and($result->tokens[6]['type'])->toBe(TokenType::AttributeValue)
            ->and(Token::text($result->tokens[6], $source))->toBe('main')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('empty quoted value', function (): void {
        $source = '<div class="">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $quotes = array_filter($result->tokens, fn ($t) => $t['type'] === TokenType::Quote);
        expect(count($quotes))->toBe(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unquoted attribute value', function (): void {
        $source = '<div data-id=123>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::text($result->tokens[3], $source))->toBe('data-id')
            ->and($result->tokens[4]['type'])->toBe(TokenType::Equals)
            ->and($result->tokens[5]['type'])->toBe(TokenType::AttributeValue)
            ->and(Token::text($result->tokens[5], $source))->toBe('123')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unquoted alphanumeric', function (): void {
        $source = '<input type=text>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::text($result->tokens[3], $source))->toBe('type')
            ->and($result->tokens[5]['type'])->toBe(TokenType::AttributeValue)
            ->and(Token::text($result->tokens[5], $source))->toBe('text')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('boolean attribute no value', function (): void {
        $source = '<input disabled>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::text($result->tokens[3], $source))->toBe('disabled')
            ->and($result->tokens[4]['type'])->toBe(TokenType::GreaterThan)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple boolean attributes', function (): void {
        $source = '<input disabled checked readonly>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['disabled', 'checked', 'readonly'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('a few attributes', function (): void {
        $source = '<div class="foo" id="bar">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['class', 'id'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('various mixed attributes', function (): void {
        $source = '<input type="text" disabled value=hello>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['type', 'disabled', 'value'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('a few more attributes of various types', function (): void {
        $source = '<input type="text" name=\'username\' maxlength=50 required data-id=123>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['type', 'name', 'maxlength', 'required', 'data-id'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('attribute with spaces around equals', function (): void {
        $source = '<div class = "foo">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and($result->tokens[5]['type'])->toBe(TokenType::Equals)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('attributes with newlines', function (): void {
        $source = "<div\n  class=\"foo\"\n  id=\"bar\">";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        expect($attrNames)->toHaveCount(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('data attribute', function (): void {
        $source = '<div data-test="value">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrName = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        ));
        expect(Token::text($attrName, $source))->toBe('data-test')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('aria attribute', function (): void {
        $source = '<div aria-label="menu">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrName = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        ));
        expect(Token::text($attrName, $source))->toBe('aria-label')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('namespaced attribute', function (): void {
        $source = '<svg xmlns:xlink="http://example.com">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrName = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        ));
        expect(Token::text($attrName, $source))->toBe('xmlns:xlink')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('attribute with underscore', function (): void {
        $source = '<div _custom="value">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrName = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        ));
        expect(Token::text($attrName, $source))->toBe('_custom')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('value with spaces', function (): void {
        $source = '<div title="hello world">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrValue = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeValue
        ));
        expect(Token::text($attrValue, $source))->toBe('hello world')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('value with special chars', function (): void {
        $source = '<div data="<>&\"">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrValue = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeValue
        ));
        expect(Token::text($attrValue, $source))->toBe('<>&\"')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('value with numbers', function (): void {
        $source = '<div data="123-456">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrValue = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeValue
        ));
        expect(Token::text($attrValue, $source))->toBe('123-456')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('self closing with attribute', function (): void {
        $source = '<img src="test.jpg" />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrName = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        ));
        expect(Token::text($attrName, $source))->toBe('src')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('self closing with multiple attributes', function (): void {
        $source = '<img src="test.jpg" alt="Test" width=100 />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['src', 'alt', 'width'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('attribute at tag start', function (): void {
        $source = '<div class="foo">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('no space after tag name', function (): void {
        $source = '<div class="foo">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[1]['type'])->toBe(TokenType::TagName)
            ->and($result->tokens[3]['type'])->toBe(TokenType::AttributeName)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('link tag', function (): void {
        $source = '<link rel="stylesheet" href="/css/app.css" type="text/css">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['rel', 'href', 'type'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('script tag', function (): void {
        $source = '<script src="/js/app.js" defer async></script>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['src', 'defer', 'async'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('input tag full', function (): void {
        $source = '<input type="text" name="username" id="user-input" class="form-control" placeholder="Enter username" required maxlength=50>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['type', 'name', 'id', 'class', 'placeholder', 'required', 'maxlength'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('button tag', function (): void {
        $source = '<button type="submit" class="btn btn-primary" disabled>Submit</button>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['type', 'class', 'disabled'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiline quoted value', function (): void {
        $source = "<div title=\"line 1\nline 2\">";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrValue = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeValue
        ));
        expect(Token::text($attrValue, $source))->toBe("line 1\nline 2")
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('ten attributes', function (): void {
        $source = '<div a1="v1" a2="v2" a3="v3" a4="v4" a5="v5" a6="v6" a7="v7" a8="v8" a9="v9" a10="v10">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        expect($attrNames)->toHaveCount(10)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('long attribute name', function (): void {
        $source = '<div data-very-long-attribute-name-for-testing="value">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrName = current(array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        ));
        expect(Token::text($attrName, $source))->toBe('data-very-long-attribute-name-for-testing')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('long attribute value', function (): void {
        $source = '<div data="This is a very long attribute value that contains many words and special characters like <>&\"">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('nested tags with attributes', function (): void {
        $source = '<div class="a"><span id="b"><p data="c">text</p></span></div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $attrNames = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] === TokenType::AttributeName
        );
        $names = array_map(fn ($t) => Token::text($t, $source), $attrNames);
        expect(array_values($names))->toBe(['class', 'id', 'data'])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
