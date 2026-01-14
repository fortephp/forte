<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('Standard HTML Comments', function (): void {
    test('basic html comment', function (): void {
        $source = '<!-- test -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty('Should have no errors')
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('empty html comment', function (): void {
        $source = '<!---->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiline html comment', function (): void {
        $source = "<!--\ntest\ncomment\n-->";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe("\ntest\ncomment\n")
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with single dash', function (): void {
        $source = '<!-- foo - bar -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::text($result->tokens[1], $source))->toBe(' foo - bar ')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with double dash', function (): void {
        $source = '<!-- foo -- bar -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::text($result->tokens[1], $source))->toBe(' foo -- bar ')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with special chars', function (): void {
        $source = '<!-- foo > bar < baz -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::text($result->tokens[1], $source))->toBe(' foo > bar < baz ')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with text before and after', function (): void {
        $source = 'Hello <!-- comment --> World';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(5)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[0], $source))->toBe('Hello ')
            ->and($result->tokens[1]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[2]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[2], $source))->toBe(' comment ')
            ->and($result->tokens[3]['type'])->toBe(TokenType::CommentEnd)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[4], $source))->toBe(' World')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unclosed html comment', function (): void {
        $source = '<!-- no close';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->offset)->toBe(strlen($source))
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' no close');
    });

    test('comment with utf8', function (): void {
        $source = '<!-- 测试 -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::text($result->tokens[1], $source))->toBe(' 测试 ')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with emoji', function (): void {
        $source = '<!-- Hello 🎉 World -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::text($result->tokens[1], $source))->toBe(' Hello 🎉 World ')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with crlf', function (): void {
        $source = "<!--\r\ntest\r\n-->";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::text($result->tokens[1], $source))->toBe("\r\ntest\r\n")
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('very long comment', function (): void {
        $content = str_repeat('x', 10000);
        $source = "<!-- {$content} -->";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment at eof', function (): void {
        $source = 'text<!--comment-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[1]['type'])->toBe(TokenType::CommentStart)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple comments', function (): void {
        $source = '<!-- one -->text<!-- two -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(7)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment ignores echo', function (): void {
        $source = '<!-- This has {{ $var }} inside -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' This has {{ $var }} inside ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment ignores raw echo', function (): void {
        $source = '<!-- This has {!! $html !!} inside -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' This has {!! $html !!} inside ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment ignores triple echo', closure: function (): void {
        $source = '<!-- This has {{{ $safe }}} inside -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' This has {{{ $safe }}} inside ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment ignores directive', function (): void {
        $source = '<!-- @if(true) do something @endif -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' @if(true) do something @endif ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment ignores blade comment', function (): void {
        $source = '<!-- This has {{-- nested --}} inside -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' This has {{-- nested --}} inside ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment ignores php tags', function (): void {
        $source = "<!-- This has <?php echo 'test'; ?> inside -->";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(" This has <?php echo 'test'; ?> inside ")
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('comment with multiple blade constructs', function (): void {
        $source = '<!-- {{ $a }} {!! $b !!} @if(true) @endif -->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe(' {{ $a }} {!! $b !!} @if(true) @endif ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::CommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
