<?php

declare(strict_types=1);

use Forte\Support\HtmlWhitespace;

describe('HTML whitespace', function (): void {
    it('splits only the five HTML ASCII whitespace characters', function (): void {
        expect(HtmlWhitespace::split(" foo\tbar\nbaz\fqux\rzip "))
            ->toBe(['foo', 'bar', 'baz', 'qux', 'zip'])
            ->and(HtmlWhitespace::split("foo\x0Bbar"))->toBe(["foo\x0Bbar"]);
    });

    it('offers exact trim and boundary predicates', function (): void {
        expect(HtmlWhitespace::trim("\t foo \r"))->toBe('foo')
            ->and(HtmlWhitespace::trimStart("\t foo \r"))->toBe("foo \r")
            ->and(HtmlWhitespace::trimEnd("\t foo \r"))->toBe("\t foo")
            ->and(HtmlWhitespace::trim("\x0Bfoo\x0B"))->toBe("\x0Bfoo\x0B")
            ->and(HtmlWhitespace::isOnly("\t\n\f\r "))->toBeTrue()
            ->and(HtmlWhitespace::isOnly("\x0B"))->toBeFalse()
            ->and(HtmlWhitespace::startsWith("\tfoo"))->toBeTrue()
            ->and(HtmlWhitespace::endsWith('foo '))->toBeTrue();
    });
});
