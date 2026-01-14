<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('Blade Echo in Attributes', function (): void {
    test('echo in quoted attribute', function (): void {
        $source = '<div class="{{ $var }}">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $types = array_map(fn ($t) => $t['type'], $result->tokens);
        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with surrounding text', function (): void {
        $source = '<div class="prefix {{ $var }} suffix">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $types = array_map(fn ($t) => $t['type'], $result->tokens);
        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::AttributeValue, // "prefix "
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::AttributeValue, // " suffix"
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple echos in attribute', function (): void {
        $source = '<div class="{{ $a }} middle {{ $b }}">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $types = array_map(fn ($t) => $t['type'], $result->tokens);
        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::AttributeValue, // " middle "
            TokenType::EchoStart,
            TokenType::EchoContent,
            TokenType::EchoEnd,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('raw echo in attribute', function (): void {
        $source = '<div data="{!! $html !!}">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $types = array_map(fn ($t) => $t['type'], $result->tokens);
        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::RawEchoStart,
            TokenType::EchoContent,
            TokenType::RawEchoEnd,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('triple echo in attribute', function (): void {
        $source = '<div data="{{{ $safe }}}">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $types = array_map(fn ($t) => $t['type'], $result->tokens);
        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,
            TokenType::Whitespace,
            TokenType::AttributeName,
            TokenType::Equals,
            TokenType::Quote,
            TokenType::TripleEchoStart,
            TokenType::EchoContent,
            TokenType::TripleEchoEnd,
            TokenType::Quote,
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo in unquoted attribute', function (): void {
        $source = '<div data={{ $value }}>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::EchoStart))
            ->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('mixed quotes and getEchoes', function (): void {
        $source = '<div class="{{ $a }}" data-value=\'{{ $b }}\'>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $echoCount = collect($result->tokens)
            ->filter(fn ($t) => $t['type'] === TokenType::EchoStart)
            ->count();

        expect($echoCount)->toBe(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Blade Directives in Attributes', function (): void {
    test('directive in attribute name position', function (): void {
        $source = '<div @click="handler">';
        $lexer = new Lexer($source, Directives::acceptAll());
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::Directive))
            ->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple directives in attributes', function (): void {
        $source = '<div @click="handler" @mouseover="hover">';
        $lexer = new Lexer($source, Directives::acceptAll());
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $directiveCount = collect($result->tokens)
            ->filter(fn ($t) => $t['type'] === TokenType::Directive)
            ->count();

        expect($directiveCount)->toBe(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('dynamic attribute name with echo', function (): void {
        $source = '<div data-{{ $name }}="value">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::EchoStart))
            ->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('fully dynamic attribute with echo name and value', function (): void {
        $source = '<div {{ $name }}="{{ $value }}">';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $echoCount = collect($result->tokens)
            ->filter(fn ($t) => $t['type'] === TokenType::EchoStart)
            ->count();

        expect($echoCount)->toBe(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('attribute with echo and directive mixed', function (): void {
        $source = '<div class="{{ $class }}" @click="handler">';
        $lexer = new Lexer($source, Directives::acceptAll());
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = collect($result->tokens);

        expect($tokens->contains(fn ($t) => $t['type'] === TokenType::EchoStart))->toBeTrue()
            ->and($tokens->contains(fn ($t) => $t['type'] === TokenType::Directive))->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('nested tags with blade in attributes', function (): void {
        $source = '<div class="{{ $outer }}"><span data="{{ $inner }}"></span></div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $echoCount = collect($result->tokens)
            ->filter(fn ($t) => $t['type'] === TokenType::EchoStart)
            ->count();

        expect($echoCount)->toBe(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('prioritizes Blade echo over JSX in attribute values', function (): void {
        $source = '<Table data={{...props}} />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->filter(fn ($t) => $t['type'] === TokenType::EchoStart)->count())
            ->toBe(1, 'Should tokenize {{ as Blade echo, not JSX')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::EchoEnd)->count())
            ->toBe(1, 'Should tokenize }} as Blade echo end, not JSX')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::JsxAttributeValue)->count())
            ->toBe(0, 'Should not tokenize as JSX attribute value');
    });

    test('tokenizes single curly brace as JSX', function (): void {
        $source = '<Component items={[1, 2, 3]} />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->filter(fn ($t) => $t['type'] === TokenType::JsxAttributeValue)->count())
            ->toBe(1, 'Should tokenize single { as JSX attribute value')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::EchoStart)->count())
            ->toBe(0, 'Should not tokenize as Blade echo');
    });

    test('prioritizes Blade raw echo in attribute values', function (): void {
        $source = '<div data={!! $html !!} />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->filter(fn ($t) => $t['type'] === TokenType::RawEchoStart)->count())
            ->toBe(1, 'Should tokenize {!! as Blade raw echo')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::RawEchoEnd)->count())
            ->toBe(1, 'Should tokenize !!} as Blade raw echo end')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::JsxAttributeValue)->count())
            ->toBe(0, 'Should not tokenize as JSX attribute value');
    });

    test('prioritizes Blade triple echo in attribute values', function (): void {
        $source = '<div data={{{ $escaped }}} />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->filter(fn ($t) => $t['type'] === TokenType::TripleEchoStart)->count())
            ->toBe(1, 'Should tokenize {{{ as Blade triple echo')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::TripleEchoEnd)->count())
            ->toBe(1, 'Should tokenize }}} as Blade triple echo end')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::JsxAttributeValue)->count())
            ->toBe(0, 'Should not tokenize as JSX attribute value');
    });

    test('handles mixed Blade and JSX attributes', function (): void {
        $source = '<Component blade={{...props}} jsx={value} />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->filter(fn ($t) => $t['type'] === TokenType::EchoStart)->count())
            ->toBe(1, 'Should have 1 Blade echo')
            ->and($tokens->filter(fn ($t) => $t['type'] === TokenType::JsxAttributeValue)->count())
            ->toBe(1, 'Should have 1 JSX attribute value');
    });
});
