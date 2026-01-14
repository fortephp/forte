<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('Conditional Comments', function (): void {
    test('conditional comment start', function (): void {
        $source = '<!--[if IE 8]>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(1)
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment end', function (): void {
        $source = '<![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(1)
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment full', function (): void {
        $source = '<!--[if IE 8]><div>IE specific</div><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with parentheses condition', function (): void {
        $source = '<!--[if (gte mso 9)]><p>Office content</p><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if (gte mso 9)]>')
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with OR condition', function (): void {
        $source = '<!--[if (gte mso 9)|(IE)]><table><tr><td>Outlook</td></tr></table><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if (gte mso 9)|(IE)]>')
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with AND condition', function (): void {
        $source = '<!--[if (gt IE 5)&(lt IE 7)]><link rel="stylesheet" href="ie6.css"><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if (gt IE 5)&(lt IE 7)]>')
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with NOT condition', function (): void {
        $source = '<!--[if !IE]><p>Not IE</p><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if !IE]>')
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with less than version', function (): void {
        $source = '<!--[if lt IE 9]><script src="html5shiv.js"></script><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if lt IE 9]>')
            ->and($result->tokens[count($result->tokens) - 1]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with lte version', function (): void {
        $source = '<!--[if lte IE 8]><link href="ie8.css"><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if lte IE 8]>')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with gte version', function (): void {
        $source = '<!--[if gte IE 10]><meta content="ie=edge"><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if gte IE 10]>')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with gt version', function (): void {
        $source = '<!--[if gt IE 7]><div>Modern IE</div><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if gt IE 7]>')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment full structure with exact token count', function (): void {
        $source = '<!--[if IE]><p>IE content</p><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(10)
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[2]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[2], $source))->toBe('p')
            ->and($result->tokens[3]['type'])->toBe(TokenType::GreaterThan)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[4], $source))->toBe('IE content')
            ->and($result->tokens[5]['type'])->toBe(TokenType::LessThan)
            ->and($result->tokens[6]['type'])->toBe(TokenType::Slash)
            ->and($result->tokens[7]['type'])->toBe(TokenType::TagName)
            ->and(Token::text($result->tokens[7], $source))->toBe('p')
            ->and($result->tokens[8]['type'])->toBe(TokenType::GreaterThan)
            ->and($result->tokens[9]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with mso condition - full structure', function (): void {
        $source = '<!--[if (gte mso 9)]><xml>mso</xml><![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(10)
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and(Token::text($result->tokens[0], $source))->toBe('<!--[if (gte mso 9)]>')
            ->and($result->tokens[9]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::text($result->tokens[9], $source))->toBe('<![endif]-->')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple conditional comments in sequence', function (): void {
        $source = '<!--[if IE]>IE<![endif]--><!--[if !IE]>Not IE<![endif]-->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(6)
            ->and($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[1], $source))->toBe('IE')
            ->and($result->tokens[2]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and($result->tokens[3]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[4], $source))->toBe('Not IE')
            ->and($result->tokens[5]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('conditional comment with text before and after', function (): void {
        $source = 'before<!--[if IE]>IE<![endif]-->after';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(5)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[0], $source))->toBe('before')
            ->and($result->tokens[1]['type'])->toBe(TokenType::ConditionalCommentStart)
            ->and($result->tokens[2]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[2], $source))->toBe('IE')
            ->and($result->tokens[3]['type'])->toBe(TokenType::ConditionalCommentEnd)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Text)
            ->and(Token::text($result->tokens[4], $source))->toBe('after')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('unclosed conditional start', function (): void {
        $source = '<!--[if IE no close';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentStart);
    });

    test('unclosed conditional end', function (): void {
        $source = '<![endif no close';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::ConditionalCommentEnd);
    });
});
