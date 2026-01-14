<?php

declare(strict_types=1);

namespace Forte\Ast\Elements\Concerns;

use Forte\Ast\Elements\ElementNameNode;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
trait ManagesElementClosing
{
    /**
     * Check if this is a self-closing element.
     */
    public function isSelfClosing(): bool
    {
        return $this->flat()['data'] === 1;
    }

    /**
     * Check if this element has a closing tag.
     */
    public function isPaired(): bool
    {
        return $this->getClosingNameIndex() !== null;
    }

    /**
     * Get the self-closing style used in the source document.
     */
    public function selfClosingStyle(): ?string
    {
        if (! $this->isSelfClosing()) {
            return null;
        }

        $content = $this->getDocumentContent();

        if (str_ends_with((string) $content, ' />')) {
            return ' /';
        }

        if (str_ends_with((string) $content, '/>')) {
            return '/';
        }

        return null;
    }

    /**
     * Get the index of the ClosingElementName child node, if any.
     */
    private function getClosingNameIndex(): ?int
    {
        if ($this->closingNameSearched) {
            return $this->closingNameIdx;
        }

        $this->closingNameSearched = true;
        $flat = $this->flat();
        $lastChildIdx = $flat['lastChild'];

        if ($lastChildIdx === -1) {
            return null;
        }

        $lastChild = $this->document->getFlatNode($lastChildIdx);
        if ($lastChild['kind'] === NodeKind::ClosingElementName) {
            $this->closingNameIdx = $lastChildIdx;

            return $lastChildIdx;
        }

        return null;
    }

    /**
     * Get the closing tag node.
     */
    public function closingTag(): ?ElementNameNode
    {
        if ($this->cachedClosingTag !== null) {
            return $this->cachedClosingTag;
        }

        $closingIdx = $this->getClosingNameIndex();
        if ($closingIdx === null) {
            return null;
        }

        $node = $this->document->getNode($closingIdx);
        assert($node instanceof ElementNameNode && $node->isClosingName());

        return $this->cachedClosingTag = $node;
    }

    /**
     * Check if this element has a synthetic closing.
     */
    public function hasSyntheticClosing(): bool
    {
        $flat = $this->flat();
        $tokens = $this->document->getTokens();

        $tokenStart = $flat['tokenStart'];
        $tokenEnd = $tokenStart + $flat['tokenCount'];

        for ($i = $tokenStart; $i < $tokenEnd && $i < count($tokens); $i++) {
            if ($tokens[$i]['type'] === TokenType::SyntheticClose) {
                return true;
            }
        }

        return false;
    }
}
