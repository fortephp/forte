<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('PHP Tags', function (): void {
    test('basic php tag', function (): void {
        $source = <<<'SOURCE'
<?php
$var = 'value';
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toBe("\n\$var = 'value';\n");

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('short echo php tag', function (): void {
        $source = <<<'SOURCE'
<?= $var ?>
SOURCE;
        $source = rtrim($source, "\n");

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toBe(' $var ');

        $tagStart = substr($source, $tokens[0]['start'], $tokens[0]['end'] - $tokens[0]['start']);
        expect($tagStart)->toBe('<?=');
    });

    test('short php tag', function (): void {
        $source = <<<'SOURCE'
<? echo 'hello'; ?>
SOURCE;
        $source = rtrim($source, "\n");

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::BogusComment)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('uppercase php tag', function (): void {
        $source = <<<'SOURCE'
<?PHP
$var = 1;
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);

        $tagStart = substr($source, $tokens[0]['start'], $tokens[0]['end'] - $tokens[0]['start']);
        expect($tagStart)->toBe('<?PHP');
    });

    test('mixed case php tag', function (): void {
        $source = <<<'SOURCE'
<?PhP
$var = 1;
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);
    });

    test('empty php tag', function (): void {
        $source = '<?php?>';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpTagEnd);
    });

    test('unclosed php tag', function (): void {
        $source = '<?php'."\n".'$var = \'value\';'."\n".'No closing tag';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent);
    });

    test('php tag with blade syntax', function (): void {
        $source = <<<'SOURCE'
<?php
$data = ['name' => '{{ $user }}'];
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toContain('{{ $user }}');
    });

    test('php tag with blade directives', function (): void {
        $source = '<?php'."\n".'@if($x) { echo \'test\'; }'."\n".'?'.'>';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toContain('@if($x)');
    });

    test('php tag word boundary', function (): void {
        $source = '<?phps'."\n".'$var = 1;'."\n".'?'.'>';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PIStart)
            ->and($tokens[2]['type'])->toBe(TokenType::PIEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source); // PIStart, Text, PIEnd
    });

    test('multiple php tags', function (): void {
        $source = <<<'SOURCE'
<?php
$one = 1;
?>
<p>HTML</p>
<?php
$two = 2;
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('mixed php tags', function (): void {
        $source = <<<'SOURCE'
<?php echo 'a'; ?> text <?= $var ?> more
SOURCE;
        $source = rtrim($source, "\n");

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('php tag preserves whitespace', function (): void {
        $source = <<<'SOURCE'
<?php
    $var = 'value';
        $another = 'test';
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('php tag with surrounding blade', function (): void {
        $source = <<<'SOURCE'
{{ $before }}
<?php
$code = 'test';
?>
{{ $after }}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('xml declaration is properly tokenized', function (): void {
        $source = '<'.'?xml version="1.0"?'.'>';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        $tokens = $result->tokens;
        expect(count($tokens))->toBe(8)
            ->and($tokens[0]['type'])->toBe(TokenType::DeclStart)
            ->and($tokens[2]['type'])->toBe(TokenType::AttributeName)
            ->and($tokens[7]['type'])->toBe(TokenType::DeclEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('question mark in php content', function (): void {
        $source = <<<'SOURCE'
<?php
$test = true ? 'yes' : 'no';
?>
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::PhpTagStart)
            ->and($tokens[1]['type'])->toBe(TokenType::PhpContent)
            ->and($tokens[2]['type'])->toBe(TokenType::PhpTagEnd);

        $phpContent = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($phpContent)->toContain('?');
    });
});
