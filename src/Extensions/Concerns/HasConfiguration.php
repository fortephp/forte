<?php

declare(strict_types=1);

namespace Forte\Extensions\Concerns;

trait HasConfiguration
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Get an option value.
     *
     * @template T
     *
     * @param  T  $default
     * @return T
     */
    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Check if an option is set.
     */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * Get all configured options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
