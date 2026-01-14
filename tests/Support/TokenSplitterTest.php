<?php

declare(strict_types=1);

use Forte\Support\TokenSplitter;

class SemicolonSplitter extends TokenSplitter
{
    public function split(string $value): array
    {
        return $this->splitIntoParts($value);
    }

    protected function isSplitCharacter(?string $char): bool
    {
        return $char === ';';
    }

    protected function advancePastSplitCharacter(): void
    {
        $this->advance();
    }

    /**
     * @param  array<int, string>  $parts
     * @return array<int, string>
     */
    protected function processFinalResult(array $parts): array
    {
        return array_values(array_filter($parts, fn ($part) => $part !== ''));
    }
}

describe('Token Splitter Parser', function (): void {
    it('handles custom delimiters', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('foo;bar;baz');

        expect($result)->toBe(['foo', 'bar', 'baz']);
    });

    it('respects strings when splitting', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('foo;"bar;baz";qux');

        expect($result)->toBe(['foo', '"bar;baz"', 'qux']);
    });

    it('respects parentheses when splitting', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('foo;func(a;b);bar');

        expect($result)->toBe(['foo', 'func(a;b)', 'bar']);
    });

    it('respects brackets when splitting', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('foo;[1;2;3];bar');

        expect($result)->toBe(['foo', '[1;2;3]', 'bar']);
    });

    it('respects braces when splitting', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('foo;{a;b};bar');

        expect($result)->toBe(['foo', '{a;b}', 'bar']);
    });

    it('handles deeply nested structures', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('a;func([{x;y}]);b');

        expect($result)->toBe(['a', 'func([{x;y}])', 'b']);
    });

    it('handles empty parts', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split(';;foo;;bar;;');

        expect($result)->toBe(['foo', 'bar']);
    });

    it('handles single element', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('foo');

        expect($result)->toBe(['foo']);
    });

    it('handles empty string', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('');

        expect($result)->toBe([]);
    });

    it('handles mixed quotes and nested structures', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('a;"func(x;y)";[1;2];b');

        expect($result)->toBe(['a', '"func(x;y)"', '[1;2]', 'b']);
    });

    it('handles escaped quotes in strings', function (): void {
        $splitter = new SemicolonSplitter;
        $result = $splitter->split('a;"foo\\"bar;baz";c');

        expect($result)->toBe(['a', '"foo\\"bar;baz"', 'c']);
    });
});
