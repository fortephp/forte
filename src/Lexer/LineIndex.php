<?php

declare(strict_types=1);

namespace Forte\Lexer;

use OutOfBoundsException;

readonly class LineIndex
{
    /**
     * @param  array<int, int>  $lineStarts
     */
    private function __construct(private array $lineStarts) {}

    public static function build(string $source): self
    {
        $lineStarts = [0];

        if (preg_match_all('/\r\n|\n|\r/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $newlineStart = $match[1];
                $newlineLength = strlen($match[0]);

                $lineStarts[] = $newlineStart + $newlineLength;
            }
        }

        return new self($lineStarts);
    }

    /**
     * Get a 1-indexed line number for the provided byte offset.
     *
     * @param  int  $offset  Byte offset (0-indexed)
     */
    public function offsetToLine(int $offset): int
    {
        $left = 0;
        $right = count($this->lineStarts) - 1;

        while ($left < $right) {
            $mid = $left + (int) ceil(($right - $left) / 2);

            if ($this->lineStarts[$mid] <= $offset) {
                $left = $mid;
            } else {
                $right = $mid - 1;
            }
        }

        return $left + 1;
    }

    /**
     * Get 1-indexed line and column for the provided byte offset.
     *
     * @param  int  $offset  Byte offset (0-indexed)
     * @return array{line: int, column: int}
     */
    public function offsetToLineAndColumn(int $offset): array
    {
        $line = $this->offsetToLine($offset);
        $lineStart = $this->lineStarts[$line - 1];
        $column = $offset - $lineStart + 1;

        return [
            'line' => $line,
            'column' => $column,
        ];
    }

    /**
     * Get the byte offset for start of given line.
     *
     * @param  int  $line  Line number (1-indexed)
     */
    public function lineToOffset(int $line): int
    {
        if ($line < 1 || $line > count($this->lineStarts)) {
            throw new OutOfBoundsException("Line $line is out of bounds");
        }

        return $this->lineStarts[$line - 1];
    }

    /**
     * Get the total number of lines.
     */
    public function lineCount(): int
    {
        return count($this->lineStarts);
    }
}
