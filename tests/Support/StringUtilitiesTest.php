<?php

declare(strict_types=1);

use Forte\Support\StringUtilities;

describe('Unwrap Parentheses', function (): void {
    it('removes single layer of parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('(hello)');

        expect($result)->toBe('hello');
    });

    it('removes multiple layers of parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('((hello))');

        expect($result)->toBe('hello');
    });

    it('removes many layers of parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('((((hello))))');

        expect($result)->toBe('hello');
    });

    it('does not remove unbalanced parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('(hello');

        expect($result)->toBe('(hello');
    });

    it('does not remove unbalanced closing parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('hello)');

        expect($result)->toBe('hello)');
    });

    it('returns string without parentheses unchanged', function (): void {
        $result = StringUtilities::unwrapParentheses('hello world');

        expect($result)->toBe('hello world');
    });

    it('handles empty string', function (): void {
        $result = StringUtilities::unwrapParentheses('');

        expect($result)->toBe('');
    });

    it('handles empty parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('()');

        expect($result)->toBe('');
    });

    it('preserves internal parentheses', function (): void {
        $result = StringUtilities::unwrapParentheses('(func(a, b))');

        expect($result)->toBe('func(a, b)');
    });

    it('handles unicode content', function (): void {
        $result = StringUtilities::unwrapParentheses('(世界)');

        expect($result)->toBe('世界');
    });
});

describe('Unwrap String', function (): void {
    it('unwraps double quoted string', function (): void {
        $result = StringUtilities::unwrapString('"hello"');

        expect($result)->toBe('hello');
    });

    it('unwraps single quoted string', function (): void {
        $result = StringUtilities::unwrapString("'hello'");

        expect($result)->toBe('hello');
    });

    it('does not unwrap mixed quotes', function (): void {
        $result = StringUtilities::unwrapString('"hello\'');

        expect($result)->toBe('"hello\'');
    });

    it('does not unwrap unbalanced double quotes', function (): void {
        $result = StringUtilities::unwrapString('"hello');

        expect($result)->toBe('"hello');
    });

    it('does not unwrap unbalanced single quotes', function (): void {
        $result = StringUtilities::unwrapString("'hello");

        expect($result)->toBe("'hello");
    });

    it('returns unquoted string unchanged', function (): void {
        $result = StringUtilities::unwrapString('hello');

        expect($result)->toBe('hello');
    });

    it('handles empty string', function (): void {
        $result = StringUtilities::unwrapString('');

        expect($result)->toBe('');
    });

    it('handles empty double quotes', function (): void {
        $result = StringUtilities::unwrapString('""');

        expect($result)->toBe('');
    });

    it('handles empty single quotes', function (): void {
        $result = StringUtilities::unwrapString("''");

        expect($result)->toBe('');
    });

    it('preserves internal quotes', function (): void {
        $result = StringUtilities::unwrapString('"hello \'world\'"');

        expect($result)->toBe("hello 'world'");
    });

    it('handles unicode content', function (): void {
        $result = StringUtilities::unwrapString('"世界"');

        expect($result)->toBe('世界');
    });
});

describe('Wrap in Quotes', function (): void {
    it('wraps unquoted string in double quotes', function (): void {
        $result = StringUtilities::wrapInQuotes('hello', '"');

        expect($result)->toBe('"hello"');
    });

    it('wraps unquoted string in single quotes', function (): void {
        $result = StringUtilities::wrapInQuotes('hello', "'");

        expect($result)->toBe("'hello'");
    });

    it('does not double wrap already quoted string', function (): void {
        $result = StringUtilities::wrapInQuotes('"hello"', '"');

        expect($result)->toBe('"hello"');
    });

    it('escapes internal quotes when wrapping', function (): void {
        $result = StringUtilities::wrapInQuotes('hello "world"', '"');

        expect($result)->toBe('"hello \"world\""');
    });

    it('preserves already escaped quotes', function (): void {
        $result = StringUtilities::wrapInQuotes('hello \"world\"', '"');

        expect($result)->toBe('"hello \"world\""');
    });

    it('handles empty string', function (): void {
        $result = StringUtilities::wrapInQuotes('', '"');

        expect($result)->toBe('""');
    });

    it('handles unicode content', function (): void {
        $result = StringUtilities::wrapInQuotes('世界', '"');

        expect($result)->toBe('"世界"');
    });
});

describe('Wrap in Single Quotes', function (): void {
    it('wraps string in single quotes', function (): void {
        $result = StringUtilities::wrapInSingleQuotes('hello');

        expect($result)->toBe("'hello'");
    });

    it('does not wrap variables starting with dollar sign', function (): void {
        $result = StringUtilities::wrapInSingleQuotes('$variable');

        expect($result)->toBe('$variable');
    });

    it('does not double wrap already quoted string', function (): void {
        $result = StringUtilities::wrapInSingleQuotes("'hello'");

        expect($result)->toBe("'hello'");
    });

    it('handles empty string', function (): void {
        $result = StringUtilities::wrapInSingleQuotes('');

        expect($result)->toBe("''");
    });

    it('handles unicode content', function (): void {
        $result = StringUtilities::wrapInSingleQuotes('世界');

        expect($result)->toBe("'世界'");
    });
});

describe('Escape Single Quotes', function (): void {
    it('escapes single quotes', function (): void {
        $result = StringUtilities::escapeSingleQuotes("it's");

        expect($result)->toBe("it\\'s");
    });

    it('escapes multiple single quotes', function (): void {
        $result = StringUtilities::escapeSingleQuotes("it's a 'test'");

        expect($result)->toBe("it\\'s a \\'test\\'");
    });

    it('handles string without single quotes', function (): void {
        $result = StringUtilities::escapeSingleQuotes('hello');

        expect($result)->toBe('hello');
    });

    it('handles empty string', function (): void {
        $result = StringUtilities::escapeSingleQuotes('');

        expect($result)->toBe('');
    });

    it('handles already escaped quotes', function (): void {
        $result = StringUtilities::escapeSingleQuotes("it\\'s");

        expect($result)->toBe("it\\\\'s");
    });
});

describe('Line Ending Normalization', function (): void {
    it('converts CRLF to LF', function (): void {
        $result = StringUtilities::normalizeLineEndings("hello\r\nworld");

        expect($result)->toBe("hello\nworld");
    });

    it('converts CR to LF', function (): void {
        $result = StringUtilities::normalizeLineEndings("hello\rworld");

        expect($result)->toBe("hello\nworld");
    });

    it('preserves LF', function (): void {
        $result = StringUtilities::normalizeLineEndings("hello\nworld");

        expect($result)->toBe("hello\nworld");
    });

    it('handles multiple line endings', function (): void {
        $result = StringUtilities::normalizeLineEndings("line1\r\nline2\rline3\nline4");

        expect($result)->toBe("line1\nline2\nline3\nline4");
    });

    it('handles empty string', function (): void {
        $result = StringUtilities::normalizeLineEndings('');

        expect($result)->toBe('');
    });

    it('handles string with no line endings', function (): void {
        $result = StringUtilities::normalizeLineEndings('hello world');

        expect($result)->toBe('hello world');
    });

    it('handles consecutive line endings', function (): void {
        $result = StringUtilities::normalizeLineEndings("hello\r\n\r\nworld");

        expect($result)->toBe("hello\n\nworld");
    });
});
