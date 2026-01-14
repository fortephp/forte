<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\Node;

trait ManagesSiblings
{
    /**
     * Get the sibling before a given index in the root children.
     *
     * @param  int  $currentIndex  Zero-based index of the current child
     */
    public function getSiblingBefore(int $currentIndex): ?Node
    {
        if ($currentIndex <= 0 || $currentIndex >= count($this->rootChildren)) {
            return null;
        }

        return $this->getNode($this->rootChildren[$currentIndex - 1]);
    }

    /**
     * Get the sibling after a given index in the root children.
     *
     * @param  int  $currentIndex  Zero-based index of the current child
     */
    public function getSiblingAfter(int $currentIndex): ?Node
    {
        if ($currentIndex < 0 || $currentIndex >= count($this->rootChildren) - 1) {
            return null;
        }

        return $this->getNode($this->rootChildren[$currentIndex + 1]);
    }

    /**
     * Get all siblings except the one at the given index.
     *
     * @param  int  $currentIndex  Zero-based index to exclude
     * @return array<int, Node>
     */
    public function getSiblings(int $currentIndex): array
    {
        $siblings = [];
        foreach ($this->rootChildren as $i => $childIdx) {
            if ($i !== $currentIndex) {
                $siblings[] = $this->getNode($childIdx);
            }
        }

        return $siblings;
    }
}
