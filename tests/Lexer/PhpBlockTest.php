<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('PHP Blocks', function (): void {
    test('basic php block', function (): void {
        $source = "@php\n\$var = 'value';\n@endphp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlock)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpBlockEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toBe("\n\$var = 'value';\n");

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('php block with blade syntax', function (): void {
        $source = "@php\n\$data = ['name' => '{{ \$user }}'];\n@endphp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlock)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpBlockEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toContain('{{ $user }}');
    });

    test('php directive with args', function (): void {
        $source = '@php($condition)';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs);
    });

    test('uppercase php', function (): void {
        $source = "@PHP\n\$var = 1;\n@ENDPHP";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlock)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpBlockEnd);
    });

    test('mixed case php', function (): void {
        $source = "@PhP\n\$var = 1;\n@EnDpHp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlock);
    });

    test('empty php block', function (): void {
        $source = '@php@endphp';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlockEnd);
    });

    test('unclosed php block', function (): void {
        $source = "@php\n\$var = 'value';\nNo closing tag";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::UnexpectedEof);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlock);
    });

    test('endphp without php', function (): void {
        $source = '@endphp';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockEnd);
    });

    test('phpfoo should not start php block', function (): void {
        $source = "@phpfoo\n\$var = 1;\n@endphp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpBlockEnd);
    });

    test('endphpfoo should not end php block', function (): void {
        $source = "@php\n\$var = 1;\n@endphpfoo\n@endphp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpBlock)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpBlockEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toContain('@endphpfoo');
    });

    test('multiple php blocks', function (): void {
        $source = "@php\n\$one = 1;\n@endphp\n<p>HTML</p>\n@php\n\$two = 2;\n@endphp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    it('preserves whitespace', function (): void {
        $source = "@php\n    \$var = 'value';\n        \$another = 'test';\n@endphp";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('php block with surrounding blade', function (): void {
        $source = "{{ \$before }}\n@php\n\$code = 'test';\n@endphp\n{{ \$after }}";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('@php@endphp emits correct tokens', function (): void {
        $source = '@php@endphp';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($result->tokens[0]['start'])->toBe(0)
            ->and($result->tokens[0]['end'])->toBe(4)

            ->and($result->tokens[1]['type'])->toBe(TokenType::PhpBlockEnd)
            ->and($result->tokens[1]['start'])->toBe(4)
            ->and($result->tokens[1]['end'])->toBe(11);
    });

    test('@php with content then @endphp', function (): void {
        $source = "@php\n\$x = 1;\n@endphp";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::PhpBlock)
            ->and($result->tokens[2]['type'])->toBe(TokenType::PhpBlockEnd);
    });

    test('@endphp outside PHP block still emits PhpBlockEnd', function (): void {
        $source = '🐘 @endphp 🐘';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[1]['type'])->toBe(TokenType::PhpBlockEnd)
            ->and($result->tokens[2]['type'])->toBe(TokenType::Text);
    });

    test('goofy php/endphp on character boundaries', function (): void {
        $source = 'text @php$x=1;@endphp more';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(5)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Text)
            ->and($result->tokens[1]['type'])->toBe(TokenType::PhpBlockStart)
            ->and($result->tokens[2]['type'])->toBe(TokenType::PhpBlock)
            ->and($result->tokens[3]['type'])->toBe(TokenType::PhpBlockEnd)
            ->and($result->tokens[4]['type'])->toBe(TokenType::Text);
    });
});
