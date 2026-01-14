<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

use Illuminate\Support\Str;

abstract class NodeBuilder
{
    abstract public function toSource(): string;

    /**
     * Get the kind of node this spec represents.
     */
    abstract public function kind(): int;

    /**
     * Whether this spec needs contextual safe spacing.
     */
    public function needsLeadingSeparator(): bool
    {
        return false;
    }

    protected function addContentWhitespace(string $content): string
    {
        $prefix = Str::startsWith($content, [' ', "\t"]) ? '' : ' ';
        $suffix = Str::endsWith($content, [' ', "\t"]) ? '' : ' ';

        return $prefix.$content.$suffix;
    }
}
