<?php

declare(strict_types=1);

namespace Forte\Ast\Document\Concerns;

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Components\ComponentNode;
use Forte\Ast\Components\SlotNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\DoctypeNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\BogusCommentNode;
use Forte\Ast\Elements\CdataNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ConditionalCommentNode;
use Forte\Ast\Elements\ElementNameNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\EscapeNode;
use Forte\Ast\GenericNode;
use Forte\Ast\Node;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\ProcessingInstructionNode;
use Forte\Ast\TextNode;
use Forte\Ast\VerbatimNode;
use Forte\Ast\XmlDeclarationNode;
use Forte\Components\ComponentManager;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Directives\Directives;
use Forte\Parser\NodeKind;
use Forte\Parser\TreeBuilder;
use OutOfBoundsException;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 */
trait ManagesAst
{
    /**
     * Get a node wrapper by index.
     */
    public function getNode(int $index): Node
    {
        if (isset($this->nodeCache[$index])) {
            return $this->nodeCache[$index];
        }

        if (! isset($this->nodes[$index])) {
            throw new OutOfBoundsException("Node index {$index} does not exist");
        }

        return $this->nodeCache[$index] = $this->createNodeWrapper($index, $this->nodes[$index]);
    }

    /**
     * Create the appropriate Node for a flat node.
     *
     * @phpstan-param  FlatNode  $flatNode
     */
    private function createNodeWrapper(int $index, array $flatNode): Node
    {
        $kind = $flatNode['kind'];

        return $kind >= NodeKind::EXTENSION_BASE ?
            $this->createExtensionWrapper($kind, $index) :
            $this->createBuiltInWrapper($kind, $index, $flatNode);
    }

    private function createExtensionWrapper(int $kind, int $index): Node
    {
        $registry = $this->getNodeKindRegistry();
        $nodeClass = $registry->getNodeClass($kind);

        if ($nodeClass !== null && class_exists($nodeClass)) {
            /** @var Node */
            return new $nodeClass($this, $index);
        }

        return new GenericNode($this, $index);
    }

    /**
     * @phpstan-param  FlatNode  $flatNode
     */
    private function createBuiltInWrapper(int $kind, int $index, array $flatNode): Node
    {
        return match ($kind) {
            NodeKind::Text => new TextNode($this, $index),
            NodeKind::Element => $this->createElementWrapper($index, $flatNode),
            NodeKind::Directive => new DirectiveNode($this, $index),
            NodeKind::DirectiveBlock => new DirectiveBlockNode($this, $index),
            NodeKind::Echo, NodeKind::RawEcho, NodeKind::TripleEcho => new EchoNode($this, $index),
            NodeKind::Comment => new CommentNode($this, $index),
            NodeKind::ConditionalComment => new ConditionalCommentNode($this, $index),
            NodeKind::BogusComment => new BogusCommentNode($this, $index),
            NodeKind::BladeComment => new BladeCommentNode($this, $index),
            NodeKind::PhpBlock => new PhpBlockNode($this, $index),
            NodeKind::PhpTag => new PhpTagNode($this, $index),
            NodeKind::Verbatim => new VerbatimNode($this, $index),
            NodeKind::Doctype => new DoctypeNode($this, $index),
            NodeKind::Cdata => new CdataNode($this, $index),
            NodeKind::Decl => new XmlDeclarationNode($this, $index),
            NodeKind::ProcessingInstruction => new ProcessingInstructionNode($this, $index),
            NodeKind::NonOutput => new EscapeNode($this, $index),
            NodeKind::ElementName, NodeKind::ClosingElementName => new ElementNameNode($this, $index),
            NodeKind::UnpairedClosingTag => new StrayClosingTagNode($this, $index),
            default => new GenericNode($this, $index),
        };
    }

    /**
     * Create an ElementNode, ComponentNode, or SlotNode depending on the tag name.
     *
     * @phpstan-param  FlatNode  $flatNode
     */
    private function createElementWrapper(int $index, array $flatNode): ElementNode
    {
        $tagName = $this->extractTagName($index, $flatNode);

        if ($this->componentManager->isComponent($tagName)) {
            $metadata = $this->componentManager->parseComponentName($tagName);
            if ($metadata !== null && $metadata->isSlot) {
                return new SlotNode($this, $index);
            }

            return new ComponentNode($this, $index);
        }

        return new ElementNode($this, $index);
    }

    /**
     * Extract the tag name from an element's flat node data.
     *
     * @phpstan-param  FlatNode  $flatNode
     */
    private function extractTagName(int $index, array $flatNode): string
    {
        $tagNameCount = $flatNode['data'] ?? 0;
        if ($tagNameCount > 0) {
            $tagNameStart = $flatNode['tokenStart'] + 1;

            return $this->collectTokenText($tagNameStart, $tagNameCount);
        }

        $firstChild = $flatNode['firstChild'] ?? -1;
        if ($firstChild !== -1 && isset($this->nodes[$firstChild])) {
            $childNode = $this->nodes[$firstChild];
            if ($childNode['kind'] === NodeKind::ElementName) {
                return $this->collectTokenText($childNode['tokenStart'], $childNode['tokenCount']);
            }
        }

        $tokenStart = $flatNode['tokenStart'] + 1;
        if ($tokenStart < count($this->tokens)) {
            $token = $this->tokens[$tokenStart];

            return substr($this->source, $token['start'], $token['end'] - $token['start']);
        }

        return '';
    }

