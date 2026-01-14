<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;

readonly class WrapElements extends ElementPass
{
    /**
     * @param  string  $pattern  Tag name pattern to match
     * @param  string  $wrapperTag  Tag name for the wrapper element
     * @param  array<string, string>  $wrapperAttributes  Attributes for the wrapper element
     */
    public function __construct(string $pattern, private string $wrapperTag, private array $wrapperAttributes = [])
    {
        parent::__construct($pattern);
    }

    protected function applyToElement(NodePath $path, ElementNode $element): void
    {
        $wrapper = Builder::element($this->wrapperTag);

        foreach ($this->wrapperAttributes as $name => $value) {
            $wrapper->attr($name, $value);
        }

        $path->wrapWith($wrapper);
    }
}
