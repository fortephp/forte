<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

trait ManagesLines
{
    /**
     * Get the line span of this node.
     *
     * Returns [-1, -1] for synthetic nodes.
     *
     * @return array{start: int, end: int}
     */
    public function lineSpan(): array
    {
        return [
            'start' => $this->startLine(),
            'end' => $this->endLine(),
        ];
    }

    /**
     * Get the number of lines this node spans.
     *
     * Returns -1 for synthetic nodes.
     */
    public function lineCount(): int
    {
        $start = $this->startLine();
        $end = $this->endLine();

        // Synthetic nodes have -1 for offsets, which propagates to lines
        if ($start < 1 || $end < 1) {
            return -1;
        }

        return $end - $start + 1;
    }

    /**
     * Check if this node contains a specific line.
     *
     * @param  int  $line  The line number to check (1-indexed)
     */
    public function containsLine(int $line): bool
    {
        $start = $this->startLine();
        $end = $this->endLine();

        // Synthetic nodes cannot contain any line.
        if ($start < 1 || $end < 1) {
            return false;
        }

        return $line >= $start && $line <= $end;
    }

    /**
     * Check if this node spans multiple lines.
     */
    public function isMultiline(): bool
    {
        return $this->lineCount() > 1;
    }

    /**
     * Check if this node fits on a single line.
     */
    public function isSingleLine(): bool
    {
        return $this->lineCount() === 1;
    }
}
