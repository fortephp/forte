<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('Multibyte Tokenization', function (): void {
    it('handles UTF-8 BOM at start', function (): void {
        $source = "\u{FEFF}Hello World";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles UTF-8 BOM with echo', function (): void {
        $source = "\u{FEFF}{{ \$var }}";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(4);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles Chinese characters in text', function (): void {
        $source = 'Some text: 测试 some more text';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles Japanese in echo', function (): void {
        $source = '{{ $日本語 }}';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles mixed CJK characters', function (): void {
        $source = '日本語">{{ $中文 }}';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles search ellipsis', function (): void {
        $source = 'Search…';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles guillemets', function (): void {
        $source = '««';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles corner brackets', function (): void {
        $source = '【】';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles emoji in text', function (): void {
        $source = '🐘🐘🐘🐘';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles specific high unicode code points', function (): void {
        $grinningFace = json_decode('"\u{1F600}"');
        $linearB = json_decode('"\u{10000}"');
        $snowman = json_decode('"\u{2603}"');

        $source = "$grinningFace $linearB $snowman";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles high code points in echo', function (): void {
        $grinningFace = json_decode('"\u{1F600}"');
        $linearB = json_decode('"\u{10000}"');
        $snowman = json_decode('"\u{2603}"');

        $source = "{{ '$grinningFace$linearB$snowman' }}";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles emoji in echo', function (): void {
        $source = "{{ '🎉' }}";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles mixed emoji and text', function (): void {
        $source = '测试 content with émojis 🎉';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles UTF-16 LE BOM', function (): void {
        $source = "\xFF\xFEHello";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles UTF-16 BE BOM', function (): void {
        $source = "\xFE\xFFHello";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles overlong null encoding', function (): void {
        $source = "Before\xC0\x80After";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles various invalid UTF-8 sequences', function (string $source): void {
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toBeArray();

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    })->with([
        "Test\xE0\x80\x80Text",    // Invalid 3-byte sequence
        "Test\xF0\x80\x80\x80Text", // Invalid 4-byte sequence
        "Test\x80\x80Text",         // Unexpected continuation bytes
        "Test\xC0Text",             // Incomplete sequence
        "Test\xFF\xFFText",         // Invalid start bytes
    ]);

    it('handles various control characters', function (): void {
        $source = "Test\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0FTest";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles multibyte in all three echo variants', function (): void {
        $source = "{{ '测试' }} text {!! '🎉' !!} more {{{ '日本語' }}}";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles multibyte near braces', function (): void {
        $source = "{{ '测试{{ nested }} test' }}";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles 4-byte emoji sequences', function (): void {
        $source = "Text {{ '👨‍👩‍👧‍👦' }} more";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('exact byte offsets for multibyte characters', function (): void {
        $source = '测试{{ $var }}';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens[0]['type'])->toBe(TokenType::Text)
            ->and($tokens[0]['start'])->toBe(0)
            ->and($tokens[0]['end'])->toBe(6)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['start'])->toBe(6)
            ->and($tokens[1]['end'])->toBe(8)
            ->and(Token::text($tokens[0], $source))->toBe('测试');
    });
});
