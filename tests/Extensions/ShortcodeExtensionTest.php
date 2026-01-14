<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\Node;
use Forte\Ast\TextNode;
use Forte\Components\ComponentManager;
use Forte\Extensions\ExtensionRegistry;
use Forte\Extensions\ForteExtension;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Directives\Directives;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\TreeBuilder;

class ShortcodeNode extends Node
{
    public function isOpening(): bool
    {
        return ! $this->isClosing();
    }

    public function isClosing(): bool
    {
        $content = $this->getDocumentContent();

        return str_starts_with($content, '[/');
    }

    public function name(): string
    {
        $content = $this->getDocumentContent();

        if (str_starts_with($content, '[/')) {
            $inner = substr($content, 2, -1);
        } else {
            $inner = substr($content, 1, -1);
        }

        if (preg_match('/^[\w\-_]+/', $inner, $match)) {
            return $match[0];
        }

        return $inner;
    }

    public function attributesRaw(): ?string
    {
        if ($this->isClosing()) {
            return null;
        }

        $content = $this->getDocumentContent();
        $inner = substr($content, 1, -1);

        $spacePos = strpos($inner, ' ');
        if ($spacePos === false) {
            return null;
        }

        return trim(substr($inner, $spacePos + 1));
    }

    /**
     * @return array<string, string|true>
     */
    public function attributes(): array
    {
        $raw = $this->attributesRaw();
        if ($raw === null || $raw === '') {
            return [];
        }

        $attrs = [];

        // Match key="value", key='value', key=value, or just key
        preg_match_all('/(\w+)(?:=(?:"([^"]*)"|\'([^\']*)\'|(\S+)))?/', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2] ?? $match[3] ?? $match[4] ?? true;
            $attrs[$key] = $value;
        }

        return $attrs;
    }
}

class ShortcodeExtension implements ForteExtension, LexerExtension, TreeExtension
{
    private int $shortcodeOpenType;

    private int $shortcodeCloseType;

    private int $shortcodeKind;

    public function id(): string
    {
        return 'shortcode';
    }

    public function name(): string
    {
        return 'Shortcode Extension';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function conflicts(): array
    {
        return [];
    }

    public function getOptions(): array
    {
        return [];
    }

    public function priority(): int
    {
        return 100;
    }

    public function triggerCharacters(): string
    {
        return '[';
    }

    public function registerTokenTypes(TokenTypeRegistry $registry): array
    {
        $this->shortcodeOpenType = $registry->register('shortcode', 'ShortcodeOpen', 'ShortcodeOpen');
        $this->shortcodeCloseType = $registry->register('shortcode', 'ShortcodeClose', 'ShortcodeClose');

        return [$this->shortcodeOpenType, $this->shortcodeCloseType];
    }

    public function shouldActivate(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '[') {
            return false;
        }

        $next = $ctx->peek(1);

        if ($next === '@') {
            return false;
        }

        return $next !== null && (ctype_alnum($next) || $next === '/' || $next === '-' || $next === '_');
    }

