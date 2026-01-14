<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('JSX/TSX-Style Generics and Attributes', function (): void {
    function countTokenType(array $tokens, int $type): int
    {
        return count(array_filter($tokens, fn ($t) => $t['type'] === $type));
    }

    function getNthToken(array $tokens, int $type, int $n): ?array
    {
        return collect($tokens)
            ->filter(fn ($t) => $t['type'] === $type)
            ->values()
            ->get($n);
    }

    function getTokenText(string $source, array $token): string
    {
        return substr($source, $token['start'], $token['end'] - $token['start']);
    }

    describe('TSX Generic Types', function (): void {
        test('simple generic type', function (): void {
            $template = '<Table<User> />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::TsxGenericType))->toBe(1);

            $generic = getNthToken($result->tokens, TokenType::TsxGenericType, 0);
            expect($generic)->not->toBeNull()
                ->and(getTokenText($template, $generic))->toBe('<User>');
        });

        test('object type generic', function (): void {
            $template = '<Table<{ id: number }> />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::TsxGenericType))->toBe(1);

            $generic = getNthToken($result->tokens, TokenType::TsxGenericType, 0);
            expect($generic)->not->toBeNull()
                ->and(getTokenText($template, $generic))->toBe('<{ id: number }>');
        });

        test('nested generic types', function (): void {
            $template = '<Map<Record<string, Array<Foo>>> />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::TsxGenericType))->toBe(1);

            $generic = getNthToken($result->tokens, TokenType::TsxGenericType, 0);
            expect($generic)->not->toBeNull()
                ->and(getTokenText($template, $generic))->toBe('<Record<string, Array<Foo>>>');
        });
    });

    describe('JSX Attribute Values', function (): void {
        test('simple JSX attribute value', function (): void {
            $template = '<div data={users}>';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxAttributeValue, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('{users}');
        });

        test('JSX arrow function attribute', function (): void {
            $template = '<button onClick={() => handle()}>Click</button>';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxAttributeValue, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('{() => handle()}');
        });

        test('JSX object literal attribute (now Blade echo)', function (): void {
            $template = '<div config={{debug: true, mode: "dev"}}>test</div>';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::EchoStart))->toBe(1)
                ->and(countTokenType($result->tokens, TokenType::EchoEnd))->toBe(1)
                ->and(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(0);

            $echoContent = getNthToken($result->tokens, TokenType::EchoContent, 0);
            expect($echoContent)->not->toBeNull()
                ->and(getTokenText($template, $echoContent))->toBe('debug: true, mode: "dev"');
        });

        test('JSX array literal attribute', function (): void {
            $template = '<List items={[1, 2, 3]} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxAttributeValue, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('{[1, 2, 3]}');
        });

        test('JSX with parenthesized expression', function (): void {
            $template = '<div value=({computed}) />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxAttributeValue, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('({computed})');
        });
    });

    describe('JSX Shorthand Attributes', function (): void {
        test('simple shorthand attribute', function (): void {
            $template = '<input {disabled} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxShorthandAttribute))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxShorthandAttribute, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('{disabled}');
        });

        test('spread attributes', function (): void {
            $template = '<div {...props} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxShorthandAttribute))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxShorthandAttribute, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('{...props}');
        });

        test('conditional shorthand', function (): void {
            $template = '<div {count > 0} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxShorthandAttribute))->toBe(1);

            $jsx = getNthToken($result->tokens, TokenType::JsxShorthandAttribute, 0);
            expect($jsx)->not->toBeNull()
                ->and(getTokenText($template, $jsx))->toBe('{count > 0}');
        });
    });

    describe('Combined JSX/TSX', function (): void {
        test('TSX generic with JSX attributes', function (): void {
            $template = '<Table<User> data={users} {enabled} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::TsxGenericType))->toBe(1)
                ->and(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1)
                ->and(countTokenType($result->tokens, TokenType::JsxShorthandAttribute))->toBe(1);

            $generic = getNthToken($result->tokens, TokenType::TsxGenericType, 0);
            expect(getTokenText($template, $generic))->toBe('<User>');

            $jsxValue = getNthToken($result->tokens, TokenType::JsxAttributeValue, 0);
            expect(getTokenText($template, $jsxValue))->toBe('{users}');

            $jsxShorthand = getNthToken($result->tokens, TokenType::JsxShorthandAttribute, 0);
            expect(getTokenText($template, $jsxShorthand))->toBe('{enabled}');
        });

        test('lots of generics!', function (): void {
            $template = <<<'TEMPLATE'
<List<User> items={users} {enabled} />
<Table<{ id: number }> data={rows} renderItem={(item) => <div>{item.name}</div>} />
<Map<Record<string, Array<Foo>>> config={{debug: true}} />
TEMPLATE;

            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::TsxGenericType))->toBe(3)
                ->and(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBeGreaterThanOrEqual(3)
                ->and(countTokenType($result->tokens, TokenType::JsxShorthandAttribute))->toBe(1);
        });
    });

    describe('Edge Cases', function (): void {
        test('JSX not confused with Blade echo', function (): void {
            $template = '<div data={value}>{{ $blade }}</div>';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1)
                ->and(countTokenType($result->tokens, TokenType::EchoStart))->toBe(1)
                ->and(countTokenType($result->tokens, TokenType::EchoEnd))->toBe(1);
        });

        test('TSX not confused with HTML tags', function (): void {
            $template = '<div><User> is not generic but <Table<User> is</div>';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::TsxGenericType))->toBe(1);
        });

        test('JSX with comments', function (): void {
            $template = '<div data={/* comment */ value} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1);
        });

        test('JSX with template literals', function (): void {
            $template = '<div data={`hello ${name}`} />';
            $lexer = new Lexer($template, Directives::acceptAll());
            $result = $lexer->tokenize();

            expect(countTokenType($result->tokens, TokenType::JsxAttributeValue))->toBe(1);
        });
    });

    describe('TypeScript Generics Edge Cases', function (): void {
        it('handles incomplete TypeScript generics at EOF', function (): void {
            $template = '<Map<Record<string, Array<';
            $lexer = new Lexer($template);
            $result = $lexer->tokenize();

            $syntheticCloseCount = collect($result->tokens)
                ->filter(fn ($t) => $t['type'] === TokenType::SyntheticClose)
                ->count();

            expect($syntheticCloseCount)->toBe(4);
        });

        it('emits SyntheticClose before new tag in generic context', function (): void {
            $template = '<div<span>';
            $lexer = new Lexer($template);
            $result = $lexer->tokenize();

            $tokens = collect($result->tokens);

            $divIndex = $tokens->search(
                fn ($t) => $t['type'] === TokenType::TagName
                    && Token::text($t, $template) === 'div'
            );

            expect($divIndex)->not->toBeFalse('Should have div TagName')
                ->and($tokens->get($divIndex + 1)['type'])->toBe(TokenType::SyntheticClose)
                ->and($tokens->get($divIndex + 2)['type'])->toBe(TokenType::LessThan);
        });

        it('correctly handles attribute name followed by less-than', function (): void {
            $template = '<div class<';
            $lexer = new Lexer($template);
            $result = $lexer->tokenize();

            $tokens = collect($result->tokens);

            $classIndex = $tokens->search(
                fn ($t) => $t['type'] === TokenType::AttributeName
                    && Token::text($t, $template) === 'class'
            );

            expect($classIndex)->not->toBeFalse('Should have class AttributeName')
                ->and($tokens->get($classIndex + 1)['type'])->toBe(TokenType::SyntheticClose)
                ->and($tokens->get($classIndex + 2)['type'])->toBe(TokenType::LessThan);
        });

        it('handles multiple nested generics at EOF', function (): void {
            $template = '<Component<Foo<Bar<Baz<';
            $lexer = new Lexer($template);
            $result = $lexer->tokenize();

            $syntheticCloseCount = collect($result->tokens)
                ->filter(fn ($t) => $t['type'] === TokenType::SyntheticClose)
                ->count();

            expect($syntheticCloseCount)->toBe(5);
        });

        it('preserves source offsets with incomplete generics', function (): void {
            $template = '<Map<Record<string, Array<';
            $lexer = new Lexer($template);
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $template);
            expect($reconstructed)->toBe($template);
        });

        it('does not double-emit SyntheticClose for attribute followed by less-than', function (): void {
            $template = '<div foo<';
            $lexer = new Lexer($template);
            $result = $lexer->tokenize();

            $syntheticCloseCount = collect($result->tokens)
                ->filter(fn ($t) => $t['type'] === TokenType::SyntheticClose)
                ->count();

            expect($syntheticCloseCount)->toBe(2);
        });
    });
});
