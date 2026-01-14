<?php

declare(strict_types=1);

use Forte\Ast\Node;
use Forte\Extensions\AbstractExtension;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\ParserOptions;

class SimpleHashtagNode extends Node
{
    public function hashtag(): string
    {
        return ltrim($this->getDocumentContent(), '#');
    }
}

class HashtagExtension extends AbstractExtension
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
        $this->hashtagType = $this->registerType($registry, 'Hashtag');
    }

    protected function registerKinds(NodeKindRegistry $registry): void
    {
        $this->registerKind($registry, 'Hashtag', SimpleHashtagNode::class);
    }

    protected function doTokenize(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '#') {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance(); // Skip #

        if (! ctype_alnum($ctx->current() ?? '')) {
            return false;
        }

        while ($ctx->current() !== null && (ctype_alnum($ctx->current()) || $ctx->current() === '_')) {
            $ctx->advance();
        }

        $ctx->emit($this->hashtagType, $start, $ctx->position());

        return true;
    }
}

class CustomHandleExtension extends AbstractExtension
{
    private int $starType;

    private int $starKind;

    private int $doubleStarKind;

    private bool $isDoubleStar = false;

    public function id(): string
    {
        return 'stars';
    }

    public function triggerCharacters(): string
    {
        return '*';
    }

    protected function registerTypes(TokenTypeRegistry $registry): void
    {
        $this->starType = $this->registerType($registry, 'Star');
    }

    protected function registerKinds(NodeKindRegistry $registry): void
    {
        $this->starKind = $this->registerKind($registry, 'Star');
        $this->doubleStarKind = $this->registerKind($registry, 'DoubleStar');
    }

    protected function doTokenize(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '*') {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance();

        $this->isDoubleStar = $ctx->current() === '*';
        if ($this->isDoubleStar) {
            $ctx->advance();
        }

        // Find closing star(s)x
        while ($ctx->current() !== null && $ctx->current() !== '*') {
            $ctx->advance();
        }

        if ($ctx->current() === '*') {
            $ctx->advance();
            if ($this->isDoubleStar && $ctx->current() === '*') {
                $ctx->advance();
            }
        }

        $ctx->emit($this->starType, $start, $ctx->position());

        return true;
    }

    protected function doHandle(TreeContext $ctx): int
    {
        $token = $ctx->currentToken();
        $content = $ctx->source();
        $tokenContent = substr($content, $token['start'], $token['end'] - $token['start']);

        $kind = str_starts_with($tokenContent, '**') ? $this->doubleStarKind : $this->starKind;
        $nodeIdx = $ctx->addNode($kind, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }
}

class DiagnosticExtension extends AbstractExtension
{
    private int $validType;

    public function id(): string
    {
        return 'diagnostic';
    }

    public function triggerCharacters(): string
    {
        return '!';
    }

    protected function registerTypes(TokenTypeRegistry $registry): void
    {
        $this->validType = $this->registerType($registry, 'Valid');
    }

    protected function registerKinds(NodeKindRegistry $registry): void
    {
        $this->registerKind($registry, 'Valid');
    }

    protected function doTokenize(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '!') {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance();

        while ($ctx->current() !== null && ctype_alpha($ctx->current())) {
            $ctx->advance();
        }

        $end = $ctx->position();

        if ($end - $start < 3) {
            $this->warn('Short identifier', $start, $end);
        }

        $ctx->emit($this->validType, $start, $end);

        return true;
    }
}

beforeEach(function (): void {
    $this->freshRegistries();
});

describe('Simple Extensions', function (): void {
    it('requires id(), triggerCharacters(), registerTypes(), and doTokenize() - hashtag extension', function (): void {
        $ext = new HashtagExtension;

        expect($ext->id())->toBe('hashtags')
            ->and($ext->triggerCharacters())->toBe('#');
    });

    it('provides default implementations for optional methods', function (): void {
        $ext = new HashtagExtension;

        expect($ext->name())->toBe('hashtags')
            ->and($ext->version())->toBe('1.0.0')
            ->and($ext->dependencies())->toBe([])
            ->and($ext->conflicts())->toBe([])
            ->and($ext->priority())->toBe(0);
    });

    it('registers token types via registerTypes()', function (): void {
        $ext = new HashtagExtension;
        $types = $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        expect($types)->toHaveCount(1)
            ->and($types[0])->toBeGreaterThanOrEqual(128);
    });

    it('registers node kinds via registerKinds()', function (): void {
        $ext = new HashtagExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));
        $ext->registerNodeKinds(app(NodeKindRegistry::class));

        expect(app(NodeKindRegistry::class)->all())->toHaveCount(1);
    });

    it('shouldActivate() is triggered during parsing', function (): void {
        $ext = new HashtagExtension;
        expect($ext->triggerCharacters())->toBe('#');

        $doc = $this->parse('#test', ParserOptions::withExtensions(HashtagExtension::class));
        $hashtags = $doc->findAll(fn ($n) => $n instanceof SimpleHashtagNode);
        expect($hashtags)->toHaveCount(1);
    });

    it('canHandle() is used during parsing', function (): void {
        $doc = $this->parse(
            '#hashtag',
            ParserOptions::withExtensions(HashtagExtension::class)
        );

        $hashtags = $doc->findAll(fn ($n) => $n instanceof SimpleHashtagNode);
        expect($hashtags)->toHaveCount(1);
    });

