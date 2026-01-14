<?php

declare(strict_types=1);

namespace Forte\Parser;

readonly class ExtensionNodeKind
{
    /**
     * @param  class-string|null  $nodeClass
     */
    public function __construct(
        public int $id,
        public string $namespace,
        public string $name,
        public string $label,
        public ?string $nodeClass = null,
        public ?string $domElement = null,
        public ?string $category = null,
    ) {}
}
