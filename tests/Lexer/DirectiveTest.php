<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('Simple Directives', function (): void {
    test('simple directive', function (): void {
        $source = '@if';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($tokens[0], $source))->toBe('@if')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive with args', function (): void {
        $source = '@if($condition)';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($tokens[0], $source))->toBe('@if')
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::text($tokens[1], $source))->toBe('($condition)')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive with complex args', function (): void {
        $source = "@section('title', ['class' => 'active'])";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Directives with Text', function (): void {
    test('directive with surrounding text', function (): void {
        $source = 'Before @if After';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[0], $source))->toBe('Before ')
            ->and($tokens[1]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($tokens[1], $source))->toBe('@if')
            ->and($tokens[2]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[2], $source))->toBe(' After')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('multiple directives', function (): void {
        $source = '@if @endif';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($tokens[0], $source))->toBe('@if')
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[1], $source))->toBe(' ')
            ->and($tokens[2]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($tokens[2], $source))->toBe('@endif')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Unknown Directives', function (): void {
    test('unknown directive', function (): void {
        $source = '@unknownDirective';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[0], $source))->toBe('@unknownDirective')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('at sign in email', function (): void {
        $source = 'Email: user@example.com';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('at sign alone', function (): void {
        $source = '@ ';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[0], $source))->toBe('@')
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and(Token::text($tokens[1], $source))->toBe(' ')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Nested Arguments', function (): void {
    test('directive nested parens', function (): void {
        $source = "@section('name', fn(\$x) => \$x)";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::text($tokens[1], $source))->toBe("('name', fn(\$x) => \$x)")
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive nested arrays', function (): void {
        $source = "@component('alert', ['data' => ['nested' => ['value']]])";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive args with quotes', function (): void {
        $source = "@if('It\\'s a \"test\"')";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Common Directives', function (): void {
    test('foreach directive', function (): void {
        $source = '@foreach($items as $item)';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($tokens[0], $source))->toBe('@foreach')
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('section directive', function (): void {
        $source = "@section('content')";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(0)
            ->and($result->tokens)->toHaveCount(2)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('csrf directive', function (): void {
        $source = '@csrf';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Edge Cases', function (): void {
    describe('Directive with Dot', function (): void {
        test('directive followed by dot at EOF', function (): void {
            $source = '<div @click.';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source, 'Failed to reconstruct @click.');
        });

        test('it tokenizes directive-like things followed by dot', function (): void {
            $source = '<div id="root" :class="{ open: isOpen }" @click.';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive followed by various punctuation', function (string $source): void {
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();
            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);

            expect($reconstructed)->toBe($source);
        })->with([
            '<div @click.',
            '<div @click.prevent',
            '<div @if.',
            '<div @foreach.',
            '<div @click(',
            '<div @click>',
        ]);

        test('directive with vue modifiers', function (): void {
            $source = '<div @click.prevent.stop="handler">';
            $lexer = new Lexer($source, Directives::acceptAll());
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source)
                ->and(collect($result->tokens)->contains(fn ($token) => $token['type'] === TokenType::Directive))->toBeTrue();
        });

        test('directive with alpine modifiers', function (): void {
            $source = '<div @click.outside="close">';
            $lexer = new Lexer($source, Directives::acceptAll());
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });
    });

    describe('Directive Partial Cases', function (): void {
        test('partial directive at EOF', function (): void {
            $source = '<div @';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive without name', function (): void {
            $source = '<div @ >';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive with special chars after name', function (string $source): void {
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();
            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);

            expect($reconstructed)->toBe($source);
        })->with([
            '<div @click!',
            '<div @click?',
            '<div @click;',
            '<div @click:',
            '<div @click[',
            '<div @click]',
        ]);
    });

    describe('Directive with Args Edge Cases', function (): void {
        test('directive with empty args', function (): void {
            $source = '@if()';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive with unclosed args at EOF', function (): void {
            $source = '@if($condition';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            expect($result->errors)->not->toBeEmpty();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive with args containing dots', function (): void {
            $source = '@if($user->profile->isActive())';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive with args containing function calls', function (): void {
            $source = '@if(count($items) > 0)';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });
    });

    describe('Directive Position Edge Cases', function (): void {
        test('directive at start of file', function (): void {
            $source = '@section("content")';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source)
                ->and($result->tokens[0]['type'])->toBe(TokenType::Directive);
        });

        test('directive at end of file', function (): void {
            $source = 'Some text @endsection';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);

            $lastToken = $result->tokens[count($result->tokens) - 1];
            expect($lastToken['type'])->toBe(TokenType::Directive);
        });

        test('directive in middle of text', function (): void {
            $source = 'Before @if($x) After';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);

            $types = array_map(fn ($t) => $t['type'], $result->tokens);
            expect($types)->toContain(TokenType::Text)
                ->and($types)->toContain(TokenType::Directive);
        });

        test('consecutive directives', function (): void {
            $source = '@if($x)@foreach($items as $item)@endif';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);

            $directiveCount = collect($result->tokens)
                ->filter(fn ($t) => $t['type'] === TokenType::Directive)
                ->count();

            expect($directiveCount)->toBe(3);
        });
    });

    describe('Directive Word Boundaries', function (): void {
        test('email with @ should not be directive', function (): void {
            $source = 'Contact: user@example.com';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::Directive))
                ->toBeFalse();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('@ in middle of word should not be directive', function (): void {
            $source = 'some@thing';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::Directive))
                ->toBeFalse();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('@ after space should be directive', function (): void {
            $source = 'text @if($x)';
            $lexer = new Lexer($source);
            $result = $lexer->tokenize();

            expect(collect($result->tokens)->contains(fn ($t) => $t['type'] === TokenType::Directive))
                ->toBeTrue();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });
    });

    describe('Case-Insensitive Matching', function (): void {
        test('directive case insensitive', function (): void {
            $source = '@IF($x) @ForEach($items) @CSRF';
            $registry = Directives::withDefaults();
            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            expect($tokens)->toHaveCount(7)
                ->and($tokens[0]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[0], $source))->toBe('@IF')
                ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs)
                ->and($tokens[3]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[3], $source))->toBe('@ForEach')
                ->and($tokens[4]['type'])->toBe(TokenType::DirectiveArgs)
                ->and($tokens[6]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[6], $source))->toBe('@CSRF');

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('directive mixed case', function (): void {
            $source = "@CsRf @EnDiF @sEcTiOn('test')";
            $registry = Directives::withDefaults();
            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            expect($tokens[0]['type'])->toBe(TokenType::Directive)
                ->and($tokens[2]['type'])->toBe(TokenType::Directive)
                ->and($tokens[4]['type'])->toBe(TokenType::Directive); // @CsRf

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });
    });

    describe('Dynamic Registration', function (): void {
        test('dynamic directive registration', function (): void {
            $source = '@myCustomDirective @anotherDirective';

            $registry = new Directives;
            $registry->registerDirective('myCustomDirective');
            $registry->registerDirective('anotherDirective');

            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            // Custom directives should be recognized
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[0], $source))->toBe('@myCustomDirective')
                ->and($tokens[2]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[2], $source))->toBe('@anotherDirective');

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('dynamic registration case insensitive', function (): void {
            $source = '@MYCUSTOMDIRECTIVE @myCustomDirective @MyCuStOmDiReCtIvE';

            // Register in one case
            $registry = new Directives;
            $registry->registerDirective('myCustomDirective');

            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            expect($tokens)->toHaveCount(5)
                ->and($tokens[0]['type'])->toBe(TokenType::Directive)
                ->and($tokens[2]['type'])->toBe(TokenType::Directive)
                ->and($tokens[4]['type'])->toBe(TokenType::Directive);

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });
    });

    describe('Accept-All Mode', function (): void {
        test('accept all mode', function (): void {
            $source = '@unknownDirective @customThing @whatever';

            $registry = Directives::acceptAll();
            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            expect($tokens)->toHaveCount(5)
                ->and($tokens[0]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[0], $source))->toBe('@unknownDirective')
                ->and($tokens[2]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[2], $source))->toBe('@customThing')
                ->and($tokens[4]['type'])->toBe(TokenType::Directive)
                ->and(Token::text($tokens[4], $source))->toBe('@whatever');

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('accept all with args', function (): void {
            $source = "@unknownDirective('arg1', 'arg2')";

            $registry = Directives::acceptAll();
            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            expect($tokens)->toHaveCount(2)
                ->and($tokens[0]['type'])->toBe(TokenType::Directive)
                ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs);

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });

        test('accept all still respects email-like patterns', function (): void {
            $source = 'Email: user@example.com';

            $registry = Directives::acceptAll();
            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty();
            $tokens = $result->tokens;

            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]['type'])->toBe(TokenType::Text);

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source);
        });
    });

    test('directive at start of line', function (): void {
        $source = "@if(\$x)\n@endif";
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[2]['type'])->toBe(TokenType::Text)
            ->and($tokens[3]['type'])->toBe(TokenType::Directive)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('directive exact offsets', function (): void {
        $source = 'Text @if(true) More';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();
        $tokens = $result->tokens;

        expect($tokens[0]['start'])->toBe(0)
            ->and($tokens[0]['end'])->toBe(5)
            ->and($tokens[1]['start'])->toBe(5)
            ->and($tokens[1]['end'])->toBe(8)
            ->and($tokens[2]['start'])->toBe(8)
            ->and($tokens[2]['end'])->toBe(14)
            ->and($tokens[3]['start'])->toBe(14)
            ->and($tokens[3]['end'])->toBe(19)
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });
});