    public function tokenize(LexerContext $ctx): bool
    {
        if (! $this->shouldActivate($ctx)) {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance(); // consume '['

        $isClosing = false;
        if ($ctx->current() === '/') {
            $isClosing = true;
            $ctx->advance();
        }

        // Parse name
        $name = '';
        while ($ctx->current() !== null &&
               (ctype_alnum($ctx->current()) ||
                $ctx->current() === '-' ||
                $ctx->current() === '_')) {
            $name .= $ctx->current();
            $ctx->advance();
        }

        if ($name === '') {
            return false;
        }

        if ($isClosing) {
            // Skip whitespace
            while ($ctx->current() === ' ') {
                $ctx->advance();
            }

            if ($ctx->current() === ']') {
                $ctx->advance();
                $ctx->emit($this->shortcodeCloseType, $start, $ctx->position());

                return true;
            }

            return false;
        }

        // Parse attributes (skip over them)
        $this->skipAttributes($ctx);

        if ($ctx->current() === ']') {
            $ctx->advance();
            $ctx->emit($this->shortcodeOpenType, $start, $ctx->position());

            return true;
        }

        return false;
    }

    private function skipAttributes(LexerContext $ctx): void
    {
        while ($ctx->current() !== ']' && $ctx->current() !== null) {
            if ($ctx->current() === '"' || $ctx->current() === "'") {
                $quote = $ctx->current();
                $ctx->advance();
                while ($ctx->current() !== $quote && $ctx->current() !== null) {
                    $ctx->advance();
                }
                if ($ctx->current() === $quote) {
                    $ctx->advance();
                }
            } else {
                $ctx->advance();
            }
        }
    }

    public function registerNodeKinds(NodeKindRegistry $registry): void
    {
        $this->shortcodeKind = $registry->register(
            'shortcode',
            'Shortcode',
            ShortcodeNode::class,
            'Shortcode'
        );
    }

    public function canHandle(TreeContext $ctx): bool
    {
        $token = $ctx->currentToken();
        if ($token === null) {
            return false;
        }

        return $token['type'] === $this->shortcodeOpenType
            || $token['type'] === $this->shortcodeCloseType;
    }

    public function handle(TreeContext $ctx): int
    {
        $ctx->currentToken();
        $nodeIdx = $ctx->addNode($this->shortcodeKind, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }

    public function getShortcodeOpenType(): int
    {
        return $this->shortcodeOpenType;
    }

    public function getShortcodeCloseType(): int
    {
        return $this->shortcodeCloseType;
    }

    public function getShortcodeKind(): int
    {
        return $this->shortcodeKind;
    }
}

function parseWithShortcodes(string $template): Document
{
    test()->freshRegistries();

    $ext = new ShortcodeExtension;
    $registry = app(ExtensionRegistry::class);
    $registry->register($ext);

    $directives = Directives::withDefaults();
    $lexer = new Lexer($template, $directives);
    $registry->configureLexer($lexer);
    $lexerResult = $lexer->tokenize();

    $builder = new TreeBuilder($lexerResult->tokens, $template, $directives);
    $registry->configureTreeBuilder($builder);
    $treeResult = $builder->build();

    return Document::fromParts(
        $treeResult['nodes'],
        $lexerResult->tokens,
        $template,
        $directives,
        new ComponentManager
    );
}

function tokenizeShortcodes(string $template): array
{
    test()->freshRegistries();

    $ext = new ShortcodeExtension;
    $registry = app(ExtensionRegistry::class);
    $registry->register($ext);

    $lexer = new Lexer($template);
    $registry->configureLexer($lexer);

    return $lexer->tokenize()->tokens;
}

function getShortcodeTokens(array $tokens): array
{
    return array_filter($tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);
}

beforeEach(function (): void {
    $this->freshRegistries();
});

describe('Shortcode Extension', function (): void {
    it('tokenizes self-closing shortcodes', function (): void {
        $tokens = tokenizeShortcodes('[gallery]');
        $shortcodes = getShortcodeTokens($tokens);

        expect(count($shortcodes))->toBe(1);

        $token = array_values($shortcodes)[0];
        expect(substr('[gallery]', $token['start'], $token['end'] - $token['start']))->toBe('[gallery]');
    });

    it('tokenizes shortcodes with attributes', function (): void {
        $template = '[gallery id="123" size="medium"]';
        $tokens = tokenizeShortcodes($template);
        $shortcodes = getShortcodeTokens($tokens);

        expect(count($shortcodes))->toBe(1);
    });

    it('handles mixed attribute formats', function (): void {
        $template = "[gallery id=\"123\" size='medium' columns=3]";
        $tokens = tokenizeShortcodes($template);
        $shortcodes = getShortcodeTokens($tokens);

        expect(count($shortcodes))->toBe(1);
    });

    it('tokenizes paired shortcodes', function (): void {
        $template = '[caption]My caption text[/caption]';
        $tokens = tokenizeShortcodes($template);
        $shortcodes = getShortcodeTokens($tokens);

        expect(count($shortcodes))->toBe(2);
    });

    it('ignores blade directives', function (): void {
        $template = '[@if(true)]';
        $tokens = tokenizeShortcodes($template);
        $shortcodes = getShortcodeTokens($tokens);

        expect(count($shortcodes))->toBe(0);
    });

    it('tokenizes multiple shortcodes', function (): void {
        $template = '[row][column][/column][/row]';
        $tokens = tokenizeShortcodes($template);
        $shortcodes = getShortcodeTokens($tokens);

        expect(count($shortcodes))->toBe(4);
    });

    it('creates ShortcodeNode for self-closing shortcode', function (): void {
        $doc = parseWithShortcodes('[gallery]');
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ShortcodeNode::class);

        /** @var ShortcodeNode $shortcode */
        $shortcode = $children[0];
        expect($shortcode->name())->toBe('gallery')
            ->and($shortcode->isOpening())->toBeTrue()
            ->and($shortcode->isClosing())->toBeFalse()
            ->and($shortcode->attributes())->toBe([]);
    });

    it('creates ShortcodeNode with attributes', function (): void {
        $doc = parseWithShortcodes('[gallery id="123" size="medium"]');
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ShortcodeNode::class);

        /** @var ShortcodeNode $shortcode */
        $shortcode = $children[0];
        expect($shortcode->name())->toBe('gallery')
            ->and($shortcode->attributesRaw())->toBe('id="123" size="medium"')
            ->and($shortcode->attributes())->toBe([
                'id' => '123',
                'size' => 'medium',
            ]);
    });

    it('creates paired ShortcodeNodes with content between', function (): void {
        $doc = parseWithShortcodes('[caption]My caption text[/caption]');
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3);

        // Opening shortcode
        expect($children[0])->toBeInstanceOf(ShortcodeNode::class);
        /** @var ShortcodeNode $opening */
        $opening = $children[0];
        expect($opening->name())->toBe('caption')
            ->and($opening->isOpening())->toBeTrue()
            ->and($children[1])->toBeInstanceOf(TextNode::class)
            ->and($children[1]->getDocumentContent())->toBe('My caption text')
            ->and($children[2])->toBeInstanceOf(ShortcodeNode::class);

        /** @var ShortcodeNode $closing */
        $closing = $children[2];
        expect($closing->name())->toBe('caption')
            ->and($closing->isClosing())->toBeTrue();
    });

