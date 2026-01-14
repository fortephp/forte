<?php

declare(strict_types=1);

use Forte\Lexer\LineIndex;

describe('Line Index', function (): void {
    test('single line', function (): void {
        $source = 'Hello World';
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(1)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(5))->toBe(1)
            ->and($index->offsetToLine(10))->toBe(1);
    });

    test('empty source', function (): void {
        $source = '';
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(1)
            ->and($index->offsetToLine(0))->toBe(1);
    });

    test('multiple lines with LF', function (): void {
        $source = "Line 1\nLine 2\nLine 3";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(3)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(5))->toBe(1)
            ->and($index->offsetToLine(6))->toBe(1)
            ->and($index->offsetToLine(7))->toBe(2)
            ->and($index->offsetToLine(12))->toBe(2)
            ->and($index->offsetToLine(13))->toBe(2)
            ->and($index->offsetToLine(14))->toBe(3)
            ->and($index->offsetToLine(18))->toBe(3);
    });

    test('trailing LF newline', function (): void {
        $source = "Line 1\nLine 2\n";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(3)
            ->and($index->offsetToLine(14))->toBe(3);
    });

    test('multiple lines with CRLF', function (): void {
        $source = "Line 1\r\nLine 2\r\nLine 3";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(3)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(5))->toBe(1)
            ->and($index->offsetToLine(6))->toBe(1)
            ->and($index->offsetToLine(7))->toBe(1)
            ->and($index->offsetToLine(8))->toBe(2)
            ->and($index->offsetToLine(13))->toBe(2)
            ->and($index->offsetToLine(14))->toBe(2)
            ->and($index->offsetToLine(15))->toBe(2)
            ->and($index->offsetToLine(16))->toBe(3)
            ->and($index->offsetToLine(20))->toBe(3);
    });

    test('trailing CRLF newline', function (): void {
        $source = "Line 1\r\nLine 2\r\n";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(3)
            ->and($index->offsetToLine(16))->toBe(3);
    });

    test('multiple lines with CR', function (): void {
        $source = "Line 1\rLine 2\rLine 3";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(3)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(5))->toBe(1)
            ->and($index->offsetToLine(6))->toBe(1)
            ->and($index->offsetToLine(7))->toBe(2)
            ->and($index->offsetToLine(12))->toBe(2)
            ->and($index->offsetToLine(13))->toBe(2)
            ->and($index->offsetToLine(14))->toBe(3)
            ->and($index->offsetToLine(18))->toBe(3);
    });

    it('handles mixed LF and CRLF', function (): void {
        $source = "Line 1\nLine 2\r\nLine 3";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(3)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(7))->toBe(2)
            ->and($index->offsetToLine(15))->toBe(3);
    });

    it('handles all three newline types', function (): void {
        $source = "Line 1\nLine 2\r\nLine 3\rLine 4";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(4)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(7))->toBe(2)
            ->and($index->offsetToLine(15))->toBe(3)
            ->and($index->offsetToLine(22))->toBe(4);
    });

    it('gets line and column for offsets', function (): void {
        $source = "Line 1\nLine 2\nLine 3";
        $index = LineIndex::build($source);

        $pos = $index->offsetToLineAndColumn(0);
        expect($pos['line'])->toBe(1)
            ->and($pos['column'])->toBe(1);

        $pos = $index->offsetToLineAndColumn(5);
        expect($pos['line'])->toBe(1)
            ->and($pos['column'])->toBe(6);

        $pos = $index->offsetToLineAndColumn(7);
        expect($pos['line'])->toBe(2)
            ->and($pos['column'])->toBe(1);

        $pos = $index->offsetToLineAndColumn(10);
        expect($pos['line'])->toBe(2)
            ->and($pos['column'])->toBe(4);
    });

    test('column accounts for byte offsets not characters', function (): void {
        $source = "Hello\n世界\nWorld";
        $index = LineIndex::build($source);

        $pos = $index->offsetToLineAndColumn(6);
        expect($pos['line'])->toBe(2)
            ->and($pos['column'])->toBe(1);

        $pos = $index->offsetToLineAndColumn(7);
        expect($pos['line'])->toBe(2)
            ->and($pos['column'])->toBe(2);

        $pos = $index->offsetToLineAndColumn(9);
        expect($pos['line'])->toBe(2)
            ->and($pos['column'])->toBe(4);
    });

    it('converts line numbers to offsets', function (): void {
        $source = "Line 1\nLine 2\nLine 3";
        $index = LineIndex::build($source);

        expect($index->lineToOffset(1))->toBe(0)
            ->and($index->lineToOffset(2))->toBe(7)
            ->and($index->lineToOffset(3))->toBe(14);
    });

    it('throws on invalid line number', function (): void {
        $source = "Line 1\nLine 2";
        $index = LineIndex::build($source);

        expect(fn () => $index->lineToOffset(0))->toThrow(OutOfBoundsException::class)
            ->and(fn () => $index->lineToOffset(10))->toThrow(OutOfBoundsException::class);
    });

    test('consecutive newlines', function (): void {
        $source = "Line 1\n\n\nLine 4";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(4)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(7))->toBe(2)
            ->and($index->offsetToLine(8))->toBe(3)
            ->and($index->offsetToLine(9))->toBe(4);
    });

    test('starts with newline', function (): void {
        $source = "\nLine 2";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(2)
            ->and($index->offsetToLine(0))->toBe(1)
            ->and($index->offsetToLine(1))->toBe(2);
    });

    test('only newlines', function (): void {
        $source = "\n\n\n";
        $index = LineIndex::build($source);

        expect($index->lineCount())->toBe(4);
    });
});
