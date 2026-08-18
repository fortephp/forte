<?php

declare(strict_types=1);

namespace Forte\Support;

use Forte\Ast\Elements\Attribute;

final class AttributeQuoting
{
    public static function preferredQuote(string $value): ?string
    {
        if (! str_contains($value, '"')) {
            return '"';
        }

        if (! str_contains($value, "'")) {
            return "'";
        }

        return null;
    }

    public static function styleOf(Attribute $attribute): string
    {
        return $attribute->quote() ?? '"';
    }

    public static function render(string $name, string $value, string $quote): string
    {
        return $name.'='.$quote.$value.$quote;
    }
}
