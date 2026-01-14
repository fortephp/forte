<?php

declare(strict_types=1);

namespace Forte\Extensions\Concerns;

trait ProvidesDefaultExtensionImplementation
{
    /**
     * Gets a human-readable name for the extension.
     */
    public function name(): string
    {
        return $this->id();
    }

    /**
     * Gets the extension version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * Get extension IDs that this extension depends on.
     *
     * @return string[]
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * Get extension IDs that conflict with this extension.
     *
     * @return string[]
     */
    public function conflicts(): array
    {
        return [];
    }

    /**
     * Get the extension ID.
     */
    abstract public function id(): string;
}
