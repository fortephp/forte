<?php

declare(strict_types=1);

use Forte\Support\ScanPrimitives;

describe('ScanPrimitives', function (): void {
    describe('skipQuotedString', function (): void {
        it('skips simple single-quoted string', function (): void {
            $source = "'hello' world";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipQuotedString($source, $pos, $len, "'");

            expect($pos)->toBe(7);
        });

        it('skips simple double-quoted string', function (): void {
            $source = '"hello" world';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipQuotedString($source, $pos, $len, '"');

            expect($pos)->toBe(7);
        });

        it('handles escaped quotes', function (): void {
            $source = "'it\\'s working' rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipQuotedString($source, $pos, $len, "'");

            expect($pos)->toBe(15);
        });

        it('handles double backslash before quote', function (): void {
            $source = "'test\\\\' rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipQuotedString($source, $pos, $len, "'");

            expect($pos)->toBe(8);
        });

        it('handles unclosed string gracefully', function (): void {
            $source = "'unclosed string";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipQuotedString($source, $pos, $len, "'");

            expect($pos)->toBe($len);
        });

        it('handles strings with newlines', function (): void {
            $source = "\"line1\nline2\" rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipQuotedString($source, $pos, $len, '"');

            expect($pos)->toBe(13);
        });
    });

    describe('skipBlockComment', function (): void {
        it('skips simple block comment', function (): void {
            $source = '/* comment */ rest';
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipBlockComment($source, $pos, $len);

            expect($pos)->toBe(13);
        });

        it('skips multiline block comment', function (): void {
            $source = "/* line1\n * line2\n */ rest";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipBlockComment($source, $pos, $len);

            expect($pos)->toBe(21);
        });

        it('handles unclosed block comment', function (): void {
            $source = '/* unclosed';
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipBlockComment($source, $pos, $len);

            expect($pos)->toBe($len);
        });

        it('handles asterisks in comment', function (): void {
            $source = '/* * ** * */ rest';
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipBlockComment($source, $pos, $len);

            expect($pos)->toBe(12);
        });
    });

    describe('skipLineComment', function (): void {
        it('skips line comment with LF', function (): void {
            $source = "// comment\nnext line";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(11)
                ->and($source[$pos])->toBe('n');
        });

        it('handles comment at end of file', function (): void {
            $source = '// comment at EOF';
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe($len);
        });

        it('skips line comment with CRLF', function (): void {
            $source = "// comment\r\nnext line";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(12)
                ->and($source[$pos])->toBe('n');
        });

        it('skips line comment with CR only (old Mac)', function (): void {
            $source = "// comment\rnext line";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(11)
                ->and($source[$pos])->toBe('n');
        });

        it('handles empty comment with LF', function (): void {
            $source = "//\nnext";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(3);
        });

        it('handles empty comment with CRLF', function (): void {
            $source = "//\r\nnext";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(4);
        });
    });

    describe('skipLineCommentDetecting', function (): void {
        it('detects sequences in comment with LF', function (): void {
            $source = "// contains ?> here\nnext";
            $pos = 2;
            $len = strlen($source);

            $detected = ScanPrimitives::skipLineCommentDetecting($source, $pos, $len, ['?>']);

            expect($detected)->toHaveCount(1)
                ->and($detected[0]['sequence'])->toBe('?>')
                ->and($pos)->toBe(20);
        });

        it('detects sequences in comment with CRLF', function (): void {
            $source = "// contains ?> here\r\nnext";
            $pos = 2;
            $len = strlen($source);

            $detected = ScanPrimitives::skipLineCommentDetecting($source, $pos, $len, ['?>']);

            expect($detected)->toHaveCount(1)
                ->and($detected[0]['sequence'])->toBe('?>')
                ->and($pos)->toBe(21);
        });

        it('handles CR-only newline', function (): void {
            $source = "// comment ?>\rnext";
            $pos = 2;
            $len = strlen($source);

            $detected = ScanPrimitives::skipLineCommentDetecting($source, $pos, $len, ['?>']);

            expect($detected)->toHaveCount(1)
                ->and($pos)->toBe(14)
                ->and($source[$pos])->toBe('n');
        });
    });

    describe('skipHeredoc', function (): void {
        it('skips simple heredoc with LF', function (): void {
            $source = "<<<EOT\nhello\nEOT;\nrest";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe(18);
        });

        it('skips nowdoc with LF', function (): void {
            $source = "<<<'EOT'\nhello\nEOT;\nrest";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe(20);
        });

        it('skips heredoc with CRLF line endings', function (): void {
            $source = "<<<EOT\r\nhello\r\nEOT;\r\nrest";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe(21)
                ->and($source[$pos])->toBe('r');
        });

        it('handles heredoc without closing delimiter', function (): void {
            $source = "<<<EOT\nhello world";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe($len);
        });

        it('handles heredoc with CR-only line endings', function (): void {
            $source = "<<<EOT\rhello\rEOT;\rrest";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe(18)
                ->and($source[$pos])->toBe('r');
        });

        it('skips heredoc without semicolon', function (): void {
            $source = "<<<EOT\nhello\nEOT\nrest";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe(17);
        });

        it('handles empty delimiter gracefully', function (): void {
            $source = "<<<\nhello";
            $pos = 3;
            $len = strlen($source);

            ScanPrimitives::skipHeredoc($source, $pos, $len);

            expect($pos)->toBe($len);
        });
    });

    describe('skipTemplateLiteral', function (): void {
        it('skips simple template literal', function (): void {
            $source = '`hello` rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipTemplateLiteral($source, $pos, $len);

            expect($pos)->toBe(7);
        });

        it('skips template with expression', function (): void {
            $source = '`hello ${name}` rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipTemplateLiteral($source, $pos, $len);

            expect($pos)->toBe(15);
        });

        it('handles escaped backtick', function (): void {
            $source = '`hello \\` world` rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipTemplateLiteral($source, $pos, $len);

            expect($pos)->toBe(16);
        });

        it('handles unclosed template', function (): void {
            $source = '`unclosed';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipTemplateLiteral($source, $pos, $len);

            expect($pos)->toBe($len);
        });

        it('handles multiline template with LF', function (): void {
            $source = "`line1\nline2` rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipTemplateLiteral($source, $pos, $len);

            expect($pos)->toBe(13);
        });

        it('handles multiline template with CRLF', function (): void {
            $source = "`line1\r\nline2` rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipTemplateLiteral($source, $pos, $len);

            expect($pos)->toBe(14);
        });
    });

    describe('skipBalancedPair', function (): void {
        it('skips simple parentheses', function (): void {
            $source = '(hello) rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(7);
        });

        it('skips nested parentheses', function (): void {
            $source = '(a(b(c))) rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(9);
        });

        it('skips with strings inside', function (): void {
            $source = '("contains ) char") rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(19);
        });

        it('skips with line comment inside', function (): void {
            $source = "(a // comment\nb) rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(16);
        });

        it('skips with block comment inside', function (): void {
            $source = '(a /* ) */ b) rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(13);
        });

        it('handles unclosed pair', function (): void {
            $source = '(unclosed';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe($len);
        });

        it('handles brackets', function (): void {
            $source = '[a, b, c] rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '[', ']');

            expect($pos)->toBe(9);
        });

        it('handles braces', function (): void {
            $source = '{key: value} rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '{', '}');

            expect($pos)->toBe(12);
        });

        it('handles heredoc inside pair', function (): void {
            $source = "(<<<EOT\ntext\nEOT\n) rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(18);
        });
    });

    describe('skipBacktickString', function (): void {
        it('skips simple backtick string', function (): void {
            $source = '`ls -la` rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBacktickString($source, $pos, $len);

            expect($pos)->toBe(8);
        });

        it('handles escaped backtick', function (): void {
            $source = '`echo \\`test\\`` rest';
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBacktickString($source, $pos, $len);

            expect($pos)->toBe(15);
        });
    });

    describe('newline style handling', function (): void {
        it('handles mixed newline styles in balanced pair', function (): void {
            $source = "(line1\nline2\r\nline3\rline4) rest";
            $pos = 1;
            $len = strlen($source);

            ScanPrimitives::skipBalancedPair($source, $pos, $len, '(', ')');

            expect($pos)->toBe(26);
        });

        it('handles mixed newlines in block comment', function (): void {
            $source = "/* line1\nline2\r\nline3\r*/ rest";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipBlockComment($source, $pos, $len);

            expect($pos)->toBe(24);
        });

        it('handles file with only CR newlines', function (): void {
            $source = "// comment 1\r// comment 2\rcode";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(13)
                ->and($source[$pos])->toBe('/');
        });

        it('handles file with only CRLF newlines', function (): void {
            $source = "// comment\r\ncode";
            $pos = 2;
            $len = strlen($source);

            ScanPrimitives::skipLineComment($source, $pos, $len);

            expect($pos)->toBe(12)
                ->and($source[$pos])->toBe('c');
        });
    });

    describe('findLineEnding', function (): void {
        it('finds LF', function (): void {
            $source = "hello\nworld";
            $pos = ScanPrimitives::findLineEnding($source, 0);

            expect($pos)->toBe(5);
        });

        it('finds CR', function (): void {
            $source = "hello\rworld";
            $pos = ScanPrimitives::findLineEnding($source, 0);

            expect($pos)->toBe(5);
        });

        it('finds CRLF (returns CR position)', function (): void {
            $source = "hello\r\nworld";
            $pos = ScanPrimitives::findLineEnding($source, 0);

            expect($pos)->toBe(5);
        });

        it('returns false when no line ending', function (): void {
            $source = 'hello world';
            $pos = ScanPrimitives::findLineEnding($source, 0);

            expect($pos)->toBeFalse();
        });

        it('finds nearest line ending when mixed', function (): void {
            $source = "cr first\r then lf\n end";
            $pos = ScanPrimitives::findLineEnding($source, 0);

            expect($pos)->toBe(8);
        });
    });

    describe('skipLineEnding', function (): void {
        it('skips LF', function (): void {
            $source = "\nworld";
            $pos = 0;
            ScanPrimitives::skipLineEnding($source, $pos, strlen($source));

            expect($pos)->toBe(1);
        });

        it('skips CR', function (): void {
            $source = "\rworld";
            $pos = 0;
            ScanPrimitives::skipLineEnding($source, $pos, strlen($source));

            expect($pos)->toBe(1);
        });

        it('skips CRLF as single unit', function (): void {
            $source = "\r\nworld";
            $pos = 0;
            ScanPrimitives::skipLineEnding($source, $pos, strlen($source));

            expect($pos)->toBe(2);
        });

        it('does nothing at EOF', function (): void {
            $source = 'hello';
            $pos = 5;
            ScanPrimitives::skipLineEnding($source, $pos, strlen($source));

            expect($pos)->toBe(5);
        });

        it('does nothing for non-line-ending char', function (): void {
            $source = 'hello';
            $pos = 0;
            ScanPrimitives::skipLineEnding($source, $pos, strlen($source));

            expect($pos)->toBe(0);
        });
    });
});
