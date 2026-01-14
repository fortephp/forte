<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('Dynamic Element Names', function (): void {
    test('echo as element name emits proper token sequence', function (): void {
        $source = '<{{ $element }} class="test">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo element name without attributes', function (): void {
        $source = '<{{ $element }}>content</{{ $element }}>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::GreaterThan,
            TokenType::Text,
            TokenType::LessThan,
            TokenType::Slash,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('raw echo as element name with attributes', function (): void {
        $source = '<{!! $tag !!} id="main">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::RawEchoStart,
            TokenType::EchoContent,
            TokenType::RawEchoEnd,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('triple echo as element name with attributes', function (): void {
        $source = '<{{{ $tag }}} data="val">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TripleEchoStart,
            TokenType::EchoContent,
            TokenType::TripleEchoEnd,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('composite tag name with static prefix and echo', function (): void {
        $source = '<div-{{ $id }} class="test">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::text($result->tokens[1], $source))->toBe('div-')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple echoes in element name', function (): void {
        $source = '<{{ $prefix }}-{{ $suffix }} id="x">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::TagName,           // the "-" between echoes
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('at-prefixed string in element name position becomes tag name', function (): void {
        $source = '<@component class="test">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $types = collect($result->tokens)->pluck('type')->all();

        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::text($result->tokens[1], $source))->toBe('@component')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