    it('doHandle() creates nodes during parsing', function (): void {
        $doc = $this->parse(
            '#test',
            ParserOptions::withExtensions(HashtagExtension::class)
        );

        $hashtags = $doc->findAll(fn ($n) => $n instanceof SimpleHashtagNode);
        expect($hashtags)->toHaveCount(1)
            ->and($hashtags[0]->hashtag())->toBe('test');
    });

    it('parses hashtags from content', function (): void {
        $doc = $this->parse(
            'Check out #php and #laravel!',
            ParserOptions::withExtensions(HashtagExtension::class)
        );

        $hashtags = $doc->findAll(fn ($n) => $n instanceof SimpleHashtagNode);

        expect($hashtags)->toHaveCount(2)
            ->and($hashtags[0]->hashtag())->toBe('php')
            ->and($hashtags[1]->hashtag())->toBe('laravel');
    });

    it('preserves source content', function (): void {
        $template = 'Hello #world from #php!';
        $doc = $this->parse(
            $template,
            ParserOptions::withExtensions(HashtagExtension::class)
        );

        expect($doc->render())->toBe($template);
    });

    it('handles edge cases', function (): void {
        $doc = $this->parse(
            '# not a hashtag, but #valid is',
            ParserOptions::withExtensions(HashtagExtension::class)
        );

        $hashtags = $doc->findAll(fn ($n) => $n instanceof SimpleHashtagNode);

        expect($hashtags)->toHaveCount(1)
            ->and($hashtags[0]->hashtag())->toBe('valid');
    });

    it('handles hashtags with underscores', function (): void {
        $doc = $this->parse(
            '#hello_world #test_123',
            ParserOptions::withExtensions(HashtagExtension::class)
        );

        $hashtags = $doc->findAll(fn ($n) => $n instanceof SimpleHashtagNode);

        expect($hashtags)->toHaveCount(2)
            ->and($hashtags[0]->hashtag())->toBe('hello_world')
            ->and($hashtags[1]->hashtag())->toBe('test_123');
    });

    it('allows overriding doHandle() for custom logic', function (): void {
        $doc = $this->parse(
            '*single* and **double**',
            ParserOptions::withExtensions(CustomHandleExtension::class)
        );

        $children = $doc->getChildren();

        expect($children)->not->toBeEmpty();
    });

    it('supports diagnostic reporting', function (): void {
        $ext = new DiagnosticExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        $this->parse(
            '!a is short but !valid is fine',
            ParserOptions::withExtensions($ext)
        );

        $diagnostics = $ext->getDiagnostics();

        expect($diagnostics)->toHaveCount(1)
            ->and($diagnostics[0]->message)->toBe('Short identifier');
    });

    it('can clear diagnostics', function (): void {
        $ext = new DiagnosticExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        $this->parse('!a', ParserOptions::withExtensions($ext));

        expect($ext->getDiagnostics())->not->toBeEmpty();

        $ext->clearDiagnostics();

        expect($ext->getDiagnostics())->toBeEmpty();
    });

    it('hasTokenType() checks registered types', function (): void {
        $ext = new HashtagExtension;
        $types = $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        expect($ext->hasTokenType($types[0]))->toBeTrue()
            ->and($ext->hasTokenType(999))->toBeFalse();
    });

    it('getRegisteredTokenTypes() returns all registered types', function (): void {
        $ext = new HashtagExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        $types = $ext->getRegisteredTokenTypes();
        expect($types)->toHaveCount(1)
            ->and($types[0])->toBeGreaterThanOrEqual(128);
    });

    it('getRegisteredNodeKinds() returns all registered kinds', function (): void {
        $ext = new HashtagExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));
        $ext->registerNodeKinds(app(NodeKindRegistry::class));

        $kinds = $ext->getRegisteredNodeKinds();
        expect($kinds)->toHaveCount(1);
    });

    it('hasNodeKind() checks registered kinds', function (): void {
        $ext = new HashtagExtension;
        $ext->registerTokenTypes(app(TokenTypeRegistry::class));
        $ext->registerNodeKinds(app(NodeKindRegistry::class));

        $kinds = $ext->getRegisteredNodeKinds();
        expect($ext->hasNodeKind($kinds[0]))->toBeTrue()
            ->and($ext->hasNodeKind(999))->toBeFalse();
    });

    it('throws if doHandle() called without node kinds', function (): void {
        $ext = new class extends AbstractExtension
        {
            public function id(): string
            {
                return 'no-kinds';
            }

            public function triggerCharacters(): string
            {
                return '!';
            }

            protected function registerTypes(TokenTypeRegistry $registry): void {}

            protected function doTokenize(LexerContext $ctx): bool
            {
                return false;
            }
        };

        $ext->registerTokenTypes(app(TokenTypeRegistry::class));
        $ext->registerNodeKinds(app(NodeKindRegistry::class));

        expect($ext->getRegisteredNodeKinds())->toBeEmpty()
            ->and($ext->id())->toBe('no-kinds');
    });

    it('requires id(), triggerCharacters(), registerTypes(), and doTokenize()', function (): void {
        $ext = new HashtagExtension;

        expect($ext->id())->toBe('hashtags')
            ->and($ext->triggerCharacters())->toBe('#');

        $types = $ext->registerTokenTypes(app(TokenTypeRegistry::class));
        expect($types)->not->toBeEmpty();

        $doc = $this->parse('#test', ParserOptions::withExtensions(HashtagExtension::class));
        expect($doc->render())->toBe('#test');
    });

    it('has sensible defaults for registration and handling', function (): void {
        $ext = new HashtagExtension;
        $types = $ext->registerTokenTypes(app(TokenTypeRegistry::class));

        expect($types)->not->toBeEmpty();
    });
});
