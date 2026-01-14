<?php

declare(strict_types=1);

namespace Forte\Ast\Document;

use Countable;
use Forte\Ast\BladeCommentNode;
use Forte\Ast\Components\ComponentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\Concerns\ManagesAst;
use Forte\Ast\Document\Concerns\ManagesChildren;
use Forte\Ast\Document\Concerns\ManagesDiagnostics;
use Forte\Ast\Document\Concerns\ManagesNodeMetadata;
use Forte\Ast\Document\Concerns\ManagesOffsets;
use Forte\Ast\Document\Concerns\ManagesRewrites;
use Forte\Ast\Document\Concerns\ManagesSiblings;
use Forte\Ast\Document\Concerns\QueriesComments;
use Forte\Ast\Document\Concerns\QueriesDirectives;
use Forte\Ast\Document\Concerns\QueriesEchoes;
use Forte\Ast\Document\Concerns\QueriesElements;
use Forte\Ast\Document\Concerns\QueriesNodes;
use Forte\Ast\Document\Concerns\QueriesPhpBlocks;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Node;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;
use Forte\Components\ComponentManager;
use Forte\Lexer\Lexer;
use Forte\Lexer\LexerError;
use Forte\Lexer\LineIndex;
use Forte\Parser\Directives\Directives;
use Forte\Parser\Errors\ParseError;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\ParserOptions;
use Forte\Parser\TreeBuilder;
use Generator;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * @phpstan-import-type FlatNode from TreeBuilder
 *
 * @property-read LazyCollection<int, PhpBlockNode> $phpBlocks
 * @property-read LazyCollection<int, PhpTagNode> $phpTags
 * @property-read LazyCollection<int, TextNode> $text
 * @property-read LazyCollection<int, CommentNode|BladeCommentNode> $comments
 * @property-read LazyCollection<int, CommentNode> $htmlComments
 * @property-read LazyCollection<int, BladeCommentNode> $bladeComments
 * @property-read LazyCollection<int, EchoNode> $echoes
 * @property-read LazyCollection<int, EchoNode> $rawEchoes
 * @property-read LazyCollection<int, EchoNode> $escapedEchoes
 * @property-read LazyCollection<int, EchoNode> $tripleEchoes
 * @property-read LazyCollection<int, DirectiveNode> $directives
 * @property-read LazyCollection<int, DirectiveBlockNode> $blockDirectives
 * @property-read LazyCollection<int, ElementNode> $elements
 * @property-read LazyCollection<int, ComponentNode> $components
 *
 * @implements IteratorAggregate<int, Node>
 */
class Document implements Countable, IteratorAggregate, Stringable
{
    use ManagesAst,
        ManagesChildren,
        ManagesDiagnostics,
        ManagesNodeMetadata,
        ManagesOffsets,
        ManagesRewrites,
        ManagesSiblings,
        QueriesComments,
        QueriesDirectives,
        QueriesEchoes,
        QueriesElements,
        QueriesNodes,
        QueriesPhpBlocks;

    private const NONE = -1;

    /** @var array<int, FlatNode> */
    private array $nodes;

    /** @var array<int, array{type: int, start: int, end: int}> */
    private array $tokens;

    /** @var array<int, int> */
    private array $rootChildren;

    /** @var array<int, Node> */
    private array $nodeCache = [];

    private ?LineIndex $lineIndex = null;

    /** @var array<int, string>|null */
    private ?array $linesCache = null;

    /** @var array<int, ParseError> */
    protected array $parseErrors = [];

    protected ?string $filePath = null;

    /** @var array<int, string> */
    private array $syntheticContent = [];

    /** @var array<int, array<string, mixed>> */
    private array $syntheticMeta = [];

    /** @var array<int, array<string, mixed>> */
    private array $nodeMetadata = [];

    /** @var array<int, array<string, true>> */
    private array $nodeTags = [];

    /**
     * @param  array<int, FlatNode>  $nodes
     * @param  array<int, array{type: int, start: int, end: int}>  $tokens
     * @param  array<int, int>  $rootChildren
     * @param  array<int, string>  $syntheticContent
     * @param  array<int, array<string, mixed>>  $syntheticMeta
     * @param  array<int, LexerError>  $lexerErrors
     */
    private function __construct(
        array $nodes,
        array $tokens,
        private readonly string $source,
        private readonly Directives $directives,
        private readonly ComponentManager $componentManager,
        array $rootChildren,
        array $syntheticContent = [],
        array $syntheticMeta = [],
        protected array $lexerErrors = [],
        private readonly ?NodeKindRegistry $nodeKindRegistry = null
    ) {
        $this->nodes = $nodes;
        $this->tokens = $tokens;
        $this->rootChildren = $rootChildren;
        $this->syntheticContent = $syntheticContent;
        $this->syntheticMeta = $syntheticMeta;
    }

