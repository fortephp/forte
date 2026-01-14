<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

function tokenizeAndVerify(string $source): array
{
    $lexer = new Lexer($source, Directives::acceptAll());
    $result = $lexer->tokenize();

    $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
    expect($reconstructed)->toBe($source, 'Reconstruction mismatch for: '.json_encode($source));

    return $result->tokens;
}

describe('Basic Tokenization', function (): void {
    test('tokenizes simple text', function (): void {
        $lexer = new Lexer('Hello world');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and($tokens[0]['start'])->toBe(0)
            ->and($tokens[0]['end'])->toBe(11)
            ->and(Token::text($tokens[0], 'Hello world'))->toBe('Hello world');
    });

    test('handles empty input', function (): void {
        $lexer = new Lexer('');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toBeEmpty();
    });

    test('token length calculation', function (): void {
        $lexer = new Lexer('Test');
        $tokens = $lexer->tokenize()->tokens;

        expect(Token::length($tokens[0]))->toBe(4)
            ->and(Token::isEmpty($tokens[0]))->toBeFalse();
    });

    test('token type checking', function (): void {
        $lexer = new Lexer('Test');
        $tokens = $lexer->tokenize()->tokens;

        expect(Token::is($tokens[0], TokenType::Text))->toBeTrue()
            ->and(Token::is($tokens[0], TokenType::EchoStart))->toBeFalse();
    });

    test('token isAny helper', function (): void {
        $lexer = new Lexer('Test');
        $tokens = $lexer->tokenize()->tokens;

        expect(Token::isAny($tokens[0], [TokenType::Text, TokenType::EchoStart]))->toBeTrue()
            ->and(Token::isAny($tokens[0], [TokenType::EchoStart, TokenType::Directive]))->toBeFalse();
    });

    test('token toString helper', function (): void {
        $lexer = new Lexer('Test');
        $tokens = $lexer->tokenize()->tokens;

        expect(Token::toString($tokens[0]))->toBe('Text[0:4]');
    });

    test('zero-width tokens', function (): void {
        $token = Token::create(TokenType::SyntheticClose, 10, 10);

        expect(Token::length($token))->toBe(0)
            ->and(Token::isEmpty($token))->toBeTrue();
    });
});

