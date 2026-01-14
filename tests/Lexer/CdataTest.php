<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

beforeEach(function (): void {
    $this->directives = new Directives;
});

describe('CDATA Section Tokenization', function (): void {
    it('tokenizes basic CDATA section', function (): void {
        $source = '<![CDATA[content]]>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CdataStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[2]['type'])->toBe(TokenType::CdataEnd)
            ->and(substr($source, $result->tokens[0]['start'], $result->tokens[0]['end'] - $result->tokens[0]['start']))
            ->toBe('<![CDATA[')
            ->and(substr($source, $result->tokens[1]['start'], $result->tokens[1]['end'] - $result->tokens[1]['start']))
            ->toBe('content')
            ->and(substr($source, $result->tokens[2]['start'], $result->tokens[2]['end'] - $result->tokens[2]['start']))
            ->toBe(']]>');

    });

    it('tokenizes CDATA with special characters', function (): void {
        $source = '<![CDATA[<div class="foo" & bar>]]>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and(substr($source, $result->tokens[1]['start'], $result->tokens[1]['end'] - $result->tokens[1]['start']))
            ->toBe('<div class="foo" & bar>');
    });

    it('tokenizes CDATA with Blade syntax preserved', function (): void {
        $source = '<![CDATA[{{ $variable }} @directive]]>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(substr($source, $result->tokens[1]['start'], $result->tokens[1]['end'] - $result->tokens[1]['start']))
            ->toBe('{{ $variable }} @directive');
    });

    it('tokenizes empty CDATA section', function (): void {
        $source = '<![CDATA[]]>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CdataStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::CdataEnd);
    });

    it('handles unclosed CDATA section', function (): void {
        $source = '<![CDATA[content without closing';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::CdataStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->errors)->toHaveCount(1);
    });

    it('tokenizes CDATA with newlines', function (): void {
        $source = "<![CDATA[line1\nline2\nline3]]>";
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and(substr($source, $result->tokens[1]['start'], $result->tokens[1]['end'] - $result->tokens[1]['start']))
            ->toBe("line1\nline2\nline3");
    });

    it('does not confuse conditional comment end with CDATA', function (): void {
        $source = '<![endif]-->';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentEnd);
    });

    it('tokenizes CDATA followed by other content', function (): void {
        $source = '<![CDATA[content]]><div>test</div>';
        $lexer = new Lexer($source, $this->directives);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::CdataStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[2]['type'])->toBe(TokenType::CdataEnd)
            ->and($result->tokens[3]['type'])->toBe(TokenType::LessThan);
    });
});
