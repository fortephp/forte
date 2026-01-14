<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Lexer\LineIndex;

trait ManagesOffsets
{
    /**
     * Get the 1-index line number for a source offset.
     *
     * @internal
     */
    public function getLineForOffset(int $offset): int
    {
        return $this->getLineIndex()->offsetToLine($offset);
    }

    /**
     * Get the 1-index column number for a source offset.
     *
     * @internal
     */
    public function getColumnForOffset(int $offset): int
    {
        return $this->getLineIndex()->offsetToLineAndColumn($offset)['column'];
    }

    /**
     * Get both the 1-indexed line and column for a source offset.
     *
     * @internal
     *
     * @return array{line: int, column: int}
     */
    public function getLineAndColumnForOffset(int $offset): array
    {
        return $this->getLineIndex()->offsetToLineAndColumn($offset);
    }

    /**
     * Get the byte offset for a 1-indexed line and column position.
     *
     * @internal
     *
     * @param  int  $line  1-based line number
     * @param  int  $column  1-based column number
     */
    public function getOffsetFromPosition(int $line, int $column): int
    {
        $lineStart = $this->getLineIndex()->lineToOffset($line);

        return $lineStart + $column - 1;
    }

    /**
     * Get the line index.
     */
    private function getLineIndex(): LineIndex
    {
        if ($this->lineIndex === null) {
            $this->lineIndex = LineIndex::build($this->source);
        }

        return $this->lineIndex;
    }

    /**
     * Get the total number of lines in the original source.
     */
    public function getLineCount(): int
    {
        return $this->getLineIndex()->lineCount();
    }

    /**
     * Get a single line from the original source.
     *
     * @param  int  $lineNumber  1-based line number
     */
    public function getLine(int $lineNumber): string
    {
        $lines = $this->getLines();

        return $lines[$lineNumber - 1] ?? '';
    }

    /**
     * Get the source split into lines.
     *
     * @return array<int, string>
     */
    public function getLines(): array
    {
        return $this->linesCache ??= preg_split('/\r\n|\n|\r/', $this->source) ?: [];
    }

    /**
     * Get a small excerpt of lines around a given line number.
     *
     * @param  int  $lineNumber  Center line (1-based)
     * @param  int  $contextLines  Number of lines to include before and after
     * @return array<int, string>
     */
    public function getLineExcerpt(int $lineNumber, int $contextLines = 2): array
    {
        $lines = $this->getLines();
        $start = max(0, $lineNumber - $contextLines - 1);
        $length = ($contextLines * 2) + 1;

        $excerpt = [];
        for ($i = $start; $i < $start + $length && $i < count($lines); $i++) {
            $excerpt[$i + 1] = $lines[$i];
        }

        return $excerpt;
    }
}