    it('handles nested shortcodes', function (): void {
        $template = '[row][column]Content[/column][/row]';
        $doc = parseWithShortcodes($template);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(5)
            ->and($children[0])->toBeInstanceOf(ShortcodeNode::class)
            ->and($children[0]->name())->toBe('row')
            ->and($children[1])->toBeInstanceOf(ShortcodeNode::class)
            ->and($children[1]->name())->toBe('column')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[3])->toBeInstanceOf(ShortcodeNode::class)
            ->and($children[3]->isClosing())->toBeTrue()
            ->and($children[4])->toBeInstanceOf(ShortcodeNode::class)
            ->and($children[4]->isClosing())->toBeTrue();
    });

    it('handles hyphens and underscores in names', function (): void {
        $doc = parseWithShortcodes('[my-custom_shortcode]');
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ShortcodeNode::class);

        /** @var ShortcodeNode $shortcode */
        $shortcode = $children[0];
        expect($shortcode->name())->toBe('my-custom_shortcode');
    });

    it('mixes with text content', function (): void {
        $doc = parseWithShortcodes('Hello [gallery] World');
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('Hello ')
            ->and($children[1])->toBeInstanceOf(ShortcodeNode::class)
            ->and($children[1]->name())->toBe('gallery')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[2]->getDocumentContent())->toBe(' World');
    });

    it('renders back to original source', function (): void {
        $template = '[gallery id="123"] Some content [/gallery]';
        $doc = parseWithShortcodes($template);

        expect($doc->render())->toBe($template);
    });

    it('finds shortcode nodes via findAll', function (): void {
        $doc = parseWithShortcodes('[gallery] text [button]Click[/button]');

        $shortcodes = $doc->findAll(fn ($n) => $n instanceof ShortcodeNode);

        expect($shortcodes)->toHaveCount(3)
            ->and($shortcodes[0]->name())->toBe('gallery')
            ->and($shortcodes[1]->name())->toBe('button')
            ->and($shortcodes[2]->name())->toBe('button')
            ->and($shortcodes[2]->isClosing())->toBeTrue();
    });

    it('finds first shortcode via find', function (): void {
        $doc = parseWithShortcodes('text [gallery] more [button]');

        $first = $doc->find(fn ($n) => $n instanceof ShortcodeNode);

        expect($first)->toBeInstanceOf(ShortcodeNode::class)
            ->and($first->name())->toBe('gallery');
    });

    it('stores metadata on shortcode nodes', function (): void {
        $doc = parseWithShortcodes('[gallery id="123"]');

        $shortcode = $doc->find(fn ($n) => $n instanceof ShortcodeNode);
        $shortcode->setData('processed', true);
        $shortcode->setData('original_attrs', $shortcode->attributes());

        expect($shortcode->hasData('processed'))->toBeTrue()
            ->and($shortcode->getData('processed'))->toBeTrue()
            ->and($shortcode->getData('original_attrs'))->toBe(['id' => '123']);
    });

    it('tags shortcode nodes', function (): void {
        $doc = parseWithShortcodes('[gallery] [button] [gallery]');

        $shortcodes = $doc->findAll(fn ($n) => $n instanceof ShortcodeNode);

        foreach ($shortcodes as $sc) {
            if ($sc->name() === 'gallery') {
                $sc->tag('gallery-shortcode');
            }
        }

        $tagged = $doc->findNodesByTag('gallery-shortcode');
        expect($tagged)->toHaveCount(2)
            ->and($tagged[0]->name())->toBe('gallery')
            ->and($tagged[1]->name())->toBe('gallery');
    });

    it('accesses node index for precise targeting', function (): void {
        $doc = parseWithShortcodes('[a][b][c]');
        $children = $doc->getChildren();

        expect($children[0]->index())->toBe(1) // Index 0 is root
            ->and($children[1]->index())->toBe(2)
            ->and($children[2]->index())->toBe(3);

        $nodeB = $doc->getNode(2);
        expect($nodeB)->toBeInstanceOf(ShortcodeNode::class)
            ->and($nodeB->name())->toBe('b');
    });
});
