<?php

declare(strict_types=1);

namespace Forte\Ast\Elements;

class VoidElements
{
    /**
     * List of void elements that should never have closing tags.
     *
     * @var array<string, true>
     */
    public static array $voidElements = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];
}
