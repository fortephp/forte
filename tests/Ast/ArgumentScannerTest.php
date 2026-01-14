<?php

declare(strict_types=1);

use Forte\Parser\Directives\ArgumentScanner;

describe('ArgumentScanner', function (): void {
    describe('countArguments', function (): void {
        it('counts basic comma-separated arguments', function (): void {
            expect(ArgumentScanner::countArguments('$a, $b, $c'))->toBe(3)
                ->and(ArgumentScanner::countArguments('$a'))->toBe(1)
                ->and(ArgumentScanner::countArguments(''))->toBe(0)
                ->and(ArgumentScanner::countArguments('   '))->toBe(0);
        });

        it('respects nested brackets', function (): void {
            $input = '["one, two", $var1, $var2], $hello, 12345.23, bar, baz, (1,2,3,4,), "foo, bar, baz"';
            expect(ArgumentScanner::countArguments($input))->toBe(7);

            $input = '[["one, two", $var1, $var2], $hello, 12345.23, bar, baz, (1,2,3,4,), "foo, bar, baz"]';
            expect(ArgumentScanner::countArguments($input))->toBe(1);
        });

        it('respects nested parentheses', function (): void {
            $input = '(["one, two", $var1, $var2], $hello, 12345.23, bar, baz, (1,2,3,4,), "foo, bar, baz")';
            expect(ArgumentScanner::countArguments($input))->toBe(1);

            $input = 'func($a, $b), other($c, $d)';
            expect(ArgumentScanner::countArguments($input))->toBe(2);
        });

        it('handles deeply nested structures', function (): void {
            $input = '[[[[[["one, two", $var1, $var2], $hello, 12345.23]]]]], [bar, baz, (1,2,3,4,), "foo, bar, baz"]';
            expect(ArgumentScanner::countArguments($input))->toBe(2);

            $input = '[[[[[["one, two", $var1, $var2], $hello, 12345.23]]]]], [bar, baz, (1,2,3,4,), "foo, bar, baz"], (true == false) ? $this : $that';
            expect(ArgumentScanner::countArguments($input))->toBe(3);
        });

        it('handles strings with commas', function (): void {
            expect(ArgumentScanner::countArguments('"hello, world", $var'))->toBe(2)
                ->and(ArgumentScanner::countArguments("'hello, world', \$var"))->toBe(2)
                ->and(ArgumentScanner::countArguments('"a, b, c"'))->toBe(1);
        });

        it('handles escaped quotes in strings', function (): void {
            expect(ArgumentScanner::countArguments('"hello \\"world\\", test", $var'))->toBe(2)
                ->and(ArgumentScanner::countArguments("'hello \\'world\\', test', \$var"))->toBe(2);
        });

        it('handles heredoc with commas inside', function (): void {
            $input = <<<'INPUT'
<<<EOT
Hello, world, test
EOT;, $other
INPUT;
            expect(ArgumentScanner::countArguments($input))->toBe(2);
        });

        it('handles nowdoc with commas inside', function (): void {
            $input = <<<'INPUT'
<<<'TXT'
Hello, world, test
TXT;, $other
INPUT;
            expect(ArgumentScanner::countArguments($input))->toBe(2);
        });

        it('handles mixed braces', function (): void {
            $input = '[$a, $b], {$c, $d}, ($e, $f)';
            expect(ArgumentScanner::countArguments($input))->toBe(3);
        });
    });

    describe('startsWithArray', function (): void {
        it('detects array notation', function (): void {
            expect(ArgumentScanner::startsWithArray('[1, 2, 3]'))->toBeTrue()
                ->and(ArgumentScanner::startsWithArray('  [1, 2, 3]'))->toBeTrue()
                ->and(ArgumentScanner::startsWithArray("\t\n[1, 2, 3]"))->toBeTrue();
        });

        it('returns false for non-array', function (): void {
            expect(ArgumentScanner::startsWithArray("'key'"))->toBeFalse()
                ->and(ArgumentScanner::startsWithArray('"key"'))->toBeFalse()
                ->and(ArgumentScanner::startsWithArray('$variable'))->toBeFalse()
                ->and(ArgumentScanner::startsWithArray('123'))->toBeFalse()
                ->and(ArgumentScanner::startsWithArray(''))->toBeFalse();
        });
    });

    describe('isSimpleString', function (): void {
        it('detects simple single-quoted strings', function (): void {
            expect(ArgumentScanner::isSimpleString("'hello'"))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString("'messages.welcome'"))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString("  'hello'  "))->toBeTrue();
        });

        it('detects simple double-quoted strings', function (): void {
            expect(ArgumentScanner::isSimpleString('"hello"'))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString('"messages.welcome"'))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString('  "hello"  '))->toBeTrue();
        });

        it('handles strings with escaped quotes', function (): void {
            expect(ArgumentScanner::isSimpleString("'hello\\'s world'"))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString('"hello \\"world\\""'))->toBeTrue();
        });

        it('returns false for complex expressions', function (): void {
            expect(ArgumentScanner::isSimpleString("'hello', 'world'"))->toBeFalse()
                ->and(ArgumentScanner::isSimpleString("['key']"))->toBeFalse()
                ->and(ArgumentScanner::isSimpleString('$variable'))->toBeFalse()
                ->and(ArgumentScanner::isSimpleString("'hello' . 'world'"))->toBeFalse()
                ->and(ArgumentScanner::isSimpleString(''))->toBeFalse();
        });
    });

    describe('hasSingleArgument', function (): void {
        it('returns true for single argument', function (): void {
            expect(ArgumentScanner::hasSingleArgument("'sidebar'"))->toBeTrue()
                ->and(ArgumentScanner::hasSingleArgument('$variable'))->toBeTrue()
                ->and(ArgumentScanner::hasSingleArgument('[1, 2, 3]'))->toBeTrue();
        });

        it('returns false for multiple arguments', function (): void {
            expect(ArgumentScanner::hasSingleArgument("'sidebar', 'default'"))->toBeFalse()
                ->and(ArgumentScanner::hasSingleArgument('$a, $b'))->toBeFalse();
        });
    });

    describe('getFirstArgument', function (): void {
        it('extracts first argument', function (): void {
            expect(ArgumentScanner::getFirstArgument("'sidebar', 'default'"))->toBe("'sidebar'")
                ->and(ArgumentScanner::getFirstArgument('$a, $b, $c'))->toBe('$a')
                ->and(ArgumentScanner::getFirstArgument('[1, 2], $other'))->toBe('[1, 2]');
        });

        it('handles single argument', function (): void {
            expect(ArgumentScanner::getFirstArgument("'only'"))->toBe("'only'")
                ->and(ArgumentScanner::getFirstArgument('$var'))->toBe('$var');
        });

        it('returns null for empty input', function (): void {
            expect(ArgumentScanner::getFirstArgument(''))->toBeNull()
                ->and(ArgumentScanner::getFirstArgument('   '))->toBeNull();
        });

        it('trims whitespace', function (): void {
            expect(ArgumentScanner::getFirstArgument('  $a  , $b'))->toBe('$a');
        });
    });

    describe('UTF-8 multi-byte handling', function (): void {
        it('counts arguments with multi-byte characters in strings', function (): void {
            expect(ArgumentScanner::countArguments('"こんにちは", $var'))->toBe(2)
                ->and(ArgumentScanner::countArguments('"你好世界", "测试"'))->toBe(2)
                ->and(ArgumentScanner::countArguments('"Hello 🌍", "World 🎉"'))->toBe(2)
                ->and(ArgumentScanner::countArguments('"Привет мир", "مرحبا", "שלום"'))->toBe(3);
        });

        it('handles UTF-8 in array keys', function (): void {
            $input = "['キー' => 'バリュー', 'другой' => 'значение']";
            expect(ArgumentScanner::countArguments($input))->toBe(1);
        });

        test('isSimpleString works with UTF-8', function (): void {
            expect(ArgumentScanner::isSimpleString('"日本語"'))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString("'العربية'"))->toBeTrue()
                ->and(ArgumentScanner::isSimpleString('"🎯 Target"'))->toBeTrue();
        });

        test('getFirstArgument preserves UTF-8', function (): void {
            expect(ArgumentScanner::getFirstArgument('"日本語", $other'))->toBe('"日本語"')
                ->and(ArgumentScanner::getFirstArgument("'Ελληνικά', \$var"))->toBe("'Ελληνικά'");
        });

        it('handles 4-byte UTF-8 sequences (emojis)', function (): void {
            $input = '"🎉🎊🎈", "🌟⭐✨", "🔥💧🌍"';
            expect(ArgumentScanner::countArguments($input))->toBe(3)
                ->and(ArgumentScanner::isSimpleString('"🎉🎊🎈"'))->toBeTrue();
        });
    });

    describe('stress tests', function (): void {
        it('handles very long argument strings', function (): void {
            $items = array_map(fn ($i) => '$var'.$i, range(1, 1000));
            $input = implode(', ', $items);

            expect(ArgumentScanner::countArguments($input))->toBe(1000);
        });

        it('handles deeply nested structures (100 levels)', function (): void {
            $input = str_repeat('[', 100).'$value'.str_repeat(']', 100);
            expect(ArgumentScanner::countArguments($input))->toBe(1);
        });

        it('handles many heredocs', function (): void {
            $input = <<<'INPUT'
<<<A
content1
A;, <<<B
content2
B;, <<<C
content3
C;, <<<D
content4
D;, <<<E
content5
E;
INPUT;
            expect(ArgumentScanner::countArguments($input))->toBe(5);
        });

        it('handles mixed nesting with long strings', function (): void {
            $longString = str_repeat('x', 10000);
            $input = "[\"{$longString}\", \$a, \$b], \$c, [\"{$longString}\"]";
            expect(ArgumentScanner::countArguments($input))->toBe(3);
        });

        it('handles alternating quote types', function (): void {
            $input = '"a, b", \'c, d\', "e, f", \'g, h\', "i, j"';
            expect(ArgumentScanner::countArguments($input))->toBe(5);
        });

        it('handles strings with many escaped quotes', function (): void {
            $escaped = str_repeat('\\"', 100);
            $input = "\"{$escaped}content{$escaped}\", \$var";
            expect(ArgumentScanner::countArguments($input))->toBe(2);
        });

        it('handles empty strings in sequence', function (): void {
            $input = '"", "", "", ""';
            expect(ArgumentScanner::countArguments($input))->toBe(4);
        });

        it('handles complex real-world examples', function (): void {
            $input = '$condition ? array_map(fn($x) => $x * 2, [1, 2, 3]) : collect([4, 5, 6])->filter(fn($y) => $y > 4), $default';
            expect(ArgumentScanner::countArguments($input))->toBe(2);

            $input = "['callback' => fn(\$a, \$b) => \$a + \$b, 'data' => [1, 2, 3]], \$options";
            expect(ArgumentScanner::countArguments($input))->toBe(2);
        });
    });

    describe('edge cases', function (): void {
        it('handles unclosed strings gracefully', function (): void {
            $input = '"unclosed, $var';
            $count = ArgumentScanner::countArguments($input);
            expect($count)->toBeGreaterThanOrEqual(1);
        });

        it('handles unclosed brackets gracefully', function (): void {
            $input = '[$a, $b, $c';
            $count = ArgumentScanner::countArguments($input);
            expect($count)->toBeGreaterThanOrEqual(1);
        });

        it('handles unclosed heredoc gracefully', function (): void {
            $input = <<<'INPUT'
<<<EOT
content without end
INPUT;
            $count = ArgumentScanner::countArguments($input);
            expect($count)->toBe(1);
        });

        it('handles only whitespace inside quotes', function (): void {
            expect(ArgumentScanner::countArguments('"   ", $var'))->toBe(2)
                ->and(ArgumentScanner::isSimpleString('"   "'))->toBeTrue();
        });

        it('handles single character strings', function (): void {
            expect(ArgumentScanner::countArguments('"a", "b", "c"'))->toBe(3)
                ->and(ArgumentScanner::isSimpleString('"x"'))->toBeTrue();
        });

        it('handles numeric strings', function (): void {
            expect(ArgumentScanner::countArguments('123, 456, 789'))->toBe(3)
                ->and(ArgumentScanner::countArguments('12.34, 56.78'))->toBe(2);
        });

        it('handles boolean and null literals', function (): void {
            expect(ArgumentScanner::countArguments('true, false, null'))->toBe(3);
        });

        it('handles arrow functions', function (): void {
            $input = 'fn($x) => $x * 2, fn($y) => $y + 1';
            expect(ArgumentScanner::countArguments($input))->toBe(2);

            $input = 'fn($a, $b) => [$a, $b]';
            expect(ArgumentScanner::countArguments($input))->toBe(1);
        });

        it('handles spread operator', function (): void {
            $input = '...$array, $single';
            expect(ArgumentScanner::countArguments($input))->toBe(2);
        });
    });
});
