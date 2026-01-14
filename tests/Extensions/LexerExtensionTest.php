<?php

declare(strict_types=1);

use Forte\Extensions\ExtensionRegistry;
use Forte\Extensions\ForteExtension;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;
use Forte\Lexer\Tokens\TokenTypeRegistry;

class MarkerExtension implements ForteExtension, LexerExtension
{
    private int $markerStart;

    private int $markerEnd;

    private int $markerContent;

    public function id(): string
    {
        return 'marker';
    }

    public function name(): string
    {
        return 'Marker Extension';
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
        $this->markerStart = $registry->register('marker', 'MarkerStart', 'MarkerStart');
        $this->markerEnd = $registry->register('marker', 'MarkerEnd', 'MarkerEnd');
        $this->markerContent = $registry->register('marker', 'MarkerContent', 'MarkerContent');

        return [$this->markerStart, $this->markerEnd, $this->markerContent];
    }

    public function shouldActivate(LexerContext $ctx): bool
    {
        return $ctx->current() === '[';
    }

    public function tokenize(LexerContext $ctx): bool
    {
        if (! $ctx->matches('[marker]')) {
            return false;
        }

        $start = $ctx->position();

        // Emit [
        $ctx->emit($this->markerStart, $start, $start + 1);
        $ctx->advance(1);

        // Emit marker
        $ctx->emit($this->markerContent, $start + 1, $start + 7);
        $ctx->advance(6);

        // Emit ]
        $ctx->emit($this->markerEnd, $start + 7, $start + 8);
        $ctx->advance(1);

        return true;
    }

    public function getMarkerStartType(): int
    {
        return $this->markerStart;
    }

    public function getMarkerEndType(): int
    {
        return $this->markerEnd;
    }

    public function getMarkerContentType(): int
    {
        return $this->markerContent;
    }
}

beforeEach(function (): void {
    $this->freshRegistries();
});

describe('Lexer Extension System', function (): void {
    it('registers custom token types', function (): void {
        $ext = new MarkerExtension;
        $registry = app(TokenTypeRegistry::class);
        $types = $ext->registerTokenTypes($registry);

        expect($types)->toHaveCount(3)
            ->and($types[0])->toBeGreaterThanOrEqual(TokenType::EXTENSION_BASE)
            ->and($types[1])->toBeGreaterThanOrEqual(TokenType::EXTENSION_BASE)
            ->and($types[2])->toBeGreaterThanOrEqual(TokenType::EXTENSION_BASE);
    });

    it('tokenizes custom patterns via direct registration', function (): void {
        $ext = new MarkerExtension;
        $lexer = new Lexer('Hello [marker] World');
        $lexer->registerExtension($ext);

        $result = $lexer->tokenize();

        // Find the marker tokens
        $markerTokens = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE
        );

        expect(count($markerTokens))->toBe(3);

        $source = $lexer->source();
        $markerTokenValues = array_values($markerTokens);
        expect(substr($source, $markerTokenValues[0]['start'], $markerTokenValues[0]['end'] - $markerTokenValues[0]['start']))
            ->toBe('[')
            ->and(substr($source, $markerTokenValues[1]['start'], $markerTokenValues[1]['end'] - $markerTokenValues[1]['start']))
            ->toBe('marker')
            ->and(substr($source, $markerTokenValues[2]['start'], $markerTokenValues[2]['end'] - $markerTokenValues[2]['start']))
            ->toBe(']');
    });

    it('tokenizes custom patterns via ExtensionRegistry', function (): void {
        $ext = new MarkerExtension;
        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexer = new Lexer('Hello [marker] World');
        $registry->configureLexer($lexer);

        $result = $lexer->tokenize();

        // Find the marker tokens
        $markerTokens = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE
        );

        expect(count($markerTokens))->toBe(3);
    });

    it('falls through when extension does not match', function (): void {
        $ext = new MarkerExtension;
        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexer = new Lexer('[other] text');
        $registry->configureLexer($lexer);

        $result = $lexer->tokenize();

        // No marker tokens since [other] doesn't match [marker]
        $markerTokens = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE
        );

        expect(count($markerTokens))->toBe(0);
    });

    it('provides extension token type labels', function (): void {
        $ext = new MarkerExtension;
        $registry = app(TokenTypeRegistry::class);
        $ext->registerTokenTypes($registry);

        $label = TokenType::label($ext->getMarkerStartType());
        expect($label)->toBe('MarkerStart');
    });
});

