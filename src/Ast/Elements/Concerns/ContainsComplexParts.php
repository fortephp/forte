<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
trait ContainsComplexParts
{
    /**
     * Check if this value contains interpolation-like nodes.
     */
    public function isComplex(): bool
    {
        if ($this->cachedIsComplex !== null) {
            return $this->cachedIsComplex;
        }

        $flat = $this->document->getFlatNode($this->index);
        $childIdx = $flat['firstChild'];

        while ($childIdx !== -1) {
            $childNode = $this->document->getFlatNode($childIdx);
            if ($childNode['kind'] !== NodeKind::Text) {
                $this->cachedIsComplex = true;

                return true;
            }
            $childIdx = $childNode['nextSibling'];
        }

        $this->cachedIsComplex = false;

        return false;
    }
}
