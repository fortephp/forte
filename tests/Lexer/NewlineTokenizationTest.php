<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('LF Newlines', function (): void {
    it('handles LF in text', function (): void {
        $source = "Line 1\nLine 2\nLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles LF with echo', function (): void {
        $source = "Line 1\n{{ \$var }}\nLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect(count($tokens))->toBeGreaterThanOrEqual(5);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles trailing LF', function (): void {
        $source = "Content\n";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });
});

describe('CRLF Newlines (Windows)', function (): void {
    it('handles CRLF in text', function (): void {
        $source = "Line 1\r\nLine 2\r\nLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles CRLF with echo', function (): void {
        $source = "Line 1\r\n{{ \$var }}\r\nLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect(count($tokens))->toBeGreaterThanOrEqual(5);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles trailing CRLF', function (): void {
        $source = "Content\r\n";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });
});

describe('CR Newlines (old Mac)', function (): void {
    it('handles CR in text', function (): void {
        $source = "Line 1\rLine 2\rLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles CR with echo', function (): void {
        $source = "Line 1\r{{ \$var }}\rLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect(count($tokens))->toBeGreaterThanOrEqual(5);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles trailing CR', function (): void {
        $source = "Content\r";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });
});

describe('Mixed Newlines', function (): void {
    it('handles mixed LF and CRLF', function (): void {
        $source = "Line 1\nLine 2\r\nLine 3";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles all three newline types', function (): void {
        $source = "Line 1\nLine 2\r\nLine 3\rLine 4";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles mixed newlines with echos', function (): void {
        $source = "Text\n{{ \$a }}\r\n{!! \$b !!}\r{{{ \$c }}}";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect(count($tokens))->toBeGreaterThan(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });
});

describe('Edge Cases', function (): void {
    it('handles consecutive newlines', function (): void {
        $source = "Text\n\n\nMore text";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles consecutive mixed newlines', function (): void {
        $source = "Text\n\r\n\rMore text";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('handles only newlines', function (): void {
        $source = "\n\r\n\r";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1);

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    it('exact byte offsets with different newline styles', function (): void {
        $source = "Line 1\nLine 2\r\nLine 3\rEnd";
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source)
            ->and($tokens[0]['start'])->toBe(0);
    });
});