    private function collectTokenText(int $start, int $count): string
    {
        $name = '';
        $tokenTotal = count($this->tokens);

        for ($i = 0; $i < $count && ($start + $i) < $tokenTotal; $i++) {
            $token = $this->tokens[$start + $i];
            $name .= substr($this->source, $token['start'], $token['end'] - $token['start']);
        }

        return $name;
    }

    /**
     * @internal
     *
     * @return FlatNode
     */
    public function getFlatNode(int $index): array
    {
        return $this->nodes[$index];
    }

    /**
     * @internal
     *
     * @return array{type: int, start: int, end: int}
     *
     * @throws OutOfBoundsExceptioN
     */
    public function getToken(int $index): array
    {
        if ($index < 0 || $index >= count($this->tokens)) {
            throw new OutOfBoundsException("Token index {$index} is out of bounds (0-".(count($this->tokens) - 1).')');
        }

        return $this->tokens[$index];
    }

    /**
     * @internal
     */
    public function getSourceSlice(int $start, int $end): string
    {
        return substr($this->source, $start, $end - $start);
    }

    /**
     * @internal
     */
    public function getDirectivesRegistry(): Directives
    {
        return $this->directives;
    }

    /**
     * @internal
     */
    public function getComponentManager(): ComponentManager
    {
        return $this->componentManager;
    }

    /**
     * @internal
     *
     * @return array<int, array{type: int, start: int, end: int}>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @internal
     *
     * @return array<int, FlatNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * Get synthetic content for a node, if any.
     *
     * @internal
     */
    public function getSyntheticContent(int $nodeIndex): ?string
    {
        return $this->syntheticContent[$nodeIndex] ?? null;
    }

    /**
     * Get metadata for a synthetic node.
     *
     * @internal
     *
     * @return array<string, mixed>|null
     */
    public function getSyntheticMeta(int $nodeIndex): ?array
    {
        return $this->syntheticMeta[$nodeIndex] ?? null;
    }

    /**
     * Check if a node is synthetic.
     *
     * @internal
     */
    public function isSyntheticNode(int $nodeIndex): bool
    {
        return isset($this->syntheticContent[$nodeIndex]);
    }

    /**
     * Get source string for a token range.
     *
     * @internal
     */
    public function getTokenRangeContent(int $tokenStart, int $tokenCount): string
    {
        if ($tokenCount === 0) {
            return '';
        }

        $tokenTotal = count($this->tokens);

        if ($tokenStart < 0 || $tokenStart >= $tokenTotal) {
            return '';
        }

        $endIndex = $tokenStart + $tokenCount - 1;
        if ($endIndex >= $tokenTotal) {
            $endIndex = $tokenTotal - 1;
        }

        $startToken = $this->tokens[$tokenStart];
        $endToken = $this->tokens[$endIndex];

        return substr($this->source, $startToken['start'], $endToken['end'] - $startToken['start']);
    }

    /**
     * Get source string for a node's full token range.
     *
     * @internal
     */
    public function getNodeTokenContent(int $index): string
    {
        $flat = $this->nodes[$index];

        return $this->getTokenRangeContent($flat['tokenStart'], $flat['tokenCount']);
    }

    /**
     * Check if a paired node has its closing delimiter.
     *
     * @internal
     */
    public function hasClosingDelimiter(int $index): bool
    {
        return ($this->nodes[$index]['data'] ?? 0) === 1;
    }

    /**
     * Find the first child node of a specific kind.
     *
     * @internal
     */
    public function findChildByKind(int $parentIdx, int $kind): int
    {
        $childIdx = $this->nodes[$parentIdx]['firstChild'] ?? -1;

        while ($childIdx !== -1) {
            $childFlat = $this->nodes[$childIdx];
            if ($childFlat['kind'] === $kind) {
                return $childIdx;
            }
            $childIdx = $childFlat['nextSibling'] ?? -1;
        }

        return -1;
    }

    /**
     * Find the source position where an element's opening tag ends.
     *
     * @internal
     */
    public function findOpeningTagEndPosition(int $elementIndex): int
    {
        $elementNode = $this->nodes[$elementIndex];
        $tokenIdx = $elementNode['tokenStart'];

        if ($tokenIdx < 0) {
            return -1;
        }

        $endTokenIdx = $tokenIdx + $elementNode['tokenCount'];
        $tokenLimit = count($this->tokens);
        $depth = 0;

        for ($i = $tokenIdx; $i < $endTokenIdx && $i < $tokenLimit; $i++) {
            $token = $this->tokens[$i];
            $type = $token['type'];

            if ($type === TokenType::EchoStart ||
                $type === TokenType::RawEchoStart ||
                $type === TokenType::TripleEchoStart ||
                $type === TokenType::PhpTagStart ||
                $type === TokenType::PhpBlockStart) {
                $depth++;
            } elseif ($type === TokenType::EchoEnd ||
                $type === TokenType::RawEchoEnd ||
                $type === TokenType::TripleEchoEnd ||
                $type === TokenType::PhpTagEnd ||
                $type === TokenType::PhpBlockEnd) {
                $depth--;
            }

            if ($depth === 0 && $type === TokenType::GreaterThan) {
                return $token['end'];
            }

            if ($depth === 0 && $type === TokenType::DeclEnd) {
                return $token['end'];
            }

            if ($depth === 0 && $type === TokenType::SyntheticClose) {
                return $token['start'];
            }
        }

        return strlen($this->source);
    }
}
