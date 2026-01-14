<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\LexerError;
use Forte\Lexer\State;

describe('Nested Echo Errors', function (): void {
    test('nested echo in echo', function (): void {
        $source = '{{ $iAmEchoOne {{ $iAmEchoTwo }} }}';
        $lexer = new Lexer($source);

        expect($lexer->tokenize()->tokens)->toBeArray();
    });

    test('nested raw echo in echo', function (): void {
        // {{ $var {!! $raw !!} }}
        $source = '{{ $var {!! $raw !!} }}';
        $lexer = new Lexer($source);

        expect($lexer->tokenize()->tokens)->toBeArray();
    });

    test('nested triple echo in raw echo', function (): void {
        // {!! $var {{{ $triple }}} !!}
        $source = '{!! $var {{{ $triple }}} !!}';
        $lexer = new Lexer($source);

        expect($lexer->tokenize()->tokens)->toBeArray();
    });
});

describe('Error Display and Creation', function (): void {
    test('error message format', function (): void {
        $error = LexerError::unexpectedNestedEcho(State::EchoContent, 15);
        $message = $error->getMessage();

        expect($message)->toContain('15')
            ->and($message)->toContain('UnexpectedNestedEcho')
            ->and($message)->toContain('EchoContent');
    });

    test('error creation methods', closure: function (): void {
        $error1 = LexerError::unexpectedNestedEcho(State::EchoContent, 10);
        expect($error1->reason)->toBe(ErrorReason::UnexpectedNestedEcho)
            ->and($error1->offset)->toBe(10);

        $error2 = LexerError::unexpectedEof(State::RawEchoContent, 20);
        expect($error2->reason)->toBe(ErrorReason::UnexpectedEof)
            ->and($error2->offset)->toBe(20);

        $error3 = LexerError::unclosedString(State::EchoContent, 30);
        expect($error3->reason)->toBe(ErrorReason::UnclosedString)
            ->and($error3->offset)->toBe(30);

        $error4 = LexerError::unclosedComment(State::Comment, 40);
        expect($error4->reason)->toBe(ErrorReason::UnclosedComment)
            ->and($error4->offset)->toBe(40);
    });

    test('error properties are readonly', function (): void {
        $error = LexerError::unexpectedEof(State::Data, 100);

        expect($error->state)->toBe(State::Data)
            ->and($error->reason)->toBe(ErrorReason::UnexpectedEof)
            ->and($error->offset)->toBe(100);
    });
});
