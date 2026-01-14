<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('Echo with Heredoc/Comment Skipping', function (): void {
    test('echo with heredoc containing braces', function (): void {
        $source = <<<'SOURCE'
{{ $thing = <<<'THING'
    {{ }} { }
THING
}}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);

        $content = Token::text($tokens[1], $source);
        expect($content)->toContain("<<<'THING'")
            ->and($content)->toContain('{{ }} { }')
            ->and($content)->toContain('THING')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with block comment containing braces', function (): void {
        $source = '{{ $x /* {{ }} */ }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with line comment containing braces', function (): void {
        $source = <<<'SOURCE'
{{ $x // {{ }}
}}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with hash comment containing braces', function (): void {
        $source = <<<'SOURCE'
{{ $x # {{ }}
}}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with single-quoted string containing braces', function (): void {
        $source = "{{ \$x = '{{ }}' }}";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with double-quoted string containing braces', function (): void {
        $source = '{{ $x = "{{ }}" }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('echo with backtick string containing braces', function (): void {
        $source = '{{ $x = `echo "{{ }}"` }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('all the painful things', function (): void {
        $source = <<<'SOURCE'
{{ $thing = <<<'THING'
    {{ }} { }
THING;
/*
*/
asdf
// asdf
#asdf
}}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);

        $content = Token::text($tokens[1], $source);
        expect($content)->toContain("<<<'THING'")
            ->and($content)->toContain('{{ }} { }')
            ->and($content)->toContain('THING;')
            ->and($content)->toContain('/*')
            ->and($content)->toContain('*/')
            ->and($content)->toContain('// asdf')
            ->and($content)->toContain('#asdf')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Raw Echo with Heredoc/Comment Skipping', function (): void {
    test('raw echo with heredoc containing braces', function (): void {
        $source = <<<'SOURCE'
{!! $thing = <<<'THING'
    {!! !!} { }
THING;
!!}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('raw echo with block comment containing braces', function (): void {
        $source = '{!! $x /* {!! !!} */ !!}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('raw echo with line comment containing braces', function (): void {
        $source = <<<'SOURCE'
{!! $x // {!! !!}
!!}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('raw echo with single-quoted string containing braces', function (): void {
        $source = "{!! \$x = '{!! !!}' !!}";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Triple Echo with Heredoc/Comment Skipping', function (): void {
    test('triple echo with heredoc containing braces', function (): void {
        $source = <<<'SOURCE'
{{{ $thing = <<<'THING'
    {{{ }}} { }
THING;
}}}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('triple echo with block comment containing braces', function (): void {
        $source = '{{{ $x /* {{{ }}} */ }}}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('triple echo with line comment containing braces', function (): void {
        $source = <<<'SOURCE'
{{{ $x // {{{ }}}
}}}
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('triple echo with nested triple echo properly skipped in string', function (): void {
        $source = "{{{ \$x = '{{{ }}}' }}}";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Directive Args with Heredoc/Comment Skipping', function (): void {
    test('directive args with heredoc containing parens', function (): void {
        $source = <<<'SOURCE'
@php($thing = <<<'THING'
    ( ) ( )
THING
)
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with block comment containing parens', function (): void {
        $source = '@if($x /* ( ) */ )';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with line comment containing parens', function (): void {
        $source = <<<'SOURCE'
@if($x // ( )
)
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with string containing parens', function (): void {
        $source = "@section('name (with) parens')";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with hash comment containing parens', function (): void {
        $source = <<<'SOURCE'
@if($x # ( )
)
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with backtick string containing parens', function (): void {
        $source = '@php(`echo "( )"`)';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with double-quoted string containing parens', function (): void {
        $source = '@section("name (with) parens")';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with nested parens and mixed skip scenarios', function (): void {
        $source = <<<'SOURCE'
@section('outer', [
    'data' => '( )',
    'heredoc' => <<<'DOC'
        ( ) ( )
DOC
    /* ( ) */,
    'nested' => ['( )']
])
SOURCE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs);

        $args = Token::text($tokens[1], $source);
        expect($args)->toContain("'outer'")
            ->and($args)->toContain("'data' => '( )'")
            ->and($args)->toContain("<<<'DOC'")
            ->and($args)->toContain('/* ( ) */')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});
