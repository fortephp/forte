<?php

declare(strict_types=1);

namespace Forte\Lexer;

use Forte\Extensions\AttributeExtension;
use Forte\Lexer\Concerns\AttributeScanner;
use Forte\Lexer\Concerns\BladeCommentScanner;
use Forte\Lexer\Concerns\ByteHelpers;
use Forte\Lexer\Concerns\CommentScanner;
use Forte\Lexer\Concerns\DataScanner;
use Forte\Lexer\Concerns\DirectiveScanner;
use Forte\Lexer\Concerns\DoctypeScanner;
use Forte\Lexer\Concerns\EchoScanner;
use Forte\Lexer\Concerns\HtmlScanner;
use Forte\Lexer\Concerns\JsxScanner;
use Forte\Lexer\Concerns\PhpScanner;
use Forte\Lexer\Concerns\RawtextScanner;
use Forte\Lexer\Extension\AttributeLexerContext;
use Forte\Lexer\Extension\ExtensionStack;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Extension\LexerExtension;
use Forte\Lexer\Tokens\TokenType;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Directives\Directives;
use Forte\Support\ScanPrimitives;

class Lexer
{
    use AttributeScanner,
        BladeCommentScanner,
        ByteHelpers,
        CommentScanner,
        ConstructDetector,
        DataScanner,
        DirectiveScanner,
        DoctypeScanner,
        EchoScanner,
        HtmlScanner,
        JsxScanner,
        PhpScanner,
        RawtextScanner;

    private string $source;

    private int $pos = 0;

    private int $len;

    private State $state;

    private State $returnState;

    /** @var array<int, array{type: int, start: int, end: int}> */
    private array $tokens = [];

    /** @var array<int, LexerError> */
    private array $errors = [];

    private Directives $directives;

    private bool $verbatim = false;

    private ?State $verbatimReturnState = null;

    private bool $phpBlock = false;

    private bool $phpTag = false;

    private bool $continuedTagName = false;

    /** @phpstan-ignore property.onlyWritten */
    private bool $rawtext = false;

    private string $rawtextTagName = '';

    private string $currentTagName = '';

    private bool $isClosingTag = false;

    private bool $inXmlDeclaration = false;

    private ?ExtensionStack $extensions = null;

    private ?LexerContext $extensionContext = null;

    private string $extensionTriggers = '';

    /**
     * @var array<string, AttributeExtension>
     */
    private array $attributeExtensions = [];

    /**
     * @var string[]
     */
    private array $attributePrefixes = [];

    private ?AttributeLexerContext $attrExtContext = null;

    public function __construct(string $source, ?Directives $directives = null)
    {
        $this->source = $source;
        $this->len = strlen($source);
        $this->state = State::Data;
        $this->returnState = State::Data;
        $this->directives = $directives ?? Directives::withDefaults();
    }

    public function directives(): Directives
    {
        return $this->directives;
    }

    /**
     * Register a lexer extension.
     *
     *
     * @param  LexerExtension  $extension  The extension to register
     * @param  TokenTypeRegistry|null  $registry  Optional registry
     * @return $this
     */
    public function registerExtension(LexerExtension $extension, ?TokenTypeRegistry $registry = null): self
    {
        if ($this->extensions === null) {
            $this->extensions = new ExtensionStack;
        }

        $this->extensions->add($extension);
        $extension->registerTokenTypes($registry ?? app(TokenTypeRegistry::class));

        $this->extensionTriggers = $this->extensions->getTriggerCharacters();

        return $this;
    }

    public function getExtensionTriggers(): string
    {
        return $this->extensionTriggers;
    }

    private function getExtensionContext(): LexerContext
    {
        return $this->extensionContext ??= new LexerContext($this);
    }