describe('Extension Registry', function (): void {
    it('registers and retrieves extensions', function (): void {
        $ext = new MarkerExtension;
        $registry = app(ExtensionRegistry::class);

        $registry->register($ext);

        expect($registry->has('marker'))->toBeTrue()
            ->and($registry->get('marker'))->toBe($ext);
    });

    it('throws on duplicate registration', function (): void {
        $ext = new MarkerExtension;
        $registry = app(ExtensionRegistry::class);

        $registry->register($ext);

        expect(fn () => $registry->register($ext))
            ->toThrow(RuntimeException::class, "Extension 'marker' is already registered");
    });

    it('filters by capability', function (): void {
        $ext = new MarkerExtension;
        $registry = app(ExtensionRegistry::class);
        $registry->register($ext);

        $lexerExtensions = $registry->extensionOfType(LexerExtension::class);

        expect($lexerExtensions)->toHaveCount(1)
            ->and($lexerExtensions[0])->toBe($ext);
    });
});

function createLexerExtension(
    string $id,
    string $triggers,
    callable $shouldActivate,
    callable $tokenize,
    int $priority = 100
): LexerExtension {
    return new class($id, $triggers, $shouldActivate, $tokenize, $priority) implements LexerExtension
    {
        private int $tokenType;

        public function __construct(
            private readonly string $id,
            private readonly string $triggers,
            private $shouldActivateFn,
            private $tokenizeFn,
            private readonly int $priorityValue
        ) {}

        public function name(): string
        {
            return $this->id;
        }

        public function priority(): int
        {
            return $this->priorityValue;
        }

        public function triggerCharacters(): string
        {
            return $this->triggers;
        }

        public function registerTokenTypes(TokenTypeRegistry $registry): array
        {
            $this->tokenType = $registry->register($this->id, 'Custom', 'Custom');

            return [$this->tokenType];
        }

        public function shouldActivate(LexerContext $ctx): bool
        {
            return ($this->shouldActivateFn)($ctx);
        }

        public function tokenize(LexerContext $ctx): bool
        {
            return ($this->tokenizeFn)($ctx, $this->tokenType);
        }

        public function getTokenType(): int
        {
            return $this->tokenType;
        }
    };
}

describe('Multiple Extensions', function (): void {
    it('supports multiple extensions with different triggers', function (): void {
        // Dollar extension: $$content$$
        $dollarExt = createLexerExtension(
            'dollar',
            '$',
            fn (LexerContext $ctx) => $ctx->matches('$$'),
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('$$')) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(2); // Skip $$

                $ctx->advanceUntil('$');

                if ($ctx->matches('$$')) {
                    $ctx->advance(2);
                }

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        // Percent extension: %%content%%
        $percentExt = createLexerExtension(
            'percent',
            '%',
            fn (LexerContext $ctx) => $ctx->matches('%%'),
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('%%')) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(2);

                $ctx->advanceUntil('%');

                if ($ctx->matches('%%')) {
                    $ctx->advance(2);
                }

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('$$one$$ text %%two%%');
        $lexer->registerExtension($dollarExt);
        $lexer->registerExtension($percentExt);

        $result = $lexer->tokenize();

        $extTokens = array_filter(
            $result->tokens,
            fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE
        );

        expect(count($extTokens))->toBe(2);
    });

    it('works alongside Blade syntax', function (): void {
        $tildeExt = createLexerExtension(
            'tilde',
            '~',
            fn (LexerContext $ctx) => $ctx->matches('~~'),
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('~~')) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(2);

                $ctx->advanceUntil('~');

                if ($ctx->matches('~~')) {
                    $ctx->advance(2);
                }

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('~~custom~~ {{ $var }} @if(true)');
        $lexer->registerExtension($tildeExt);

        $result = $lexer->tokenize();

        $extTokens = array_filter($result->tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);
        $echoTokens = array_filter($result->tokens, fn ($t) => $t['type'] === TokenType::EchoStart);
        $directiveTokens = array_filter($result->tokens, fn ($t) => $t['type'] === TokenType::Directive);

        expect($extTokens)->toHaveCount(1)
            ->and($echoTokens)->toHaveCount(1)
            ->and($directiveTokens)->toHaveCount(1);
    });
});