    public function getNodeKindRegistry(): NodeKindRegistry
    {
        return $this->nodeKindRegistry ?? app(NodeKindRegistry::class);
    }

    /**
     * Parse a template string into a Document.
     */
    public static function parse(string $template, ?ParserOptions $options = null): self
    {
        $options ??= ParserOptions::defaults();

        $directives = $options->getDirectives();
        $componentManager = $options->getComponentManager();
        $registry = $options->hasExtensions() ? $options->getExtensionRegistry() : null;

        $lexer = new Lexer($template, $directives);

        $registry?->configureLexer($lexer);

        $lexerResult = $lexer->tokenize();

        if ($directives->acceptsAllDirectives()) {
            $directives->train($lexerResult->tokens, $template);
        }

        $builder = new TreeBuilder($lexerResult->tokens, $template, $directives);

        $registry?->configureTreeBuilder($builder);

        $treeResult = $builder->build();

        $nodes = $treeResult['nodes'];

        $rootChildren = [];

        if (! empty($nodes)) {
            $root = $nodes[0];
            $childIdx = $root['firstChild'] ?? self::NONE;
            while ($childIdx !== self::NONE && isset($nodes[$childIdx])) {
                $rootChildren[] = $childIdx;
                $childIdx = $nodes[$childIdx]['nextSibling'] ?? self::NONE;
            }
        }

        $nodeKindRegistry = $registry?->getNodeRegistry();

        return new self(
            $nodes,
            $lexerResult->tokens,
            $template,
            $directives,
            $componentManager,
            $rootChildren,
            [],
            [],
            $lexerResult->errors,
            $nodeKindRegistry
        );
    }

    /**
     * Create a Document from pre-built parts.
     *
     * @param  array<int, array<string, mixed>>  $nodes  The nodes
     * @param  array<int, array<string, mixed>>  $tokens  Token stream
     * @param  string  $source  Original source template
     * @param  array<int, string>  $syntheticContent  Rendered content for synthetic nodes
     * @param  array<int, array<string, mixed>>  $syntheticMeta  Metadata for synthetic nodes
     *
     * @internal
     */
    public static function fromParts(
        array $nodes,
        array $tokens,
        string $source,
        Directives $directives,
        ComponentManager $componentManager,
        array $syntheticContent = [],
        array $syntheticMeta = [],
        ?NodeKindRegistry $nodeKindRegistry = null
    ): self {
        /** @var list<int> $rootChildren */
        $rootChildren = [];
        if (! empty($nodes)) {
            $root = $nodes[0];
            $firstChild = $root['firstChild'] ?? self::NONE;
            $childIdx = is_int($firstChild) ? $firstChild : self::NONE;

            while ($childIdx !== self::NONE && isset($nodes[$childIdx])) {
                $rootChildren[] = $childIdx;
                $nextSibling = $nodes[$childIdx]['nextSibling'] ?? self::NONE;
                $childIdx = is_int($nextSibling) ? $nextSibling : self::NONE;
            }
        }

        /** @var array<int, FlatNode> $nodes */
        /** @var array<int, array{type: int, start: int, end: int}> $tokens */
        return new self(
            $nodes,
            $tokens,
            $source,
            $directives,
            $componentManager,
            $rootChildren,
            $syntheticContent,
            $syntheticMeta,
            [],
            $nodeKindRegistry
        );
    }

    /** @var array<int, Node>|null */
    private ?array $childrenCache = null;

    /**
     * Render the entire document back to string.
     */
    public function render(): string
    {
        $output = '';
        foreach ($this->children() as $child) {
            $output .= $child->render();
        }

        return $output;
    }

    /**
     * Get the original source.
     */
    public function getDocumentContent(): string
    {
        return $this->source;
    }

    /**
     * Walk all nodes in the document depth-first.
     *
     * @return iterable<Node>
     */
    private function allDescendants(): iterable
    {
        foreach ($this->children() as $child) {
            yield $child;
            yield from $child->descendants();
        }
    }