describe('Lexer Fault Tolerance', function (): void {
    describe('Incomplete Blade Delimiters', function (): void {
        it('handles incomplete echo start', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '{{',
            '{{{',
            '{{--',
            '{!!',
            '{{ ',
            '{{ $',
            '{{ $x',
            '{!! ',
            '{!! $x',
        ]);

        it('handles incomplete echo with content', function (): void {
            $tokens = tokenizeAndVerify('{{ $variable');
            expect($tokens)->not->toBeEmpty();

            $tokens = tokenizeAndVerify('{!! $html');
            expect($tokens)->not->toBeEmpty();

            $tokens = tokenizeAndVerify('{{{ $escaped');
            expect($tokens)->not->toBeEmpty();
        });

        it('handles mismatched echo delimiters', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '{{ $x !!}',
            '{!! $x }}',
            '{{{ $x }}',
            '{{ $x }}}',
        ]);
    });

    describe('Incomplete Blade Comments', function (): void {
        it('handles incomplete blade comments', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '{{--',
            '{{-- comment',
            '{{-- comment --',
            '{{-- }} --',
            '{{-- {!! !!} --',
        ]);
    });

    describe('Incomplete HTML Elements', function (): void {
        it('handles incomplete opening tags', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<',
            '<d',
            '<div',
            '<div ',
            '<div c',
            '<div class',
            '<div class=',
            '<div class="',
            '<div class="foo',
            '<div class="foo"',
            '<div class="foo" ',
        ]);

        it('handles incomplete closing tags', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '</',
            '</d',
            '</div',
            '</div ',
        ]);

        it('handles orphan angle brackets', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<',
            '>',
            '< >',
            '> <',
            '<<',
            '>>',
            '<><',
            '><>',
        ]);
    });

    describe('Incomplete HTML Comments', function (): void {
        it('handles incomplete HTML comments', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<!--',
            '<!-',
            '<!-- comment',
            '<!-- comment -',
            '<!-- comment --',
            '<!-->',
            '<!--->',
        ]);

        it('handles bogus comments', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<!>',
            '<!x>',
            '<!DOCTYPE',
            '<!DOCTYPE ',
            '<!DOCTYPE html',
        ]);
    });

    describe('IE Conditional Comments', function (): void {
        it('handles incomplete conditional comments', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<!--[if',
            '<!--[if IE',
            '<!--[if IE]>',
            '<!--[if IE]>content',
            '<![endif]',
            '<![endif]-->',
        ]);

        it('handles complete conditional comments', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<!--[if IE]>content<![endif]-->',
            '<!--[if lt IE 9]><html class="legacy"><![endif]-->',
            '<!--[if !IE]><!--><p>Non-IE</p><!--<![endif]-->',
        ]);
    });

    describe('Incomplete PHP Blocks', function (): void {
        it('handles incomplete PHP syntax', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<?',
            '<?p',
            '<?php',
            '<?php ',
            '<?php echo',
            '<?php echo $x',
            '<?php echo $x;',
            '<?=',
            '<?= $x',
        ]);

        it('handles PHP blocks with blade-like content', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<?php echo "{{ $x }}"; ?>',
            '<?php if ($x): ?><div><?php endif; ?>',
            '<?php /* {{ $x }} */ ?>',
        ]);
    });

    describe('Incomplete Directives', function (): void {
        it('handles incomplete directive syntax', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '@',
            '@i',
            '@if',
            '@if(',
            '@if($x',
            '@if($x)',
            '@foreach',
            '@foreach(',
            '@foreach($x',
            '@foreach($x as',
            '@foreach($x as $y',
        ]);

        it('handles escaped directives', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '@@',
            '@@if',
            '@@if($x)',
            '@@@if',
        ]);
    });

    describe('CDATA Sections', function (): void {
        it('handles incomplete CDATA', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<![CDATA[',
            '<![CDATA[content',
            '<![CDATA[content]',
            '<![CDATA[content]]',
        ]);

        it('handles complete CDATA', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<![CDATA[content]]>',
            '<![CDATA[ <div> ]]>',
            '<![CDATA[ {{ $x }} ]]>',
        ]);
    });

    describe('Generic Type Arguments (TSX)', function (): void {
        it('handles incomplete generic types', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<Map<',
            '<Map<Rec',
            '<Map<Rec>',
            '<Map<Rec>>',
            '<Map<<Rec',
            '<Map<<Rec<Rec<<>',
        ]);

        it('handles complete generic types', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<Table<User> />',
            '<Map<Record<string, number>>> data="ok"></Map>',
            '<List<Array<Item>> items={data} />',
        ]);
    });

    describe('Alpine.js Attributes', function (): void {
        it('handles Alpine-style attributes', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div :class>',
            '<div @click>',
            '<div :class="{ active: isActive }">',
            '<div @click="handle()">',
            '<div @click.prevent="open = !open">',
            '<div x-data="{ open: false }">',
            '<div :class @click :disabled>',
        ]);
    });

    describe('Dynamic Attributes', function (): void {
        it('handles Blade in attributes', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div {{ $attrs }}>',
            '<div {{ $a }}="{{ $b }}">',
            '<div {{ $a }}={{ $b }}>',
            '<div {!! $attrs !!}>',
            '<div class="{{ $class }}">',
            '<div data-json=\'{"a":"{{ $b }}"}\'>',
        ]);
    });

    describe('Script and Style Tags', function (): void {
        it('handles script with incomplete Blade', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<script>{{',
            '<script>{{ $x',
            '<script>{!! $x',
            '<script>@if($x)',
        ]);

        it('handles script with complete Blade', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<script>{{ $x }}</script>',
            '<script>{!! json_encode($data) !!}</script>',
            '<script>if(a<b){console.log("{{ $x }}")}</script>',
        ]);

        it('handles style with Blade', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<style>{{ $x }}</style>',
            '<style>.a { color: {{ $color }}; }</style>',
            '<style>.{{ $class }} { }</style>',
        ]);
    });

    describe('Quote Edge Cases', function (): void {
        it('handles complex quoting', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div class="{{ $a ? "b" : \'c\' }}">',
            "<div onclick=\"alert('{{ \$x }}')\">",
            '<div data-json=\'{"key":"{{ $val }}"}\'>',
            '<div title="He said \"hello\"">',
        ]);

        it('handles unclosed quotes', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div class="',
            "<div class='",
            '<div class="foo',
            "<div class='foo",
        ]);
    });

    describe('Whitespace Edge Cases', function (): void {
        it('handles various whitespace', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '',
            ' ',
            '  ',
            "\n",
            "\t",
            "\r\n",
            "  \n  \t  ",
        ]);

        it('handles whitespace in constructs', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            "{{\n\$x\n}}",
            "@if(\$x)\n@endif",
            "<div\nclass=\"foo\"\n>",
            "<!--\ncomment\n-->",
        ]);
    });

    describe('Unicode Content', function (): void {
        it('handles unicode in various contexts', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div>{{ $émoji }}</div>',
            '<div class="日本語">{{ $中文 }}</div>',
            '{{ $emoji }}🎉{{ $more }}',
            '<div title="Ñoño">',
            '<!-- Комментарий -->',
        ]);
    });

    describe('Nested Constructs', function (): void {
        it('handles deeply nested structures', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div><div><div>{{ $deep }}</div></div></div>',
            '@if($a)@if($b)@if($c)x@endif@endif@endif',
            '{{ {{ $x }} }}',
            '{{{ $x }}}',
            '{{{{{{ $x }}}}}}',
        ]);
    });

    describe('Mixed Contexts', function (): void {
        it('handles multiple constructs together', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div {{ $x }} @if($y) class="a" @endif>{{ $content }}</div>',
            '@if($x)<p @endif>',
            '<?php if ($x): ?><div @if($y)>{{ $z }}</div>@endif<?php endif; ?>',
            '<!-- {{ $blade }} --> @if($x) {!! $html !!} @endif',
        ]);
    });

    describe('Self-closing and Void Elements', function (): void {
        it('handles self-closing elements', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<br/>',
            '<br />',
            '<input type="{{ $type }}"/>',
            '<img src="{{ $src }}" />',
            '<x-component :prop="$value"/>',
        ]);
    });

    describe('Component Syntax', function (): void {
        it('handles Blade components', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<x-alert />',
            '<x-alert>content</x-alert>',
            '<x-slot name="header">{{ $title }}</x-slot>',
            '<x-admin.panel>',
            '<livewire:counter />',
        ]);
    });

    describe('Extremely Long Content', function (): void {
        it('handles very long strings', function (): void {
            $long = str_repeat('a', 10000);
            tokenizeAndVerify("<div>{$long}</div>");
            tokenizeAndVerify('{{ $x }}'.str_repeat('{{ $x }}', 100));
        });

        it('handles many tokens', function (): void {
            $manyDivs = str_repeat('<div></div>', 100);
            tokenizeAndVerify($manyDivs);

            $manyEchoes = str_repeat('{{ $x }}', 100);
            tokenizeAndVerify($manyEchoes);
        });
    });

    describe('DOCTYPE Edge Cases', function (): void {
        it('handles DOCTYPE variations', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<!DOCTYPE html>',
            '<!doctype html>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN">',
            '<!DOCTYPE html><html {{ $attrs }}>',
            '<!doctype ',
            '<!DOCTYPE',
        ]);
    });

    describe('XML Declarations', function (): void {
        it('handles XML declarations', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<?xml version="1.0"?>',
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?xml',
            '<?xml ',
        ]);
    });

    describe('Malformed Structures', function (): void {
        it('handles malformed HTML', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '<div<span>',
            '<div>>',
            '<<div>',
            '<div></span>',
            '<div><span></div></span>',
            '<div class class="foo">',
            '<div =value>',
        ]);

        it('handles malformed Blade', function (string $input): void {
            tokenizeAndVerify($input);
        })->with([
            '@if @else @endif',
            '@if($x)@if($y)@endif',
            '{{ $x {{ $y }} }}',
            '@if(@if($x))',
        ]);
    });
});

