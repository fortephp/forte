<?php

declare(strict_types=1);

namespace Forte\Ast\Concerns;

use Illuminate\Support\Str;

trait HasDirectiveName
{
    /**
     * Get the normalized directive name.
     */
    public function nameText(): string
    {
        return $this->flat()['name'] ?? '';
    }

    /**
     * Get the original directive name.
     */
    public function name(): string
    {
        $flat = $this->flat();
        $token = $this->document->getToken($flat['tokenStart']);
        $text = $this->document->getSourceSlice($token['start'], $token['end']);

        if (str_starts_with((string) $text, '@')) {
            $text = substr((string) $text, 1);
        }

        return $text;
    }

    /**
     * Check if the current is a directive matching the pattern.
     */
    public function is(string $pattern): bool
    {
        $directiveName = $this->nameText();

        if ($directiveName === '') {
            return false;
        }

        return Str::is($pattern, $directiveName);
    }

    /**
     * Check whether this directive matches any of the given patterns.
     *
     * @param  array<string>  $patterns
     */
    public function isAny(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether this directive has any of the exact given names.
     *
     * @param  array<string>  $names
     */
    public function isAnyDirectiveNamed(array $names): bool
    {
        foreach ($names as $name) {
            if ($this->matchesDirectiveName($name)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesDirectiveName(string $name): bool
    {
        return strcasecmp($this->nameText(), $name) === 0;
    }
}