    /**
     * Query nodes by type, with an optional filter.
     *
     * @template T of Node
     *
     * @param  class-string<T>  $class
     * @param  (callable(T): bool)|null  $filter
     * @return LazyCollection<int, T>
     */
    protected function queryNodesOfType(string $class, ?callable $filter = null): LazyCollection
    {
        return LazyCollection::make(function () use ($class, $filter): Generator {
            foreach ($this->allDescendants() as $node) {
                if ($node instanceof $class && ($filter === null || $filter($node))) {
                    yield $node;
                }
            }
        });
    }

    /**
     * Get the original source code.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Get a substring from the original source text using absolute offsets.
     *
     * @param  int  $startOffset  Inclusive start offset
     * @param  int  $endOffset  Exclusive end offset
     */
    public function getText(int $startOffset, int $endOffset): string
    {
        return substr($this->source, $startOffset, $endOffset - $startOffset);
    }

    /**
     * Get the raw text between two nodes.
     */
    public function getTextBetween(Node $start, Node $end): string
    {
        return $this->getText($start->endOffset(), $end->startOffset());
    }

    /**
     * Get the source file path associated with the document, if any.
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Set the source file path for this document.
     */
    public function setFilePath(?string $path): self
    {
        $this->filePath = $path;

        return $this;
    }

    /**
     * Walk the tree, calling a callback for each node.
     *
     * @param  callable(Node): void  $callback
     */
    public function walk(callable $callback): self
    {
        foreach ($this->allDescendants() as $node) {
            $callback($node);
        }

        return $this;
    }

    /**
     * @param  bool|callable  $condition  Truthy value or closure
     * @param  callable  $then  Invoked when the condition is truthy
     * @param  callable|null  $else  Invoked when the condition is falsy
     */
    public function when(bool|callable $condition, callable $then, ?callable $else = null): self
    {
        if (value($condition)) {
            $then($this);
        } elseif ($else) {
            $else($this);
        }

        return $this;
    }

    public function componentManager(): ComponentManager
    {
        return $this->componentManager;
    }

    /**
     * @param  bool|callable  $condition  Falsy value or closure
     * @param  callable  $callback  Invoked when the condition is falsy
     * @param  callable|null  $default  Invoked when the condition is truthy
     */
    public function unless(bool|callable $condition, callable $callback, ?callable $default = null): self
    {
        if (! value($condition)) {
            $callback($this);
        } elseif ($default) {
            $default($this);
        }

        return $this;
    }

    public function tap(callable $callback): self
    {
        $callback($this);

        return $this;
    }

    /**
     * Check if the document has non-whitespace content.
     */
    public function filled(): bool
    {
        return trim($this->source) !== '';
    }

    /**
     * Check if the document is empty or contains only whitespace.
     */
    public function blank(): bool
    {
        return ! $this->filled();
    }

    /**
     * Get the depth of a node in the tree.
     */
    public function getNodeDepth(Node $node): int
    {
        $depth = 0;
        $flat = $node->getFlatNode();
        $parentIdx = $flat['parent'] ?? -1;

        while ($parentIdx > 0) {
            $depth++;
            $parentFlat = $this->nodes[$parentIdx];
            $parentIdx = $parentFlat['parent'] ?? -1;
        }

        return $depth;
    }

    /**
     * @var array<string, string>
     */
    private const QUERY_METHODS = [
        'phpBlocks' => 'phpBlocks',
        'phpTags' => 'phpTags',
        'text' => 'text',
        'comments' => 'comments',
        'bladeComments' => 'bladeComments',
        'htmlComments' => 'htmlComments',
        'echoes' => 'queryEchoes',
        'rawEchoes' => 'queryRawEchoes',
        'escapedEchoes' => 'queryEscapedEchoes',
        'tripleEchoes' => 'queryTripleEchoes',
        'directives' => 'directives',
        'blockDirectives' => 'blockDirectives',
        'elements' => 'elements',
        'components' => 'components',
    ];

    /**
     * @return LazyCollection<int, Node>
     */
    public function __get(string $name): mixed
    {
        if (isset(self::QUERY_METHODS[$name])) {
            return $this->{self::QUERY_METHODS[$name]}();
        }

        throw new InvalidArgumentException("Undefined property: Document::\${$name}");
    }

    public function __isset(string $name): bool
    {
        return isset(self::QUERY_METHODS[$name]);
    }

    /**
     * Get the number of top-level nodes.
     */
    public function count(): int
    {
        return count($this->rootChildren);
    }

    /**
     * @return Generator<int, Node>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->children() as $child) {
            yield $child;
        }
    }

    /**
     * Convert document to string.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
