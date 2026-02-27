<?php

declare(strict_types=1);

namespace Forte\Enclaves\Rewriters;

use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;

class AttributeDirectiveCoordinator extends Visitor
{
    /** @var list<AttributeDirective> */
    protected array $directives = [];

    public function addDirective(AttributeDirective $directive): self
    {
        $this->directives[] = $directive;

        return $this;
    }

    public function enter(NodePath $path): void
    {
        $elem = $path->asElement();

        if ($elem === null || $this->directives === []) {
            return;
        }

        // Collect matched attributes with their handlers, in attribute order.
        $matched = [];

        foreach ($elem->attributes() as $attr) {
            foreach ($this->directives as $directive) {
                if ($directive->matches($attr)) {
                    $matched[] = ['directive' => $directive, 'attr' => $attr];
                    break;
                }
            }
        }

        if ($matched === []) {
            return;
        }

        // Process in REVERSE attribute order so that the leftmost attribute
        // is pushed last onto the wrapStack and becomes outermost.
        foreach (array_reverse($matched) as $entry) {
            $entry['directive']->apply($path, $elem, $entry['attr']);
        }
    }
}
