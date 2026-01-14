<?php

declare(strict_types=1);

use Forte\Lexer\ErrorReason;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;

describe('Verbatim Basic Tests', function (): void {
    test('basic verbatim block', function (): void {
        $source = "@verbatim\n{{ \$var }}\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::VerbatimStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::VerbatimEnd);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toBe("\n{{ \$var }}\n");

        $reconstructed = Token::reconstructFromTokens($tokens, $source);
        expect($reconstructed)->toBe($source);
    });

    test('multiple blade constructs in verbatim', function (): void {
        $source = "@verbatim\n{{ \$echo }}\n{!! \$raw !!}\n{{{ \$triple }}}\n@if(\$x)\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::VerbatimStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::VerbatimEnd);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toContain('{{ $echo }}')
            ->and($text)->toContain('{!! $raw !!}')
            ->and($text)->toContain('{{{ $triple }}}')
            ->and($text)->toContain('@if($x)');
    });

    test('blade comments in verbatim', function (): void {
        $source = "@verbatim\n{{-- This is a comment --}}\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[1]['type'])->toBe(TokenType::Text);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toBe("\n{{-- This is a comment --}}\n");
    });
});

describe('Verbatim Case Insensitive Tests', function (): void {
    test('uppercase verbatim', function (): void {
        $source = "@VERBATIM\n{{ \$var }}\n@ENDVERBATIM";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[0]['type'])->toBe(TokenType::VerbatimStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text)
            ->and($tokens[2]['type'])->toBe(TokenType::VerbatimEnd);
    });

    test('mixed case verbatim', function (): void {
        $source = "@VeRbAtIm\n{{ \$var }}\n@EnDvErBaTiM";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[1]['type'])->toBe(TokenType::Text);
    });

    test('various endverbatim case variants', function (): void {
        $cases = [
            '@endverbatim',
            '@ENDVERBATIM',
            '@EndVerbatim',
            '@endVERBATIM',
        ];

        foreach ($cases as $endTag) {
            $source = "@verbatim\n{{ \$var }}\n{$endTag}";

            $registry = Directives::withDefaults();
            $lexer = new Lexer($source, $registry);
            $result = $lexer->tokenize();

            expect($result->errors)->toBeEmpty("Failed for: {$endTag}")
                ->and($result->tokens)->toHaveCount(3, "Failed for: {$endTag}")
                ->and($result->tokens[2]['type'])->toBe(TokenType::VerbatimEnd, "Failed for: {$endTag}");
        }
    });
});

describe('Verbatim Directive Detection Tests', function (): void {
    test('only endverbatim recognized in verbatim mode', function (): void {
        $source = "@verbatim\n@if(\$x)\n@section('test')\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toContain('@if($x)')
            ->and($text)->toContain("@section('test')");
    });
});

describe('Verbatim Word Boundary Tests', function (): void {
    test('endverbatim with suffix should not end verbatim', function (): void {
        $source = "@verbatim\n{{ \$var }}\n@endverbatimfoo\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toContain('@endverbatimfoo');
    });

    test('endverbatim with underscore should not end verbatim', function (): void {
        $source = "@verbatim\n{{ \$var }}\n@endverbatim_test\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toContain('@endverbatim_test');
    });
});

describe('Verbatim Content Preservation Tests', function (): void {
    test('preserves whitespace', function (): void {
        $source = "@verbatim\n    {{ \$var }}\n        {!! \$raw !!}\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('empty verbatim content', function (): void {
        $source = '@verbatim@endverbatim';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::VerbatimStart)
            ->and($tokens[1]['type'])->toBe(TokenType::VerbatimEnd);
    });

    test('verbatim with whitespace between tags', function (): void {
        $source = "@verbatim   \n\n   @endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[1]['type'])->toBe(TokenType::Text);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toBe("   \n\n   ");
    });
});

describe('Verbatim Nested/Multiple Blocks Tests', function (): void {
    test('multiple verbatim blocks', function (): void {
        $source = "@verbatim\n{{ \$one }}\n@endverbatim\n<p>Normal</p>\n@verbatim\n{{ \$two }}\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('verbatim inside verbatim', function (): void {
        $source = "@verbatim\n@verbatim\n{{ \$var }}\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toContain('@verbatim')
            ->and($text)->toContain('{{ $var }}');
    });
});

describe('Verbatim Edge Cases', function (): void {
    test('unclosed verbatim', function (): void {
        $source = "@verbatim\n{{ \$var }}\nNo closing tag";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toHaveCount(1)
            ->and($result->errors[0]->reason)->toBe(ErrorReason::UnexpectedEof);

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(2)
            ->and($tokens[0]['type'])->toBe(TokenType::VerbatimStart)
            ->and($tokens[1]['type'])->toBe(TokenType::Text);
    });

    test('endverbatim without verbatim', function (): void {
        $source = '@endverbatim';

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(1)
            ->and($tokens[0]['type'])->toBe(TokenType::VerbatimEnd);
    });

    test('verbatim with special chars', function (): void {
        $source = "@verbatim\n<script>var x = \"{{ \$var }}\";</script>\n@endverbatim";

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty()
            ->and(Token::reconstructFromTokens($result->tokens, $source))->toBe($source);
    });

    test('javascript example', function (): void {
        $source = <<<'BLADE'
@verbatim
<script>
    var app = new Vue({
        data: {
            message: '{{ greeting }}'
        }
    });
</script>
@endverbatim
BLADE;

        $registry = Directives::withDefaults();
        $lexer = new Lexer($source, $registry);
        $result = $lexer->tokenize();

        expect($result->errors)->toBeEmpty();

        $tokens = $result->tokens;
        expect($tokens)->toHaveCount(3)
            ->and($tokens[1]['type'])->toBe(TokenType::Text);

        $text = substr($source, $tokens[1]['start'], $tokens[1]['end'] - $tokens[1]['start']);
        expect($text)->toContain("'{{ greeting }}'");
    });

    test('@verbatim@endverbatim emits correct tokens', function (): void {
        $source = '@verbatim@endverbatim';
        $lexer = new Lexer($source);
        $result = $lexer->tokenize();

        expect($result->tokens)->toHaveCount(2)
            ->and($result->tokens[0]['type'])->toBe(TokenType::VerbatimStart)
            ->and($result->tokens[1]['type'])->toBe(TokenType::VerbatimEnd);
    });
});
