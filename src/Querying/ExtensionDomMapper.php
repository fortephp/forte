<?php

declare(strict_types=1);

namespace Forte\Querying;

use DOMDocument;
use DOMElement;
use Forte\Ast\Node;

interface ExtensionDomMapper
{
    /**
     * Convert an extension AST node to a DOM element.
     *
     * @param  Node  $node  The AST node to convert
     * @param  DOMDocument  $dom  The DOM document to create elements in
     * @param  DomMapper  $mapper  The mapper instance for accessing constants and helpers
     */
    public function toDOM(Node $node, DOMDocument $dom, DomMapper $mapper): DOMElement;
}
