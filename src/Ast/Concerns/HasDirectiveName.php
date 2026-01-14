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
}
