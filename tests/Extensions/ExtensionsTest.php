<?php

declare(strict_types=1);

use Forte\Ast\Node;
use Forte\Diagnostics\DiagnosticSeverity;
use Forte\Extensions\AbstractExtension;
use Forte\Extensions\AbstractLexerExtension;
use Forte\Extensions\AbstractTreeExtension;
use Forte\Extensions\ExtensionRegistry;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\Extension\TreeExtension;
use Forte\Parser\NodeKind;
use Forte\Parser\NodeKindRegistry;

class HashtagNode extends Node
{
    public function name(): string
    {
        $content = $this->getDocumentContent();

        return ltrim($content, '#');
    }
}

class HashtagLexerExtension extends AbstractLexerExtension
{
    private int $hashtagType;

    public function id(): string
    {
        return 'hashtags';
    }

    public function triggerCharacters(): string
    {
        return '#';
    }

    protected function registerTypes(TokenTypeRegistry $registry): void
    {
        $this->hashtagType = $this->registerType($registry, 'Hashtag', 'Hashtag');
    }

    protected function doTokenize(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '#') {
            return false;
        }

        $next = $ctx->peek(1);
        if ($next === null || ! ctype_alnum($next)) {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance(); // Skip #

        while ($ctx->current() !== null && (ctype_alnum($ctx->current()) || $ctx->current() === '_')) {
            $ctx->advance();
        }

        $ctx->emit($this->hashtagType, $start, $ctx->position());

        return true;
    }

    public function getHashtagType(): int
    {
        return $this->hashtagType;
    }
}

class HashtagTreeExtension extends AbstractTreeExtension
{
    private int $hashtagKind;

    public function __construct(private readonly int $hashtagType) {}

    public function id(): string
    {
        return 'hashtags-tree';
    }

    protected function registerKinds(NodeKindRegistry $registry): void
    {
        $this->hashtagKind = $this->registerKind($registry, 'Hashtag', HashtagNode::class);
    }

    public function canHandle(TreeContext $ctx): bool
    {
        $token = $ctx->currentToken();

        return $token !== null && $token['type'] === $this->hashtagType;
    }

    protected function doHandle(TreeContext $ctx): int
    {
        $nodeIdx = $ctx->addNode($this->hashtagKind, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }

    public function getHashtagKind(): int
    {
        return $this->hashtagKind;
    }
}

class MentionExtension extends AbstractExtension
{
    private int $mentionType;

    private int $mentionKind;

    public function id(): string
    {
        return 'mentions';
    }

    public function triggerCharacters(): string
    {
        return '@';
    }

    public function version(): string
    {
        return '2.0.0';
    }

    protected function registerTypes(TokenTypeRegistry $registry): void
    {
        $this->mentionType = $this->registerType($registry, 'Mention');
    }

    protected function registerKinds(NodeKindRegistry $registry): void
    {
        $this->mentionKind = $this->registerKind($registry, 'Mention');
    }

    protected function doTokenize(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '@') {
            return false;
        }

        $next = $ctx->peek(1);
        if ($next === null || ! ctype_alpha($next)) {
            if ($next !== null && ctype_digit($next)) {
                $this->warn('Mentions cannot start with a digit', $ctx->position(), $ctx->position() + 2);
            }

            return false;
        }

        $start = $ctx->position();
        $ctx->advance(); // Skip @

        while ($ctx->current() !== null && (ctype_alnum($ctx->current()) || $ctx->current() === '_')) {
            $ctx->advance();
        }

        $ctx->emit($this->mentionType, $start, $ctx->position());

        return true;
    }

    public function canHandle(TreeContext $ctx): bool
    {
        $token = $ctx->currentToken();

        return $token !== null && $token['type'] === $this->mentionType;
    }

