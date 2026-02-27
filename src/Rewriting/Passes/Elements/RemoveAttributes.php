<?php

declare(strict_types=1);

namespace Forte\Rewriting\Passes\Elements;

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\NodePath;
use Illuminate\Support\Str;

readonly class RemoveAttributes extends ElementPass
{
    /**
     * @param  string  $elementPattern  Tag name pattern to match
     * @param  array<string>  $attributePatterns  Attribute name patterns to remove
     */
    public function __construct(string $elementPattern, private array $attributePatterns)
    {
        parent::__construct($elementPattern);
    }

    protected function applyToElement(NodePath $path, ElementNode $element): void
    {
        /** @var Attribute $attr */
        foreach ($element->attributes() as $attr) {
            $attrName = $attr->rawName();

            foreach ($this->attributePatterns as $pattern) {
                if (Str::is($pattern, $attrName)) {
                    $path->removeAttribute($attrName);
                    break;
                }
            }
        }

        // Handle synthetic attributes from prior pipeline passes (e.g., after RenameTag).
        $syntheticAttrs = $element->syntheticAttributes();

        if ($syntheticAttrs !== null) {
            foreach ($syntheticAttrs as [$name, $value]) {
                foreach ($this->attributePatterns as $pattern) {
                    if (Str::is($pattern, $name)) {
                        $path->removeAttribute($name);
                        break;
                    }
                }
            }
        }
    }
}