describe('Text Tokenization', function (): void {
    test('tokenizes simple text', function (): void {
        $lexer = new Lexer('Hello world');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and($tokens[0]['start'])->toBe(0)
            ->and($tokens[0]['end'])->toBe(11);
    });

    test('handles empty input', function (): void {
        $lexer = new Lexer('');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toBeEmpty();
    });

    test('single brace in text', function (): void {
        $lexer = new Lexer('This { is } text');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text);
    });

    test('simple echo', function (): void {
        $lexer = new Lexer('{{ $var }}');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[1]['start'])->toBe(2)
            ->and($tokens[1]['end'])->toBe(8)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);
    });

    test('echo with text', function (): void {
        $lexer = new Lexer('Hello {{ $name }}!');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(5)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoEnd)
            ->and($tokens[4]['type'])->toBe(TokenType::Text);
    });

    test('nested echo', function (): void {
        $lexer = new Lexer("{{ ['a' => ['b' => 'c']] }}");
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);
    });

    test('abandoned echo (error recovery)', function (): void {
        $lexer = new Lexer('{{ $var');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent);
    });

    test('empty echo', function (): void {
        $lexer = new Lexer('{{}}');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoEnd);
    });

    test('raw echo', function (): void {
        $lexer = new Lexer('{!! $html !!}');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[1]['start'])->toBe(3)
            ->and($tokens[1]['end'])->toBe(10)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoEnd);
    });

    test('abandoned raw echo (error recovery)', function (): void {
        $lexer = new Lexer('{!! $html');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent);
    });

    test('triple echo', function (): void {
        $lexer = new Lexer('{{{ $var }}}');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoEnd);
    });

    test('nested triple echo (construct collision)', function (): void {
        $lexer = new Lexer('{{{ {{{ $var }}} }}}');
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->tokens)->toHaveCount(6)
            ->and($result->tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($result->tokens[2]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($result->tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($result->tokens[4]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and($result->tokens[5]['type'])->toBe(TokenType::Text);
    });

    test('abandoned triple echo (error recovery)', function (): void {
        $lexer = new Lexer('{{{ $var');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent);
    });

    test('multiple echo types', function (): void {
        $lexer = new Lexer('{{ $a }} text {!! $b !!} more {{{ $c }}}');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(11)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd)
            ->and($tokens[3]['type'])->toBe(TokenType::Text)
            ->and($tokens[4]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[5]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[6]['type'])->toBe(TokenType::RawEchoEnd)
            ->and($tokens[7]['type'])->toBe(TokenType::Text)
            ->and($tokens[8]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[9]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[10]['type'])->toBe(TokenType::TripleEchoEnd);
    });

    test('exact token positions match Rust implementation', function (): void {
        $source = 'Hello {{ $name }}!';
        $lexer = new Lexer($source);
        $tokens = $lexer->tokenize()->tokens;

        expect(Token::text($tokens[0], $source))->toBe('Hello ')
            ->and(Token::text($tokens[2], $source))->toBe(' $name ')
            ->and(Token::text($tokens[4], $source))->toBe('!');
    });

    test('error recovery behavior matches Rust', function (): void {
        $lexer = new Lexer('{{ unclosed');
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent);
    });
});
