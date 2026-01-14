<?php

declare(strict_types=1);

namespace Forte\Extensions;

interface ForteExtension
{
    /**
     * Unique identifier for this extension.
     */
    public function id(): string;

    /**
     * Get a human-readable name for the extension.
     */
    public function name(): string;

    /**
     * Get extension's version.
     */
    public function version(): string;

    /**
     * Get extension IDs that this extension depends on.
     *
     * @return string[]
     */
    public function dependencies(): array;

    /**
     * Get extension IDs that conflict with this extension.
     *
     * @return string[]
     */
    public function conflicts(): array;

    /**
     * Get all configured options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;
}
