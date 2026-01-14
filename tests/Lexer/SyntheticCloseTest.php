<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('Synthetic Close', function (): void {
    test('synthetic close on nested tag', function (): void {
        $source = '<div<span>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $syntheticClose = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::SyntheticClose);

        expect($syntheticClose)->not->toBeNull('Should emit SyntheticClose token')
            ->and($syntheticClose['start'])->toBe($syntheticClose['end'], 'SyntheticClose should be zero-width')
            ->and($syntheticClose['start'])->toBe(4, 'SyntheticClose should be at position 4 (after "div")');

        $types = array_map(fn ($t) => $t['type'], $result->tokens);
        expect($types)->toBe([
            TokenType::LessThan,
            TokenType::TagName,           // div
            TokenType::SyntheticClose,    // zero-width recovery
            TokenType::LessThan,
            TokenType::TagName,           // span
            TokenType::GreaterThan,
        ])
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('synthetic close with partial attributes', function (): void {
        $source = '<div class="test"<span>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $syntheticClose = collect($result->tokens)
            ->first(fn ($t) => $t['type'] === TokenType::SyntheticClose);

        expect($syntheticClose)->not->toBeNull('Should emit SyntheticClose token')
            ->and($syntheticClose['start'])->toBe($syntheticClose['end'], 'SyntheticClose should be zero-width')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('synthetic close with blade echo', function (): void {
        $source = '<div {{ $x }}<span>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))
            ->toBeTrue('Should emit SyntheticClose token')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple unclosed tags', function (): void {
        $source = '<div<span<p>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        $syntheticCloseCount = collect($result->tokens)
            ->filter(fn ($t) => $t['type'] === TokenType::SyntheticClose)
            ->count();

        expect($syntheticCloseCount)->toBeGreaterThanOrEqual(1)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unclosed tag with attributes', function (): void {
        $source = '<div id="test" class="foo"<span>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))
            ->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unclosed self-closing tag', function (): void {
        $source = '<img src="test"<div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))
            ->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('no synthetic close on properly closed tag', function (): void {
        $source = '<div></div>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))
            ->toBeFalse('Should NOT emit SyntheticClose for properly closed tags');
    });

    test('no synthetic close on self-closing tag', function (): void {
        $source = '<img src="test" />';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))
            ->toBeFalse('Should NOT emit SyntheticClose for self-closing tags');
    });

    test('synthetic close preserves zero-width nature', function (): void {
        $source = '<div<span<p>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        collect($result->tokens)
            ->filter(fn ($t) => $t['type'] === TokenType::SyntheticClose)
            ->each(fn ($t) => expect($t['start'])->toBe($t['end'], 'SyntheticClose must be zero-width'));
    });

    test('unclosed tag with directive', function (): void {
        $source = '<div @click="handler"<span>';
        $lexer = new Lexer($source, Directives::acceptAll());
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))->toBeTrue()
            ->and($tokens->contains(fn ($t) => $t['type'] === TokenType::Directive))->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unclosed tag with mixed blade constructs', function (): void {
        $source = '<div class="{{ $class }}" @click="handler"<span>';
        $lexer = new Lexer($source, Directives::acceptAll());
        $result = $lexer->tokenize();

        $tokens = collect($result->tokens);

        expect($tokens->contains(fn ($t) => $t['type'] === TokenType::SyntheticClose))->toBeTrue()
            ->and($tokens->contains(fn ($t) => $t['type'] === TokenType::EchoStart))->toBeTrue()
            ->and($tokens->contains(fn ($t) => $t['type'] === TokenType::Directive))->toBeTrue()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
