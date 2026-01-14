<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\State;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('Echo Construct Collisions', function (): void {
    test('echo inside echo', function (): void {
        $source = '{{ $x {{ $y }} }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::EchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });

    test('raw echo inside echo', function (): void {
        $source = '{{ $x {!! $y !!} }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::RawEchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });

    test('triple echo inside echo', function (): void {
        $source = '{{ $x {{{ $y }}} }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });

    test('directive inside echo', function (): void {
        $source = '{{ $x @if($y) }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(5)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::Directive)
            ->and($tokens[3]['type'])->toBe(TokenType::DirectiveArgs)
            ->and($tokens[4]['type'])->toBe(TokenType::Text);
    });
});

describe('Raw Echo Construct Collisions', function (): void {
    test('echo inside raw echo', function (): void {
        $source = '{!! $x {{ $y }} !!}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::RawEchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::EchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });

    test('raw echo inside raw echo', function (): void {
        $source = '{!! $x {!! $y !!} !!}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::RawEchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::RawEchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::RawEchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });
});

describe('Triple Echo Construct Collisions', function (): void {
    test('echo inside triple echo', function (): void {
        $source = '{{{ $x {{ $y }} }}}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::TripleEchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::EchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });

    test('triple echo inside triple echo', function (): void {
        $source = '{{{ $x {{{ $y }}} }}}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::TripleEchoContent);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::TripleEchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::TripleEchoEnd)
            ->and($tokens[5]['type'])->toBe(TokenType::Text);
    });
});

describe('Partial Content Handling', function (): void {
    test('partial content before collision', function (): void {
        $source = '{{ $var = "value"; {{ $nested }} }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(6)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent);

        $content = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($content)->toBe(' $var = "value"; ');
    });

    test('no partial content when collision is immediate', function (): void {
        $source = '{{ @if($x) }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(5)
            ->and($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::Directive)
            ->and($tokens[3]['type'])->toBe(TokenType::DirectiveArgs)
            ->and($tokens[4]['type'])->toBe(TokenType::Text);
    });

    test('user example: nested echo', function (): void {
        $source = '{{ $iAmEchoOne {{ $iAmEchoTwo }} }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision);

        $tokens = $result->tokens;
        expect($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[3]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[4]['type'])->toBe(TokenType::EchoEnd);
    });
});

describe('PHP Tag Construct Collisions', function (): void {
    test('php tag inside echo', function (): void {
        $source = '{{ $var <?php echo "test"; ?> }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });

    test('php short tag inside raw echo', function (): void {
        $source = '{!! $html <?= $value ?> !!}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::RawEchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });

    test('php tag inside triple echo', function (): void {
        $source = '{{{ $var <? $old ?> }}}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::TripleEchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });

    test('php tag with partial content before collision', function (): void {
        $source = '{{ $x = 1; <?php $y = 2; ?> }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision);

        $tokens = $result->tokens;
        $contentTokens = array_filter($tokens, fn ($t) => $t['type'] === TokenType::EchoContent);
        expect($contentTokens)->not->toBeEmpty();

        $firstContent = array_values($contentTokens)[0];
        $content = substr($source, $firstContent['start'], $firstContent['end'] - $firstContent['start']);
        expect($content)->toBe(' $x = 1; ');
    });

    test('no false positive for less-than in echo', function (): void {
        $source = '{{ $a < $b }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens[0]['type'])->toBe(TokenType::EchoStart)
            ->and($tokens[1]['type'])->toBe(TokenType::EchoContent)
            ->and($tokens[2]['type'])->toBe(TokenType::EchoEnd);
    });
});

describe('Blade Block Construct Collisions', function (): void {
    test('php block inside echo', function (): void {
        $source = '{{ $var @php $x = 1; @endphp }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1, 'Expected construct collision error for @php in echo')
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });

    test('verbatim block inside echo', function (): void {
        $source = '{{ $var @verbatim {{ raw }} @endverbatim }}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1, 'Expected construct collision error for @verbatim in echo')
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::EchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });

    test('php block inside raw echo', function (): void {
        $source = '{!! $html @php echo "test"; @endphp !!}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1, 'Expected construct collision error for @php in raw echo')
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::RawEchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });

    test('verbatim block inside triple echo', function (): void {
        $source = '{{{ $var @verbatim test @endverbatim }}}';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1, 'Expected construct collision error for @verbatim in triple echo')
            ->and($result->errors[0]->reason)->toBe(ErrorReason::ConstructCollision)
            ->and($result->errors[0]->state)->toBe(State::TripleEchoContent)
            ->and($result->tokens)->not->toBeEmpty();
    });
});
