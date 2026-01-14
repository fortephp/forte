<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('Blade Comments', function (): void {
    test('simple blade comment', function (): void {
        $source = '{{-- This is a comment --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('empty blade comment', function (): void {
        $source = '{{----}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment with text', function (): void {
        $source = 'Before {{-- comment --}} After';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(5)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[0], $source))->toBe('Before ')
            ->and($tokens[1]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[2]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[2], $source))->toBe(' comment ')
            ->and($tokens[3]['type'])->toBe(TokenType::BladeCommentEnd)
            ->and($tokens[4]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[4], $source))->toBe(' After');

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment ignores echo', function (): void {
        $source = '{{-- This has {{ $var }} inside --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[1], $source))->toBe(' This has {{ $var }} inside ')
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd);
        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment ignores raw echo', function (): void {
        $source = '{{-- This has {!! $html !!} inside --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment ignores triple echo', function (): void {
        $source = '{{-- This has {{{ $escaped }}} inside --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment with directive syntax', function (): void {
        $source = '{{-- @if($condition) something @endif --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('multiple blade comments', function (): void {
        $source = '{{-- First --}} Middle {{-- Second --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(7)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd)
            ->and($tokens[3]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[3], $source))->toBe(' Middle ')
            ->and($tokens[4]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[5]['type'])->toBe(TokenType::Text)
            ->and($tokens[6]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment with newlines', function (): void {
        $source = "{{-- \nLine 1\nLine 2\n--}}";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::BladeCommentEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment multiline', function (): void {
        $source = "{{--\n  This is a multi-line\n  blade comment\n--}}";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment with many dashes', function (): void {
        $source = '{{-- --- ----- --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[1]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment almost closing', function (): void {
        $source = '{{-- test --} not closed --}}';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and(Token::text($tokens[1], $source))->toBe(' test --} not closed ');

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('unclosed blade comment', function (): void {
        $source = '{{-- This comment is not closed';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->state)->toBe(State::BladeComment)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::UnclosedComment);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[1], $source))->toBe(' This comment is not closed');

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('unclosed blade comment with text before', function (): void {
        $source = 'Before text {{-- unclosed';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::UnclosedComment);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[0], $source))->toBe('Before text ')
            ->and($tokens[1]['type'])->toBe(TokenType::BladeCommentStart)
            ->and($tokens[2]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[2], $source))->toBe(' unclosed');

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('blade comment exact offsets', function (): void {
        $source = 'Start {{-- comment --}} End';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens[0]['start'])->toBe(0)
            ->and($tokens[0]['end'])->toBe(6)
            ->and($tokens[1]['start'])->toBe(6)
            ->and($tokens[1]['end'])->toBe(10)
            ->and($tokens[2]['start'])->toBe(10)
            ->and($tokens[2]['end'])->toBe(19)
            ->and($tokens[3]['start'])->toBe(19)
            ->and($tokens[3]['end'])->toBe(23)
            ->and($tokens[4]['start'])->toBe(23)
            ->and($tokens[4]['end'])->toBe(27);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

});