    public function tokenize(): LexerResult
    {
        while ($this->pos < $this->len) {
            if ($this->extensions !== null && $this->state === State::Data) {
                if ($this->extensions->tryTokenize($this->getExtensionContext())) {
                    continue;
                }
            }

            switch ($this->state) {
                case State::Data:
                    $this->scanData();
                    break;

                case State::RawText:
                    $this->scanRawtext();
                    break;

                case State::BladeComment:
                    $this->scanBladeCommentContent();
                    break;

                case State::Comment:
                    $this->scanComment();
                    break;

                case State::TagName:
                    $this->scanTagName();
                    break;

                case State::BeforeAttrName:
                    $this->scanBeforeAttrName();
                    break;

                case State::AttrName:
                    $this->scanAttrName();
                    break;

                case State::AfterAttrName:
                    $this->scanAfterAttrName();
                    break;

                case State::BeforeAttrValue:
                    $this->scanBeforeAttrValue();
                    break;

                case State::AttrValueQuoted:
                    $this->scanAttrValueQuoted();
                    break;

                case State::AttrValueUnquoted:
                    $this->scanAttrValueUnquoted();
                    break;

                default:
                    // Unhandled state
                    break 2;
            }

        }

        if ($this->state === State::TagName
            || $this->state === State::BeforeAttrName
            || $this->state === State::AttrName
            || $this->state === State::AfterAttrName
            || $this->state === State::BeforeAttrValue
            || $this->state === State::AttrValueQuoted
            || $this->state === State::AttrValueUnquoted
        ) {
            $this->emitToken(TokenType::SyntheticClose, $this->pos, $this->pos);
        }

        return new LexerResult($this->tokens, $this->errors);
    }

    /**
     * Get the current lexer position.
     */
    public function position(): int
    {
        return $this->pos;
    }

    /**
     * Get source text.
     */
    public function source(): string
    {
        return $this->source;
    }

    public function logError(LexerError $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Get source document length.
     */
    public function length(): int
    {
        return $this->len;
    }

    /**
     * Update the lexer's position.
     */
    public function setPosition(int $pos): void
    {
        $this->pos = $pos;
    }

    /**
     * Get current state.
     */
    public function getState(): State
    {
        return $this->state;
    }

    /**
     * Set the current lexer state.
     */
    public function setState(State $state): void
    {
        $this->state = $state;
    }

    /**
     * Check if in a @verbatim @endverbatim block.
     */
    public function isVerbatim(): bool
    {
        return $this->verbatim;
    }

    /**
     * Set verbatim mode.
     */
    public function setVerbatim(bool $verbatim): void
    {
        $this->verbatim = $verbatim;
    }

    /**
     * Check if in a @php @endphp block.
     */
    public function isPhpBlock(): bool
    {
        return $this->phpBlock;
    }

    /**
     * Set PHP block mode.
     */
    public function setPhpBlock(bool $phpBlock): void
    {
        $this->phpBlock = $phpBlock;
    }

    /**
     * Check if in PHP tag mode.
     */
    public function isPhpTag(): bool
    {
        return $this->phpTag;
    }

    /**
     * Set PHP tag mode.
     */
    public function setPhpTag(bool $phpTag): void
    {
        $this->phpTag = $phpTag;
    }

    /**
     * Advance position a certain number of bytes.
     */
    public function advance(int $bytes): void
    {
        $this->pos = min($this->pos + $bytes, $this->len);
    }

    /**
     * Check if source matches needle at position.
     */
    public function matchesAt(string $needle, int $pos, bool $caseInsensitive = false): bool
    {
        $len = strlen($needle);
        if ($pos + $len > $this->len) {
            return false;
        }

        $substr = substr($this->source, $pos, $len);

        return $caseInsensitive
            ? strcasecmp($substr, $needle) === 0
            : $substr === $needle;
    }

    /**
     * @param  int  $type  TokenType constant or extension type ID
     */
    public function emitToken(int $type, int $start, int $end): void
    {
        $this->tokens[] = ['type' => $type, 'start' => $start, 'end' => $end];
    }

    /**
     * Get return state.
     */
    public function getReturnState(): State
    {
        return $this->returnState;
    }

    /**
     * Set the return state.
     */
    public function setReturnState(State $state): void
    {
        $this->returnState = $state;
    }

    /**
     * Skip a PHP string.
     */
    public function skipPhpString(): void
    {
        if ($this->pos >= $this->len) {
            return;
        }

        $quote = $this->source[$this->pos];
        $this->pos++; // Skip opening quote

        if ($quote === "'" || $quote === '"') {
            ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, $quote);
        } elseif ($quote === '`') {
            ScanPrimitives::skipBacktickString($this->source, $this->pos, $this->len);
        }
    }

