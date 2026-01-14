<?php

declare(strict_types=1);

namespace Forte\Rewriting\Builders;

interface WrapperBuilder
{
    /**
     * Get the opening source for this wrapper.
     */
    public function getOpeningSource(): string;

    /**
     * Get the closing source for this wrapper.
     */
    public function getClosingSource(): string;
}
