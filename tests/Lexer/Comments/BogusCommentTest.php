<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;

describe('Bogus Comments', function (): void {
    test('bogus comment question mark', function (): void {
        $source = '<'.'? bogus ?'.'>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(1)
            ->and($result->tokens[0]['type'])->toBe(TokenType::BogusComment)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('bogus comment dash', function (): void {
        $source = '<- bogus ->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens)->toHaveCount(1)
            ->and($result->tokens[0]['type'])->toBe(TokenType::BogusComment)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('bogus vs php tag', function (): void {
        $source = "<?php echo 'test'; ?>";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::PhpTagStart);
    });

    test('bogus vs short echo tag', function (): void {
        $source = '<?= $var ?>';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and($result->tokens[0]['type'])->toBe(TokenType::PhpTagStart);
    });

    test('bogus vs html comment', function (): void {
        $source = '<!->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::BogusComment);
    });

    test('dash bogus vs html comment', function (): void {
        $source = '<- test ->';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens[0]['type'])->toBe(TokenType::BogusComment);
    });

    test('unclosed bogus comment', function (): void {
        $source = '<'.'? no close';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(1)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