describe('Extension returning false', function (): void {
    it('falls through to built-in handling when extension returns false', function (): void {
        $ext = createLexerExtension(
            'passthrough',
            '@',
            fn (LexerContext $ctx) => $ctx->current() === '@',
            fn (LexerContext $ctx, int $type) => false
        );

        $lexer = new Lexer('@if(true)');
        $lexer->registerExtension($ext);

        $result = $lexer->tokenize();

        $directiveTokens = array_filter($result->tokens, fn ($t) => $t['type'] === TokenType::Directive);
        expect(count($directiveTokens))->toBe(1);

        $extTokens = array_filter($result->tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);
        expect(count($extTokens))->toBe(0);
    });
});

describe('LexerContext API', function (): void {
    it('provides position and peek methods', function (): void {
        $positions = [];

        $ext = createLexerExtension(
            'position-test',
            '|',
            fn (LexerContext $ctx) => $ctx->matches('||'),
            function (LexerContext $ctx, int $type) use (&$positions) {
                if (! $ctx->matches('||')) {
                    return false;
                }

                $positions[] = $ctx->position();

                $start = $ctx->position();
                $ctx->advance(2);
                $ctx->advanceUntil('|');

                if ($ctx->matches('||')) {
                    $ctx->advance(2);
                }

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('||test||');
        $lexer->registerExtension($ext);
        $lexer->tokenize();

        expect($positions)->toHaveCount(1)
            ->and($positions[0])->toBe(0);
    });

    it('supports peek to look ahead', function (): void {
        // Only match ##digits##
        $ext = createLexerExtension(
            'number-only',
            '#',
            function (LexerContext $ctx) {
                if (! $ctx->matches('##')) {
                    return false;
                }

                $next = $ctx->peek(2);

                return $next !== null && ctype_digit($next);
            },
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('##')) {
                    return false;
                }

                $next = $ctx->peek(2);
                if ($next === null || ! ctype_digit($next)) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(2);
                $ctx->advanceUntil('#');

                if ($ctx->matches('##')) {
                    $ctx->advance(2);
                }

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('##123## and ##abc##');
        $lexer->registerExtension($ext);

        $result = $lexer->tokenize();

        $extTokens = array_filter($result->tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);

        // Only ##123## should match, ##abc## should be text
        expect(count($extTokens))->toBe(1);

        $source = $lexer->source();
        $token = array_values($extTokens)[0];
        expect(substr($source, $token['start'], $token['end'] - $token['start']))->toBe('##123##');
    });

    it('supports matches with case insensitive option', function (): void {
        $ext = createLexerExtension(
            'case-test',
            'Tt',  // Both uppercase and lowercase to catch all variations
            fn (LexerContext $ctx) => $ctx->matches('TEST', true),
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('TEST', true)) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(4);
                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('Test and test and TEST');
        $lexer->registerExtension($ext);

        $result = $lexer->tokenize();

        $extTokens = array_filter($result->tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);

        expect(count($extTokens))->toBe(3);
    });

    it('supports substr extraction', function (): void {
        $extracted = null;

        $ext = createLexerExtension(
            'substr-test',
            '[',
            fn (LexerContext $ctx) => $ctx->matches('[extract:'),
            function (LexerContext $ctx, int $type) use (&$extracted) {
                if (! $ctx->matches('[extract:')) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(9); // Skip [extract:

                $contentStart = $ctx->position();
                $ctx->advanceUntil(']');
                $contentEnd = $ctx->position();

                $extracted = $ctx->substr($contentStart, $contentEnd - $contentStart);

                $ctx->advance(1); // Skip ]

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('[extract:hello world]');
        $lexer->registerExtension($ext);
        $lexer->tokenize();

        expect($extracted)->toBe('hello world');
    });
});

describe('Extension Priority', function (): void {
    it('checks higher priority extensions first', function (): void {
        $calls = [];

        $lowPriority = createLexerExtension(
            'low',
            '*',
            function (LexerContext $ctx) use (&$calls) {
                $calls[] = 'low-check';

                return $ctx->matches('**');
            },
            function (LexerContext $ctx, int $type) use (&$calls) {
                $calls[] = 'low-tokenize';
                $start = $ctx->position();
                $ctx->advance(2);
                $ctx->advanceUntil('*');
                if ($ctx->matches('**')) {
                    $ctx->advance(2);
                }
                $ctx->emit($type, $start, $ctx->position());

                return true;
            },
            50
        );

        $highPriority = createLexerExtension(
            'high',
            '*',
            function (LexerContext $ctx) use (&$calls) {
                $calls[] = 'high-check';

                return $ctx->matches('**');
            },
            function (LexerContext $ctx, int $type) use (&$calls) {
                $calls[] = 'high-tokenize';
                $start = $ctx->position();
                $ctx->advance(2);
                $ctx->advanceUntil('*');
                if ($ctx->matches('**')) {
                    $ctx->advance(2);
                }
                $ctx->emit($type, $start, $ctx->position());

                return true;
            },
            100
        );

        $lexer = new Lexer('**test**');
        $lexer->registerExtension($lowPriority);
        $lexer->registerExtension($highPriority);

        $lexer->tokenize();

        expect($calls[0])->toBe('high-check')
            ->and($calls[1])->toBe('high-tokenize')
            ->and(in_array('low-tokenize', $calls))->toBeFalse();
    });
});

describe('Extension with complex patterns', function (): void {
    it('handles bracket content extraction', function (): void {
        $ext = createLexerExtension(
            'brackets',
            '[',
            fn (LexerContext $ctx) => $ctx->matches('[['),
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('[[')) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(2); // Skip [[

                $ctx->advanceUntil(']');

                if ($ctx->matches(']]')) {
                    $ctx->advance(2);
                }

                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('[[content]] more text');
        $lexer->registerExtension($ext);

        $result = $lexer->tokenize();

        $extTokens = array_filter($result->tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);

        expect(count($extTokens))->toBe(1);

        $source = $lexer->source();
        $token = array_values($extTokens)[0];
        expect(substr($source, $token['start'], $token['end'] - $token['start']))->toBe('[[content]]');
    });

    it('works at tokenization boundaries with Blade echo', function (): void {
        $ext = createLexerExtension(
            'boundary',
            '%',
            fn (LexerContext $ctx) => $ctx->matches('%%'),
            function (LexerContext $ctx, int $type) {
                if (! $ctx->matches('%%')) {
                    return false;
                }

                $start = $ctx->position();
                $ctx->advance(2);
                $ctx->advanceUntil('%');
                if ($ctx->matches('%%')) {
                    $ctx->advance(2);
                }
                $ctx->emit($type, $start, $ctx->position());

                return true;
            }
        );

        $lexer = new Lexer('%%start%% {{ $var }}%%after%%');
        $lexer->registerExtension($ext);

        $result = $lexer->tokenize();

        $extTokens = array_filter($result->tokens, fn ($t) => $t['type'] >= TokenType::EXTENSION_BASE);
        $echoTokens = array_filter($result->tokens, fn ($t) => $t['type'] === TokenType::EchoStart);

        expect(count($extTokens))->toBe(2)
            ->and(count($echoTokens))->toBe(1);
    });
});