describe('Directive Error Handling', function (): void {
    test('unclosed directive args', function (): void {
        $source = "@section('name";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::UnexpectedEof);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs);
    });

    test('unclosed nested directive args', function (): void {
        $source = "@section(['name' => 'value'";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::UnexpectedEof);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($tokens[1]['type'])->toBe(TokenType::DirectiveArgs);
    });
});

describe('Directive Whitespace Tokenization', function (): void {
    test('emits whitespace token between directive name and args', function (): void {
        $source = '@if   ($count === 1)';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Directive)
            ->and(Token::text($result->tokens[0], $source))->toBe('@if')
            ->and($result->tokens[1]['type'])->toBe(TokenType::Whitespace)
            ->and(Token::text($result->tokens[1], $source))->toBe('   ')
            ->and($result->tokens[2]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::text($result->tokens[2], $source))->toBe('($count === 1)');
    });

    test('handles single space between directive and args', function (): void {
        $source = '@foreach ($items as $item)';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(3)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($result->tokens[1]['type'])->toBe(TokenType::Whitespace)
            ->and($result->tokens[2]['type'])->toBe(TokenType::DirectiveArgs)
            ->and(Token::text($result->tokens[1], $source))->toBe(' ');
    });

    test('handles no whitespace between directive and args', function (): void {
        $source = '@if($count === 1)';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Directive)
            ->and($result->tokens[1]['type'])->toBe(TokenType::DirectiveArgs);
    });

    test('handles directive without args', function (): void {
        $source = '@csrf';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(1)
            ->and($result->tokens[0]['type'])->toBe(TokenType::Directive);
    });
});