    protected function doHandle(TreeContext $ctx): int
    {
        $nodeIdx = $ctx->addNode($this->mentionKind, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }

    public function getMentionType(): int
    {
        return $this->mentionType;
    }
}

beforeEach(function (): void {
    $this->freshRegistries();
});

describe('Extensions Test', function (): void {
    it('provides sensible defaults for default implementations', function (): void {
        $ext = new HashtagLexerExtension;

        expect($ext->name())->toBe('hashtags')
            ->and($ext->version())->toBe('1.0.0')
            ->and($ext->dependencies())->toBe([])
            ->and($ext->conflicts())->toBe([]);
    });

    it('provides sensible defaults for lexer extensions', function (): void {
        $ext = new HashtagLexerExtension;

        expect($ext->priority())->toBe(0)
            ->and($ext->triggerCharacters())->toBe('#');
    });

    it('auto-registers token types once', function (): void {
        $ext = new HashtagLexerExtension;
        $registry = app(TokenTypeRegistry::class);

        $types1 = $ext->registerTokenTypes($registry);
        $types2 = $ext->registerTokenTypes($registry);

        expect($types1)->toBe($types2)
            ->and($types1)->toHaveCount(1);
    });

    it('tokenizes with doTokenize method', function (): void {
        $ext = new HashtagLexerExtension;
        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexer = new Lexer('Hello #world');
        $registry->configureLexer($lexer);

        $tokens = $lexer->tokenize()->tokens;

        $hashtagTokens = array_filter($tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);
        expect($hashtagTokens)->toHaveCount(1);
    });

    it('auto-activates on trigger characters', function (): void {
        $ext = new HashtagLexerExtension;

        expect($ext->triggerCharacters())->toBe('#');

        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexer = new Lexer('#tag1 text #tag2');
        $registry->configureLexer($lexer);

        $tokens = $lexer->tokenize()->tokens;

        $hashtagTokens = array_filter($tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);
        expect($hashtagTokens)->toHaveCount(2);
    });

    it('auto-registers node kinds once', function (): void {
        $ext = new HashtagTreeExtension(128);
        $registry = app(NodeKindRegistry::class);

        $ext->registerNodeKinds($registry);
        $kind1 = $ext->getHashtagKind();

        $ext->registerNodeKinds($registry);
        $kind2 = $ext->getHashtagKind();

        expect($kind1)->toBe($kind2)
            ->and($kind1)
            ->toBeGreaterThanOrEqual(NodeKind::EXTENSION_BASE);
    });

    it('implements both LexerExtension and TreeExtension', function (): void {
        $ext = new MentionExtension;

        expect($ext)->toBeInstanceOf(LexerExtension::class)
            ->and($ext)->toBeInstanceOf(TreeExtension::class);
    });

    it('can override version', function (): void {
        $ext = new MentionExtension;

        expect($ext->version())
            ->toBe('2.0.0');
    });

    it('tracks registered token types', function (): void {
        $ext = new MentionExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        expect($ext->getMentionType())
            ->toBeGreaterThanOrEqual(TokenType::EXTENSION_BASE);
    });

    it('reports diagnostics during tokenization', function (): void {
        $ext = new MentionExtension;
        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexer = new Lexer('Hello @123invalid');
        $registry->configureLexer($lexer);
        $lexer->tokenize();

        $diagnostics = $ext->getDiagnostics();
        expect($diagnostics)->toHaveCount(1)
            ->and($diagnostics[0]->severity)->toBe(DiagnosticSeverity::Warning)
            ->and($diagnostics[0]->message)->toContain('digit');
    });

    it('can clear diagnostics', function (): void {
        $ext = new MentionExtension;
        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexer = new Lexer('@123');
        $registry->configureLexer($lexer);
        $lexer->tokenize();

        expect($ext->getDiagnostics())->toHaveCount(1);

        $ext->clearDiagnostics();

        expect($ext->getDiagnostics())->toHaveCount(0);
    });

    it('configures options via configure method', function (): void {
        $ext = new MentionExtension;
        $ext->configure([
            'strict' => true,
            'maxLength' => 20,
        ]);

        expect($ext->option('strict'))->toBeTrue()
            ->and($ext->option('maxLength'))->toBe(20)
            ->and($ext->option('missing', 'default'))->toBe('default');
    });

    it('checks if option exists', function (): void {
        $ext = new MentionExtension;
        $ext->configure(['key' => 'value']);

        expect($ext->hasOption('key'))->toBeTrue()
            ->and($ext->hasOption('missing'))->toBeFalse();
    });

    it('merges options on multiple configure calls', function (): void {
        $ext = new MentionExtension;
        $ext->configure(['a' => 1]);
        $ext->configure(['b' => 2]);

        expect($ext->option('a'))->toBe(1)
            ->and($ext->option('b'))->toBe(2);
    });

    it('returns fluent interface from configure', function (): void {
        $ext = new MentionExtension;

        $result = $ext->configure(['key' => 'value']);

        expect($result)->toBe($ext);
    });

    it('registerType helper stores token type ID', function (): void {
        $ext = new HashtagLexerExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        expect($ext->getHashtagType())
            ->toBeGreaterThanOrEqual(TokenType::EXTENSION_BASE);
    });

    it('registerKind helper stores node kind ID', function (): void {
        $ext = new HashtagTreeExtension(128);
        $ext->registerNodeKinds(app(NodeKindRegistry::class));

        expect($ext->getHashtagKind())
            ->toBeGreaterThanOrEqual(NodeKind::EXTENSION_BASE);
    });
});
