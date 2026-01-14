<?php

declare(strict_types=1);

namespace Forte\Parser;

use Forte\Ast\Elements\VoidElements;
use Forte\Extensions\AttributeExtension;
use Forte\Lexer\Tokens\TokenType;
use Forte\Parser\Concerns\ProcessesComments;
use Forte\Parser\Concerns\ProcessesConditionalPairingDirectives;
use Forte\Parser\Concerns\ProcessesDirectives;
use Forte\Parser\Concerns\ProcessesEchoes;
use Forte\Parser\Concerns\ProcessesElements;
use Forte\Parser\Concerns\ProcessesMarkup;
use Forte\Parser\Concerns\ProcessesPhp;
use Forte\Parser\Concerns\ProcessesSwitchDirectives;
use Forte\Parser\Directives\Directives;
use Forte\Parser\Directives\DirectiveTokenIndex;
use Forte\Parser\Extension\AttributeParserContext;
use Forte\Parser\Extension\ExtensionStack;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\OptionalTags\OptionalTagConfig;
use Forte\Parser\OptionalTags\OptionalTagHandler;
use RuntimeException;

/**
 * @phpstan-type FlatNode array{kind: int, parent: int, firstChild: int, lastChild: int, nextSibling: int, tokenStart: int, tokenCount: int, genericOffset: int, data: int}
 * @phpstan-type Token array{type: int, start: int, end: int}
 * @phpstan-type BuildResult array{nodes: array<int, FlatNode>, source: string, tokens: array<int, Token>}
 */
class TreeBuilder
{
    use ProcessesComments;
    use ProcessesConditionalPairingDirectives;
    use ProcessesDirectives;
    use ProcessesEchoes;
    use ProcessesElements;
    use ProcessesMarkup;
    use ProcessesPhp;
    use ProcessesSwitchDirectives;

    /** @var array<int, FlatNode> */
    private array $nodes = [];

    private int $nodeCount = 0;

    /** @var array<int, int> */
    private array $openElements = [];

    /** @var array<int, array{blockIdx: int, startDirectiveIdx: int, name: string, elementStackBase: int}> */
    private array $openDirectives = [];

    /** @var array<int, array{blockIdx: int, currentBranchIdx: int, name: string, elementStackBase: int}> */
    private array $openConditions = [];

    private int $pos = 0;

    /** @var array<int, string> */
    private array $tagNames = [];

    /** @var array<string, array<int>> */
    private array $tagNameStacks = [];

    private ?int $detachedParent = null;

    private const NONE = -1;

    private OptionalTagConfig $optionalTagConfig;

    private OptionalTagHandler $optionalTagHandler;

    private Directives $directives;

    private ?ExtensionStack $extensions = null;

    private ?TreeContext $extensionContext = null;

    /** @var array<int, array<string, mixed>> */
    private array $nodeMetadata = [];

    private ?DirectiveTokenIndex $directiveIndex = null;

    private int $tokenTotal;

    /** @var array<int, AttributeExtension> */
    private array $attributeExtensions = [];

    private ?AttributeParserContext $attrExtContext = null;

    private int $maxElementDepth = 512;

    private int $maxDirectiveDepth = 256;

    private int $maxConditionDepth = 256;

    /** @param  array<int, array{type: int, start: int, end: int}>  $tokens */
    public function __construct(
        private readonly array $tokens,
        private readonly string $source,
        ?Directives $directives = null
    ) {
        $this->optionalTagConfig = new OptionalTagConfig;
        $this->optionalTagHandler = new OptionalTagHandler($this->optionalTagConfig);
        $this->directives = $directives ?? Directives::withDefaults();
        $this->tokenTotal = count($tokens);
    }

    /**
     * Configure stack depth limits.
     *
     * @param  int  $elements  Maximum element nesting depth (default: 512)
     * @param  int  $directives  Maximum directive nesting depth (default: 256)
     * @param  int  $conditions  Maximum condition nesting depth (default: 256)
     * @return $this
     */
    public function setDepthLimits(int $elements = 512, int $directives = 256, int $conditions = 256): self
    {
        $this->maxElementDepth = max(1, $elements);
        $this->maxDirectiveDepth = max(1, $directives);
        $this->maxConditionDepth = max(1, $conditions);

        return $this;
    }

