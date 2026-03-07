<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\Token;
use Forte\Parser\ParserOptions;

function assertTokenRangesAreBounded(string $source, array $tokens): void
{
    $len = strlen($source);
    $lastStart = -1;

    foreach ($tokens as $idx => $token) {
        expect($token['start'])->toBeGreaterThanOrEqual(0, "Negative token start at {$idx}")
            ->toBeLessThanOrEqual($len, "Token start past input at {$idx}");
        expect($token['end'])->toBeGreaterThanOrEqual($token['start'], "Token end before start at {$idx}")
            ->toBeLessThanOrEqual($len, "Token end past input at {$idx}");
        expect($token['start'])->toBeGreaterThanOrEqual($lastStart, "Non-monotonic token start at {$idx}");

        $lastStart = $token['start'];
    }
}

function assertNodeRangesAreBounded(Document $doc, int $len): void
{
    $doc->walk(function ($node) use ($len): void {
        $start = $node->startOffset();
        $end = $node->endOffset();

        if ($start === -1 || $end === -1) {
            return;
        }

        expect($start)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($len);
        expect($end)->toBeGreaterThanOrEqual($start)->toBeLessThanOrEqual($len);
    });
}

function assertCharacterFidelity(string $source): void
{
    $lexer = new Lexer($source);
    $lexerResult = $lexer->tokenize();

    assertTokenRangesAreBounded($source, $lexerResult->tokens);
    expect(Token::reconstructFromTokens($lexerResult->tokens, $source))->toBe($source);

    $doc = Document::parse($source, ParserOptions::defaults()->withAllDirectives());
    expect($doc->render())->toBe($source);
    assertTokenRangesAreBounded($source, $doc->getTokens());
    assertNodeRangesAreBounded($doc, strlen($source));

    $rewritten = $doc->rewriteWith(static function (): void {});
    expect($rewritten->render())->toBe($source);

    $roundTrip = Document::parse($doc->render(), ParserOptions::defaults()->withAllDirectives());
    expect($roundTrip->render())->toBe($source);
}

describe('Character Fidelity Recovery', function (): void {
    $cases = [
        '<{`\\',
        '<{`\\@if($x)',
        "v9|{{ \$x }}<@/\t\n><{</span><x-alert>n`t)\\<!--[]></<d}a<\r\nA?>\t@verbati",
        "<d<\t{{",
        "<d<\t7{{",
        "<m /\t",
        "<m/\t>",
        "<m /\t>",
        "<@/\n",
        "@endforeach\r\n'(?\r\n3@verbatimk?>Xf@if(\$x)9</ @e",
        "<\n],wire:model=\"foo\"",
        "<!--[]></<d}a<<?=@\n",
        "<x-alert :class=\"\$x\" {{ \$attributes }} / \t",
        "@verbatim <div>{{ \$x }}</div> @endverbatim<?=\r\n",
        "<?php if (\$x) { echo '<div>'; } ?>{{ \$y",
    ];

    it('preserves every byte for malformed and mixed recovery cases', function () use ($cases): void {
        foreach ($cases as $source) {
            assertCharacterFidelity($source);
        }
    });

    it('preserves every byte at incremental prefixes for high-risk recovery inputs', function () use ($cases): void {
        foreach (array_slice($cases, 0, 8) as $source) {
            $len = strlen($source);
            for ($i = 1; $i <= $len; $i++) {
                assertCharacterFidelity(substr($source, 0, $i));
            }
        }
    });
});