    /**
     * Skip a PHP comment.
     */
    public function skipPhpComment(): void
    {
        if ($this->pos + 1 >= $this->len) {
            return;
        }

        $next = $this->source[$this->pos + 1];

        if ($next === '/') {
            $this->pos += 2;
            $this->skipLineCommentWithWarnings();
        } elseif ($next === '*') {
            $this->pos += 2;
            ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
        }
    }

    /**
     * Skip line comment while detecting PHP close tag.
     *
     * Logs a warning if ?> is found inside a line comment.
     */
    private function skipLineCommentWithWarnings(): void
    {
        $detected = ScanPrimitives::skipLineCommentDetecting(
            $this->source,
            $this->pos,
            $this->len,
            ['?>']
        );

        foreach ($detected as $match) {
            $this->logError(LexerError::phpCloseTagInComment($this->state, $match['offset']));
        }
    }

    /**
     * Skip balanced parentheses.
     */
    public function skipBalancedParens(): int
    {
        if ($this->pos >= $this->len || $this->source[$this->pos] !== '(') {
            return $this->pos;
        }

        $this->pos++; // Skip opening (
        $depth = 1;

        while ($depth > 0 && $this->pos < $this->len) {
            $byte = $this->source[$this->pos];

            if ($byte === '(') {
                $depth++;
                $this->pos++;
            } elseif ($byte === ')') {
                $depth--;
                $this->pos++;
            } elseif ($byte === "'" || $byte === '"') {
                $this->pos++;
                ScanPrimitives::skipQuotedString($this->source, $this->pos, $this->len, $byte);
            } elseif ($byte === '/' && $this->pos + 1 < $this->len) {
                $next = $this->source[$this->pos + 1];
                if ($next === '/') {
                    $this->pos += 2;
                    $this->skipLineCommentWithWarnings();
                } elseif ($next === '*') {
                    $this->pos += 2;
                    ScanPrimitives::skipBlockComment($this->source, $this->pos, $this->len);
                } else {
                    $this->pos++;
                }
            } else {
                $this->pos++;
            }
        }

        return $this->pos;
    }

    /**
     * Register an attribute extension.
     *
     * @param  AttributeExtension  $extension  The extension to register
     * @param  TokenTypeRegistry|null  $registry  Optional token type registry
     * @return $this
     */
    public function registerAttributeExtension(AttributeExtension $extension, ?TokenTypeRegistry $registry = null): self
    {
        $prefix = $extension->attributePrefix();
        if ($prefix === '') {
            return $this; // Skip no-prefix extensions
        }

        $extension->registerAttributeTokenType($registry ?? app(TokenTypeRegistry::class));

        $this->attributeExtensions[$prefix] = $extension;

        // Re-sort prefixes by length (longest first) for correct matching
        $this->attributePrefixes = array_keys($this->attributeExtensions);
        usort($this->attributePrefixes, fn ($a, $b) => strlen($b) - strlen($a));

        return $this;
    }

    /**
     * Get all registered attribute extensions.
     *
     * @return array<string, AttributeExtension>
     */
    public function getAttributeExtensions(): array
    {
        return $this->attributeExtensions;
    }

    /**
     * Check if there are any attribute extensions registered.
     */
    public function hasAttributeExtensions(): bool
    {
        return ! empty($this->attributeExtensions);
    }

    /**
     * Try to match an attribute extension at the current position.
     */
    public function tryAttributeExtension(): bool
    {
        if (empty($this->attributeExtensions)) {
            return false;
        }

        $start = $this->pos;

        $this->attrExtContext ??= new AttributeLexerContext($this, 0);

        foreach ($this->attributePrefixes as $prefix) {
            if (! $this->matchesAt($prefix, $this->pos)) {
                continue;
            }

            $extension = $this->attributeExtensions[$prefix];
            $prefixLen = strlen($prefix);

            if (! $extension->shouldActivate($this->attrExtContext->reset($this->pos + $prefixLen))) {
                continue;
            }

            $consumed = $extension->tokenizeAttributeName($this->attrExtContext->reset($this->pos));

            if ($consumed > 0) {
                $tokenType = $extension->getTokenType();
                $this->emitToken($tokenType, $start, $start + $consumed);
                $this->pos = $start + $consumed;

                return true;
            }
        }

        return false;
    }
}