    /**
     * @throws RuntimeException
     */
    protected function checkElementDepth(): void
    {
        if (count($this->openElements) >= $this->maxElementDepth) {
            throw new RuntimeException(
                "Maximum element nesting depth ({$this->maxElementDepth}) exceeded."
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    protected function checkDirectiveDepth(): void
    {
        if (count($this->openDirectives) >= $this->maxDirectiveDepth) {
            throw new RuntimeException(
                "Maximum directive nesting depth ({$this->maxDirectiveDepth}) exceeded."
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    protected function checkConditionDepth(): void
    {
        if (count($this->openConditions) >= $this->maxConditionDepth) {
            throw new RuntimeException(
                "Maximum condition nesting depth ({$this->maxConditionDepth}) exceeded."
            );
        }
    }

    /**
     * @return BuildResult
     */
    public function build(): array
    {
        $this->nodes[] = $this->createNode(
            kind: NodeKind::Root,
            parent: self::NONE,
            tokenStart: 0,
            tokenCount: 0
        );
        $this->nodeCount = 1;
        $this->openElements[] = 0;

        while ($this->pos < $this->tokenTotal) {
            $this->processToken();
        }

        $this->closeRemainingDirectives();
        $this->closeRemainingElements();

        return [
            'nodes' => $this->nodes,
            'source' => $this->source,
            'tokens' => $this->tokens,
        ];
    }

    protected function processToken(): void
    {
        if ($this->extensions !== null) {
            $consumed = $this->tryExtensions();
            if ($consumed > 0) {
                $this->pos += $consumed;

                return;
            }
        }

        $tokenType = $this->tokens[$this->pos]['type'];

        match ($tokenType) {
            TokenType::LessThan => $this->processElementStart(),
            TokenType::Text, TokenType::TagName => $this->processText(),
            TokenType::Whitespace => $this->processWhitespace(),
            TokenType::AtSign => $this->processAtSign(),
            TokenType::EchoStart => $this->processEcho(),
            TokenType::RawEchoStart => $this->processRawEcho(),
            TokenType::TripleEchoStart => $this->processTripleEcho(),
            TokenType::Directive => $this->processDirective(),
            TokenType::VerbatimStart => $this->createVerbatim($this->pos),
            TokenType::BladeCommentStart => $this->createBladeComment($this->pos),
            TokenType::CommentStart => $this->createHtmlComment($this->pos),
            TokenType::BogusComment => $this->createBogusComment($this->pos),
            TokenType::ConditionalCommentStart => $this->createConditionalComment($this->pos),
            TokenType::CdataStart => $this->createcdata($this->pos),
            TokenType::DeclStart => $this->createDecl($this->pos),
            TokenType::PIStart => $this->createProcessingInstruction($this->pos),
            TokenType::DoctypeStart => $this->createDoctype($this->pos),
            TokenType::PhpBlockStart => $this->createPhpBlock($this->pos),
            TokenType::PhpTagStart => $this->createPhpTag($this->pos),
            TokenType::PhpBlockEnd => $this->processOrphanPhpBlockEnd(),
            TokenType::ConditionalCommentEnd, TokenType::GreaterThan => $this->emitSingleTokenText(),
            default => $this->pos++,
        };
    }

    /**
     * @return FlatNode
     */
    protected function createNode(
        int $kind,
        int $parent,
        int $tokenStart,
        int $tokenCount = 0,
        int $genericOffset = 0,
        int $data = 0
    ): array {
        return [
            'kind' => $kind,
            'parent' => $parent,
            'firstChild' => self::NONE,
            'lastChild' => self::NONE,
            'nextSibling' => self::NONE,
            'tokenStart' => $tokenStart,
            'tokenCount' => $tokenCount,
            'genericOffset' => $genericOffset,
            'data' => $data,
        ];
    }

    protected function createBlockNode(int $startPos, int $endTokenType, int $nodeKind): void
    {
        $tokens = $this->tokens;
        $endPos = $this->pos;
        $hasClosing = false;

        while ($endPos < $this->tokenTotal) {
            if ($tokens[$endPos]['type'] === $endTokenType) {
                $hasClosing = true;
                $endPos++;
                break;
            }
            $endPos++;
        }

        $this->addChild($this->createNode(
            kind: $nodeKind,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: $endPos - $startPos,
            data: $hasClosing ? 1 : 0
        ));
        $this->pos = $endPos;
    }

    /**
     * @phpstan-param  FlatNode  $node
     */
    protected function addChild(array $node): int
    {
        $this->implicitlyCloseVoidElements();

        $parentIdx = $this->getCurrentParent();
        $node['parent'] = $parentIdx;

        $nodeIdx = $this->nodeCount++;
        $this->nodes[$nodeIdx] = $node;

        $parent = &$this->nodes[$parentIdx];

        if ($parent['firstChild'] === self::NONE) {
            $parent['firstChild'] = $nodeIdx;
        } else {
            $lastChildIdx = $parent['lastChild'];
            $this->nodes[$lastChildIdx]['nextSibling'] = $nodeIdx;
        }

        $parent['lastChild'] = $nodeIdx;

        return $nodeIdx;
    }

    public function getCurrentParent(): int
    {
        if ($this->detachedParent !== null) {
            return $this->detachedParent;
        }

        $last = end($this->openElements);

        return $last !== false ? $last : 0;
    }

    protected function implicitlyCloseVoidElements(): void
    {
        while (count($this->openElements) > 1) {
            $topIdx = end($this->openElements);
            $tagName = $this->tagNames[$topIdx] ?? null;

            if ($tagName === null || ! isset(VoidElements::$voidElements[$tagName])) {
                break;
            }

            array_pop($this->openElements);

            if (isset($this->tagNameStacks[$tagName])) {
                array_pop($this->tagNameStacks[$tagName]);
                if (empty($this->tagNameStacks[$tagName])) {
                    unset($this->tagNameStacks[$tagName]);
                }
            }
        }
    }

    protected function processText(): void
    {
        $parentIdx = $this->getCurrentParent();
        $parent = $this->nodes[$parentIdx];

        if ($parent['lastChild'] !== self::NONE) {
            $lastChildIdx = $parent['lastChild'];
            if ($this->nodes[$lastChildIdx]['kind'] === NodeKind::Text) {
                $this->nodes[$lastChildIdx]['tokenCount']++;
                $this->pos++;

                return;
            }
        }

        $startPos = $this->pos;
        $this->pos++;

        $this->addChild($this->createNode(
            kind: NodeKind::Text,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: 1
        ));
    }

    protected function processWhitespace(): void
    {
        $this->processText();
    }

    protected function processAtSign(): void
    {
        $startPos = $this->pos;
        $this->pos++;

        $this->addChild($this->createNode(
            kind: NodeKind::NonOutput,
            parent: 0,
            tokenStart: $startPos,
            tokenCount: 1
        ));
    }

    protected function getTagName(int $nodeIdx): string
    {
        return $this->tagNames[$nodeIdx] ?? '';
    }

    protected function cleanupTagNameStack(?int $elementIdx): void
    {
        if ($elementIdx === null) {
            return;
        }

        $tagName = $this->tagNames[$elementIdx] ?? null;

        if ($tagName !== null && isset($this->tagNameStacks[$tagName]) && ! empty($this->tagNameStacks[$tagName])) {
            array_pop($this->tagNameStacks[$tagName]);

            if (empty($this->tagNameStacks[$tagName])) {
                unset($this->tagNameStacks[$tagName]);
            }
        }
    }

    /**
     * Pop an element from the stack if it's currently on top.
     */
    protected function popIfTop(int $idx): void
    {
        if (end($this->openElements) === $idx) {
            $poppedIdx = array_pop($this->openElements);
            $this->cleanupTagNameStack($poppedIdx);
        }
    }

    protected function popElementsToDepth(int $targetDepth): void
    {
        while (count($this->openElements) > $targetDepth) {
            $poppedIdx = array_pop($this->openElements);
            $this->cleanupTagNameStack($poppedIdx);
        }
    }

    /**
     * @return array<int>
     */
    public function buildFromTokenRange(int $tokenStart, int $tokenEnd, int $parentIdx): array
    {
        $savedPos = $this->pos;
        $savedDetachedParent = $this->detachedParent;
        $startNodeCount = $this->nodeCount;

        $this->pos = $tokenStart;
        $this->detachedParent = $parentIdx;

        while ($this->pos < $tokenEnd && $this->pos < $this->tokenTotal) {
            $this->processToken();
        }

        $createdNodes = [];
        for ($i = $startNodeCount; $i < $this->nodeCount; $i++) {
            if ($this->nodes[$i]['parent'] === $parentIdx) {
                $createdNodes[] = $i;
            }
        }

        $this->pos = $savedPos;
        $this->detachedParent = $savedDetachedParent;

        return $createdNodes;
    }

    /**
     * @param  array<int>  $sentinels  Token types to stop at
     */
    public function collectUntil(array $sentinels, int $parentIdx): void
    {
        $savedDetachedParent = $this->detachedParent;
        $savedOpenDirectives = $this->openDirectives;
        $savedOpenConditions = $this->openConditions;

        $this->detachedParent = $parentIdx;
        $this->openDirectives = [];
        $this->openConditions = [];

        try {
            while ($this->pos < $this->tokenTotal) {
                $tokenType = $this->tokens[$this->pos]['type'];

                if (in_array($tokenType, $sentinels, true)) {
                    $insideBlock = ! empty($this->openDirectives) || ! empty($this->openConditions);
                    if ($tokenType === TokenType::Whitespace && $insideBlock) {
                        $this->processToken();

                        continue;
                    }
                    break;
                }

                $this->processToken();
            }
        } finally {
            $this->detachedParent = $savedDetachedParent;
            $this->openDirectives = $savedOpenDirectives;
            $this->openConditions = $savedOpenConditions;
        }
    }

    public function registerExtension(TreeExtension $extension, ?NodeKindRegistry $registry = null): self
    {
        if ($this->extensions === null) {
            $this->extensions = new ExtensionStack;
        }

        $this->extensions->add($extension);
        $extension->registerNodeKinds($registry ?? app(NodeKindRegistry::class));

        return $this;
    }

    /**
     * Register an attribute extension.
     *
     * @param  AttributeExtension  $extension  The extension to register
     * @param  NodeKindRegistry|null  $registry  Optional node kind registry
     * @return $this
     */
    public function registerAttributeExtension(AttributeExtension $extension, ?NodeKindRegistry $registry = null): self
    {
        $extension->registerAttributeNodeKind($registry ?? app(NodeKindRegistry::class));

        $tokenType = $extension->getTokenType();
        $this->attributeExtensions[$tokenType] = $extension;

        return $this;
    }

    /**
     * Get attribute extension for a token type.
     */
    public function getAttributeExtensionForTokenType(int $tokenType): ?AttributeExtension
    {
        return $this->attributeExtensions[$tokenType] ?? null;
    }

    /**
     * Check if there are any attribute extensions registered.
     */
    public function hasAttributeExtensions(): bool
    {
        return ! empty($this->attributeExtensions);
    }

    protected function getExtensionContext(): TreeContext
    {
        return $this->extensionContext ??= new TreeContext($this);
    }

    protected function tryExtensions(): int
    {
        if ($this->extensions === null) {
            return 0;
        }

        return $this->extensions->tryHandle($this->getExtensionContext());
    }

    public function position(): int
    {
        return $this->pos;
    }

    public function getTokenCount(): int
    {
        return $this->tokenTotal;
    }

    /**
     * @return array{type: int, start: int, end: int}|null
     */
    public function getCurrentToken(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    /**
     * @return array{type: int, start: int, end: int}|null
     */
    public function peekToken(int $offset = 0): ?array
    {
        return $this->tokens[$this->pos + $offset] ?? null;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function directiveIndex(): DirectiveTokenIndex
    {
        if ($this->directiveIndex === null) {
            $this->directiveIndex = new DirectiveTokenIndex($this->tokens, $this->source);
        }

        return $this->directiveIndex;
    }

    public function createExtensionNode(int $kind, int $tokenStart, int $tokenCount, int $data = 0): int
    {
        $node = $this->createNode(
            kind: $kind,
            parent: 0,
            tokenStart: $tokenStart,
            tokenCount: $tokenCount,
            data: $data
        );

        $this->nodes[] = $node;
        $index = $this->nodeCount;
        $this->nodeCount++;

        return $index;
    }

    public function addChildNode(int $nodeIndex): void
    {
        $parentIdx = $this->getCurrentParent();

        $this->nodes[$nodeIndex]['parent'] = $parentIdx;

        $parentNode = $this->nodes[$parentIdx];
        if ($parentNode['firstChild'] === self::NONE) {
            $this->nodes[$parentIdx]['firstChild'] = $nodeIndex;
            $this->nodes[$parentIdx]['lastChild'] = $nodeIndex;
        } else {
            $lastChild = $parentNode['lastChild'];
            $this->nodes[$lastChild]['nextSibling'] = $nodeIndex;
            $this->nodes[$parentIdx]['lastChild'] = $nodeIndex;
        }
    }

    /**
     * Link a node as the first child of a parent.
     */
    public function linkAsFirstChild(int $parentIdx, int $childIdx): void
    {
        $this->nodes[$childIdx]['parent'] = $parentIdx;
        $this->nodes[$parentIdx]['firstChild'] = $childIdx;
        $this->nodes[$parentIdx]['lastChild'] = $childIdx;
    }

    /**
     * Link a node as a sibling after the previous node.
     */
    public function linkAsSibling(int $prevIdx, int $nextIdx, int $parentIdx): void
    {
        $this->nodes[$nextIdx]['parent'] = $parentIdx;
        $this->nodes[$prevIdx]['nextSibling'] = $nextIdx;
        $this->nodes[$parentIdx]['lastChild'] = $nextIdx;
    }

    /**
     * Create a node and link it as the first (and only) child of a parent.
     */
    protected function createFirstChild(int $parentIdx, int $kind, int $tokenStart, int $tokenCount): int
    {
        $childIdx = $this->nodeCount++;
        $this->nodes[$childIdx] = $this->createNode(
            kind: $kind,
            parent: $parentIdx,
            tokenStart: $tokenStart,
            tokenCount: $tokenCount
        );
        $this->nodes[$parentIdx]['firstChild'] = $childIdx;
        $this->nodes[$parentIdx]['lastChild'] = $childIdx;

        return $childIdx;
    }

    /**
     * Create a node and link it as a sibling after a previous child.
     */
    protected function createSiblingChild(int $prevSiblingIdx, int $parentIdx, int $kind, int $tokenStart, int $tokenCount): int
    {
        $childIdx = $this->nodeCount++;
        $this->nodes[$childIdx] = $this->createNode(
            kind: $kind,
            parent: $parentIdx,
            tokenStart: $tokenStart,
            tokenCount: $tokenCount
        );
        $this->nodes[$prevSiblingIdx]['nextSibling'] = $childIdx;
        $this->nodes[$parentIdx]['lastChild'] = $childIdx;

        return $childIdx;
    }

    public function pushOpenElement(int $nodeIndex): void
    {
        $this->checkElementDepth();
        $this->openElements[] = $nodeIndex;
    }

    public function popOpenElement(): ?int
    {
        if (empty($this->openElements)) {
            return null;
        }

        return array_pop($this->openElements);
    }

    public function advancePosition(int $tokens): void
    {
        $this->pos += $tokens;
    }

    public function setNodeMetadata(int $nodeIndex, string $key, mixed $value): void
    {
        $this->nodeMetadata[$nodeIndex][$key] = $value;
    }

    public function getNodeMetadata(int $nodeIndex, string $key): mixed
    {
        return $this->nodeMetadata[$nodeIndex][$key] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllNodeMetadata(): array
    {
        return $this->nodeMetadata;
    }
}
