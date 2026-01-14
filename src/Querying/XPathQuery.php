<?php

declare(strict_types=1);

namespace Forte\Querying;

use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Forte\Ast\Document\Document;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\Node;
use OutOfBoundsException;

class XPathQuery
{
    private ?DOMDocument $dom = null;

    private ?DOMXPath $xpath = null;

    public function __construct(private readonly Document $document) {}

    /**
     * Execute an XPath query and return matching AST nodes.
     *
     * @param  string  $expression  XPath expression
     * @return NodeCollection<int, Node>
     *
     * @throws XPathException
     */
    public function query(string $expression): NodeCollection
    {
        $this->ensureBuilt();

        $domNodes = $this->executeXPath($expression);

        return $this->mapToAstNodes($domNodes);
    }

    /**
     * Execute an XPath query and return the first matching node.
     *
     * @param  string  $expression  XPath expression
     *
     * @throws XPathException
     */
    public function queryFirst(string $expression): ?Node
    {
        return $this->query($expression)->first();
    }

    /**
     * Check if any nodes match the expression.
     *
     * @param  string  $expression  XPath expression
     *
     * @throws XPathException
     */
    public function exists(string $expression): bool
    {
        return $this->query($expression)->isNotEmpty();
    }

    /**
     * Count nodes matching the expression.
     *
     * @param  string  $expression  XPath expression
     *
     * @throws XPathException
     */
    public function count(string $expression): int
    {
        return $this->query($expression)->count();
    }

    /**
     * Evaluate an XPath expression and return the raw result.
     *
     * @param  string  $expression  XPath expression
     * @return mixed The result of the evaluation
     *
     * @throws XPathException
     */
    public function evaluate(string $expression): mixed
    {
        $this->ensureBuilt();
        assert($this->xpath !== null);

        $result = @$this->xpath->evaluate($expression);

        if ($result === false) {
            throw new XPathException($expression, $this->getLastXPathError());
        }

        return $result;
    }

    /**
     * Get the underlying DOMDocument.
     */
    public function getDOMDocument(): DOMDocument
    {
        $this->ensureBuilt();
        assert($this->dom !== null);

        return $this->dom;
    }

    /**
     * Get the underlying DOMXPath.
     */
    public function getDOMXPath(): DOMXPath
    {
        $this->ensureBuilt();
        assert($this->xpath !== null);

        return $this->xpath;
    }

    /**
     * Ensure the DOM is built and XPath is configured.
     */
    private function ensureBuilt(): void
    {
        if ($this->dom !== null) {
            return;
        }

        $mapper = new DomMapper($this->document);
        $result = $mapper->build();

        $this->dom = $result['dom'];
        $this->xpath = new DOMXPath($this->dom);

        $this->xpath->registerNamespace(
            DomMapper::FORTE_PREFIX,
            DomMapper::FORTE_NAMESPACE
        );
    }

    /**
     * Execute an XPath query on the DOM.
     *
     * @return DOMNodeList<DOMNode|DOMNameSpaceNode>
     *
     * @throws XPathException
     */
    private function executeXPath(string $expression): DOMNodeList
    {
        assert($this->xpath !== null);
        $result = @$this->xpath->query($expression);

        if ($result === false) {
            throw new XPathException($expression, $this->getLastXPathError());
        }

        return $result;
    }

    /**
     * Map DOM nodes back to AST nodes.
     *
     * @param  DOMNodeList<DOMNode|DOMNameSpaceNode>  $domNodes
     * @return NodeCollection<int, Node>
     */
    private function mapToAstNodes(DOMNodeList $domNodes): NodeCollection
    {
        $nodes = [];

        foreach ($domNodes as $domNode) {
            if ($astNode = $this->domNodeToAstNode($domNode)) {
                $nodes[] = $astNode;
            }
        }

        return NodeCollection::make($nodes);
    }

    /**
     * Convert a single DOM node to an AST node.
     */
    private function domNodeToAstNode(DOMNode|DOMNameSpaceNode $domNode): ?Node
    {
        if (! $domNode instanceof DOMElement) {
            return null;
        }

        $indexAttr = $domNode->getAttribute(DomMapper::INDEX_ATTR);
        if ($indexAttr === '') {
            return null;
        }

        $index = (int) $indexAttr;

        try {
            return $this->document->getNode($index);
        } catch (OutOfBoundsException) {
            return null;
        }
    }

    /**
     * Get the last XPath error message.
     */
    private function getLastXPathError(): string
    {
        $error = error_get_last();

        return $error['message'] ?? 'Unknown XPath error';
    }
}
