<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Parser\Directives\Directives;

describe('Incremental Tokenization', function (): void {
    beforeEach(function (): void {
        $this->source = file_get_contents(__DIR__.'/../Fixtures/Lexer/incremental.blade.php');
        $this->totalLen = strlen($this->source);
    });

    test('incremental forward tokenization', function (): void {
        $length = $this->totalLen;
        $positions = $length > 1000
            ? array_map(fn ($i) => max(1, min($length, (int) (($i * $length) / 100))), range(0, 99))
            : range(1, $length);

        foreach ($positions as $position) {
            $prefix = substr($this->source, 0, $position);

            $lexer = new Lexer($prefix, Directives::acceptAll());
            $start = microtime(true);
            $result = $lexer->tokenize();
            $elapsed = microtime(true) - $start;

            expect($elapsed)->toBeLessThanOrEqual(2.0, "Timeout at position {$position}");

            $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);
            expect($reconstructed)->toBe($prefix, "Reconstruction mismatch at position {$position}");
        }
    });

    test('incremental edge positions', function (): void {
        $edgePositions = [];
        $specialChars = ['@', '{', '}', '<', '>', '!', '-', '?'];

        for ($pos = 0; $pos < $this->totalLen; $pos++) {
            if (in_array($this->source[$pos], $specialChars, true)) {
                foreach ([max(0, $pos - 1), $pos, $pos + 1, $pos + 2] as $testPos) {
                    if ($testPos > 0 && $testPos <= $this->totalLen) {
                        $edgePositions[$testPos] = true;
                    }
                }
            }
        }

        $edgePositions = array_keys($edgePositions);
        sort($edgePositions);

        foreach ($edgePositions as $i) {
            $prefix = substr($this->source, 0, $i);

            $lexer = new Lexer($prefix, Directives::acceptAll());
            $start = microtime(true);
            $result = $lexer->tokenize();
            $elapsed = microtime(true) - $start;

            expect($elapsed)->toBeLessThanOrEqual(2.0, "Timeout at edge position {$i}");

            $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);
            expect($reconstructed)->toBe($prefix, "Reconstruction mismatch at edge position {$i}");
        }

        expect(count($edgePositions))->toBeGreaterThan(0);
    });

    test('utf-8 multibyte boundary handling', function (): void {
        $sources = [
            '<div>日本語テスト</div>',
            '<span class="émoji">🎉🚀💻</span>',
            '{{ $日本語 }}',
            '@if($文字列) 中文 @endif',
            '<p>Ñoño café naïve</p>',
            '<!-- コメント -->',
            '{!! $中文变量 !!}',
        ];

        foreach ($sources as $source) {
            $length = strlen($source);

            for ($i = 1; $i <= $length; $i++) {
                $prefix = substr($source, 0, $i);

                $lexer = new Lexer($prefix, Directives::acceptAll());
                $result = $lexer->tokenize();

                $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);
                expect($reconstructed)->toBe($prefix, "UTF-8 reconstruction mismatch at byte {$i} in: {$source}");
            }
        }
    });

    test('construct truncation at every byte', function (): void {
        $constructs = [
            '{{',
            '{{ $x }}',
            '{{{',
            '{{{ $x }}}',
            '{!!',
            '{!! $x !!}',
            '@if',
            '@if($x)',
            '@endif',
            '@foreach($items as $item)',
            '@endforeach',
            '{{--',
            '{{-- comment --}}',
            '<!--',
            '<!-- comment -->',
            '<div',
            '<div>',
            '</div>',
            '<div class="test">',
            '<input type="text" />',
        ];

        foreach ($constructs as $construct) {
            $length = strlen($construct);

            for ($i = 1; $i <= $length; $i++) {
                $prefix = substr($construct, 0, $i);

                $lexer = new Lexer($prefix, Directives::acceptAll());
                $result = $lexer->tokenize();

                $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);
                expect($reconstructed)->toBe($prefix, "Construct truncation mismatch at byte {$i} in: {$construct}");
            }
        }
    });

    test('pathological inputs do not hang', function (): void {
        $pathological = [
            str_repeat('{', 100),
            str_repeat('}', 100),
            str_repeat('{{', 50),
            str_repeat('}}', 50),
            str_repeat('@', 100),
            str_repeat('<', 100),
            str_repeat('>', 100),
            str_repeat('{{{{{{{{{{', 10),
            str_repeat('}}}}}}}}}}', 10),
            str_repeat('{!! ', 25),
            str_repeat(' !!}', 25),
            str_repeat('<!--', 25),
            str_repeat('-->', 25),
            str_repeat('<div>', 50).str_repeat('</div>', 50),
            str_repeat('<div><span>', 25).str_repeat('</span></div>', 25),
            '{{'.str_repeat(' ', 1000).'}}',
            '@if('.str_repeat('$x && ', 100).'$y)',
        ];

        foreach ($pathological as $source) {
            $start = microtime(true);
            $lexer = new Lexer($source, Directives::acceptAll());
            $result = $lexer->tokenize();
            $elapsed = microtime(true) - $start;

            expect($elapsed)->toBeLessThanOrEqual(2.0, 'Pathological input caused timeout: '.substr($source, 0, 50).'...');

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source, 'Pathological reconstruction mismatch');
        }
    });

    test('empty and minimal inputs', function (): void {
        $minimal = [
            '',
            ' ',
            "\n",
            "\t",
            "\r\n",
            '<',
            '>',
            '{',
            '}',
            '@',
            '!',
            '-',
            '?',
            '<>',
            '{}',
            '{{',
            '}}',
            '{!',
            '!}',
            '@i',
            '<!',
            '->',
            '--',
        ];

        foreach ($minimal as $source) {
            $lexer = new Lexer($source, Directives::acceptAll());
            $result = $lexer->tokenize();

            $reconstructed = Token::reconstructFromTokens($result->tokens, $source);
            expect($reconstructed)->toBe($source, 'Minimal input mismatch for: '.json_encode($source));
        }
    });

    test('deeply nested structures', function (): void {
        $nested = [
            '<div><div><div><div><div>content</div></div></div></div></div>',
            '@if(true) @if(true) @if(true) inner @endif @endif @endif',
            '@foreach($a as $b) @foreach($c as $d) @foreach($e as $f) x @endforeach @endforeach @endforeach',
            '<div class="{{ $a ? ($b ? $c : $d) : ($e ? $f : $g) }}">test</div>',
            '{{ $a ?? $b ?? $c ?? $d ?? $e }}',
            '@if($a) @elseif($b) @elseif($c) @elseif($d) @else @endif',
        ];

        foreach ($nested as $source) {
            $length = strlen($source);
            $positions = $length > 100
                ? array_map(fn ($i) => (int) (($i * $length) / 50), range(0, 49))
                : range(0, $length);

            foreach ($positions as $position) {
                $prefix = substr($source, 0, $position);

                $lexer = new Lexer($prefix, Directives::acceptAll());
                $start = microtime(true);
                $result = $lexer->tokenize();
                $elapsed = microtime(true) - $start;

                expect($elapsed)->toBeLessThanOrEqual(2.0, "Nested structure timeout at position {$position}");

                $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);
                expect($reconstructed)->toBe($prefix, "Nested reconstruction mismatch at position {$position}");
            }
        }
    });

    test('mixed blade and html edge cases', function (): void {
        $edgeCases = [
            '<div {{ $attr }}>',
            '<div {!! $attr !!}>',
            '<div @class([])>',
            '<{{ $tag }}>',
            '</{{ $tag }}>',
            '<div class="{{ $a }}" data-x="{{ $b }}" id="{{ $c }}">',
            '<x-component :prop="$value" />',
            '<x-component {{ $attributes }}>',
            '@php $x = "<div>"; @endphp',
            '{{ "<div>" }}',
            '{!! "</script>" !!}',
            '<script>var x = @json($data);</script>',
            '<div @if($x) class="active" @endif>',
            '<input @checked($val) @disabled($dis) />',
        ];

        foreach ($edgeCases as $source) {
            $length = strlen($source);

            for ($i = 1; $i <= $length; $i++) {
                $prefix = substr($source, 0, $i);

                $lexer = new Lexer($prefix, Directives::acceptAll());
                $result = $lexer->tokenize();

                $reconstructed = Token::reconstructFromTokens($result->tokens, $prefix);
                expect($reconstructed)->toBe($prefix, "Edge case mismatch at byte {$i} in: {$source}");
            }
        }
    });
});
