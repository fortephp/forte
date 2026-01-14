<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

use Forte\Ast\Node;

trait ManagesTagNameParts
{
    private ?string $cachedContent = null;

    /**
     * Get the tag name.
     */
    public function name(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        return $this->cachedContent = $this->document->getNodeTokenContent($this->index);
    }

    /**
     * Get the parts that make up the tag name.
     *
     * @return iterable<Node>
     */
    public function parts(): iterable
    {
        return $this->children();
    }

    /**
     * Get parts as an array.
     *
     * @return array<Node>
     */
    public function getParts(): array
    {
        return $this->getChildren();
    }

    /**
     * Check if this tag name contains nested Blade constructs.
     */
    public function isComplex(): bool
    {
        $children = $this->getChildren();

        if (count($children) === 0) {
            return false;
        }

        if (count($children) > 1) {
            return true;
        }

        return ! $children[0]->isText();
    }

    /**
     * Check if this is a simple tag name.
     */
    public function isSimple(): bool
    {
        return ! $this->isComplex();
    }

    /**
     * Get the static text portion of the tag name.
     */
    public function staticText(): string
    {
        $result = '';
        foreach ($this->parts() as $part) {
            if ($part->isText()) {
                $result .= $part->getDocumentContent();
            }
        }

        return $result;
    }
}
