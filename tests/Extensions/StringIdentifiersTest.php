<?php

declare(strict_types=1);

use Forte\Extensions\AbstractExtension;
use Forte\Extensions\ExtensionRegistry;
use Forte\Lexer\Extension\LexerContext;
use Forte\Lexer\Tokens\TokenType;
use Forte\Lexer\Tokens\TokenTypeRegistry;
use Forte\Parser\Extension\TreeContext;
use Forte\Parser\NodeKind;
use Forte\Parser\NodeKindRegistry;
use Forte\Parser\ParserOptions;

class StringTestExtension extends AbstractExtension
{
    public function id(): string
    {
        return 'string-test';
    }

    public function triggerCharacters(): string
    {
        return '#';
    }

    protected function registerTypes(TokenTypeRegistry $registry): void
    {
        $this->registerType($registry, 'HashtagToken');
    }

    protected function registerKinds(NodeKindRegistry $registry): void
    {
        $this->registerKind($registry, 'HashtagNode');
    }

    protected function doTokenize(LexerContext $ctx): bool
    {
        if ($ctx->current() !== '#') {
            return false;
        }

        $start = $ctx->position();
        $ctx->advance();

        while ($ctx->current() !== null && ctype_alnum($ctx->current())) {
            $ctx->advance();
        }

        $typeId = app(TokenTypeRegistry::class)->getId($this->id(), 'HashtagToken');
        $ctx->emit($typeId, $start, $ctx->position());

        return true;
    }

    public function canHandle(TreeContext $ctx): bool
    {
        $token = $ctx->currentToken();

        // Use string-based check
        return $token && $this->isTokenType($token, 'HashtagToken');
    }

    protected function doHandle(TreeContext $ctx): int
    {
        $kindId = app(NodeKindRegistry::class)->getId($this->id(), 'HashtagNode');
        $nodeIdx = $ctx->addNode($kindId, $ctx->position(), 1);
        $ctx->addChild($nodeIdx);

        return 1;
    }

    public function getTypeKey(string $name): string
    {
        return $this->typeKey($name);
    }

    public function getKindKey(string $name): string
    {
        return $this->kindKey($name);
    }

    public function checkTokenType(array $token, string $name): bool
    {
        return $this->isTokenType($token, $name);
    }

    public function checkNodeKind(array $node, string $name): bool
    {
        return $this->isNodeKind($node, $name);
    }
}

describe('TokenTypeRegistry string methods', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('registers and retrieves type by key', function (): void {
        $registry = app(TokenTypeRegistry::class);
        $id = $registry->register('test', 'MyToken');

        expect($registry->getIdByKey('test:MyToken'))->toBe($id)
            ->and($registry->getIdByKey('nonexistent:Token'))->toBeNull();
    });

    it('matches token by string key', function (): void {
        $registry = app(TokenTypeRegistry::class);
        $id = $registry->register('test', 'MyToken');

        $token = ['type' => $id, 'start' => 0, 'end' => 5];

        expect($registry->matches($token, 'test:MyToken'))->toBeTrue()
            ->and($registry->matches($token, 'test:OtherToken'))->toBeFalse()
            ->and($registry->matches($token, 'nonexistent:Token'))->toBeFalse();
    });
});

describe('NodeKindRegistry string methods', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('registers and retrieves kind by namespace and name', function (): void {
        $registry = app(NodeKindRegistry::class);
        $id = $registry->register('test', 'MyNode');

        expect($registry->getId('test', 'MyNode'))->toBe($id)
            ->and($registry->getId('nonexistent', 'Node'))->toBeNull();
    });

    it('retrieves kind by full key', function (): void {
        $registry = app(NodeKindRegistry::class);
        $id = $registry->register('test', 'MyNode');

        expect($registry->getIdByKey('test::MyNode'))->toBe($id)
            ->and($registry->getIdByKey('nonexistent::Node'))->toBeNull();
    });

    it('matches node by string key', function (): void {
        $registry = app(NodeKindRegistry::class);
        $id = $registry->register('test', 'MyNode');

        $node = ['kind' => $id, 'parent' => 0];

        expect($registry->matches($node, 'test::MyNode'))->toBeTrue()
            ->and($registry->matches($node, 'test::OtherNode'))->toBeFalse()
            ->and($registry->matches($node, 'nonexistent::Node'))->toBeFalse();
    });
});

describe('TokenType::is() static helper', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('matches by integer type', function (): void {
        $token = ['type' => TokenType::Directive, 'start' => 0, 'end' => 5];

        expect(TokenType::is($token, TokenType::Directive))->toBeTrue()
            ->and(TokenType::is($token, TokenType::Text))->toBeFalse();
    });

    it('matches by core alias string', function (): void {
        $token = ['type' => TokenType::Directive, 'start' => 0, 'end' => 5];

        expect(TokenType::is($token, 'core:Directive'))->toBeTrue()
            ->and(TokenType::is($token, 'core:Text'))->toBeFalse();
    });

    it('matches extension type by string', function (): void {
        $registry = app(TokenTypeRegistry::class);
        $id = $registry->register('myext', 'CustomToken');

        $token = ['type' => $id, 'start' => 0, 'end' => 5];

        expect(TokenType::is($token, 'myext:CustomToken'))->toBeTrue()
            ->and(TokenType::is($token, 'myext:OtherToken'))->toBeFalse();
    });
});

describe('NodeKind::is() static helper', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('matches by integer kind', function (): void {
        $node = ['kind' => NodeKind::Element, 'parent' => 0];

        expect(NodeKind::is($node, NodeKind::Element))->toBeTrue()
            ->and(NodeKind::is($node, NodeKind::Text))->toBeFalse();
    });

    it('matches by core alias string', function (): void {
        $node = ['kind' => NodeKind::Element, 'parent' => 0];

        expect(NodeKind::is($node, 'core::Element'))->toBeTrue()
            ->and(NodeKind::is($node, 'core::Text'))->toBeFalse();
    });

    it('matches extension kind by string', function (): void {
        $registry = app(NodeKindRegistry::class);
        $id = $registry->register('myext', 'CustomNode');

        $node = ['kind' => $id, 'parent' => 0];

        expect(NodeKind::is($node, 'myext::CustomNode'))->toBeTrue()
            ->and(NodeKind::is($node, 'myext::OtherNode'))->toBeFalse();
    });
});

describe('AbstractExtension string helpers', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('generates correct type key', function (): void {
        $ext = new StringTestExtension;

        expect($ext->getTypeKey('HashtagToken'))->toBe('string-test:HashtagToken')
            ->and($ext->getTypeKey('OtherType'))->toBe('string-test:OtherType');
    });

    it('generates correct kind key', function (): void {
        $ext = new StringTestExtension;

        expect($ext->getKindKey('HashtagNode'))->toBe('string-test::HashtagNode')
            ->and($ext->getKindKey('OtherKind'))->toBe('string-test::OtherKind');
    });

    it('checks token type by name', function (): void {
        $registry = app(ExtensionRegistry::class);
        $ext = new StringTestExtension;
        $registry->register($ext);
        $registry->all(); // Materialize

        $typeId = app(TokenTypeRegistry::class)->getId('string-test', 'HashtagToken');
        $token = ['type' => $typeId, 'start' => 0, 'end' => 5];

        expect($ext->checkTokenType($token, 'HashtagToken'))->toBeTrue()
            ->and($ext->checkTokenType($token, 'OtherType'))->toBeFalse();
    });

    it('checks node kind by name', function (): void {
        $registry = app(ExtensionRegistry::class);
        $ext = new StringTestExtension;
        $registry->register($ext);
        $registry->all(); // Materialize

        $kindId = app(NodeKindRegistry::class)->getId('string-test', 'HashtagNode');
        $node = ['kind' => $kindId, 'parent' => 0];

        expect($ext->checkNodeKind($node, 'HashtagNode'))->toBeTrue()
            ->and($ext->checkNodeKind($node, 'OtherKind'))->toBeFalse();
    });
});

describe('Node::isKind() method', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('checks built-in kind by integer', function (): void {
        $doc = $this->parse('<div>Hello</div>');
        $element = $doc->elements->first();

        expect($element->isKind(NodeKind::Element))->toBeTrue()
            ->and($element->isKind(NodeKind::Text))->toBeFalse();
    });

    it('checks built-in kind by string alias', function (): void {
        $doc = $this->parse('<div>Hello</div>');
        $element = $doc->elements->first();

        expect($element->isKind('core::Element'))->toBeTrue()
            ->and($element->isKind('core::Text'))->toBeFalse();
    });

    it('checks extension kind by string via NodeKind::is()', function (): void {
        $registry = app(NodeKindRegistry::class);
        $kindId = $registry->register('myext', 'CustomNode');

        $node = ['kind' => $kindId, 'parent' => 0];

        expect(NodeKind::is($node, 'myext::CustomNode'))->toBeTrue()
            ->and(NodeKind::is($node, 'myext::OtherNode'))->toBeFalse()
            ->and(NodeKind::is($node, $kindId))->toBeTrue();
    });
});

describe('Integration: parsing with string identifiers', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('extension uses string-based canHandle', function (): void {
        $options = ParserOptions::withExtensions(StringTestExtension::class);
        $doc = $this->parse('Hello #world!', $options);

        expect($doc->render())->toBe('Hello #world!');
    });

    it('parses multiple hashtags', function (): void {
        $options = ParserOptions::withExtensions(StringTestExtension::class);
        $doc = $this->parse('#hello #world #test', $options);

        expect($doc->render())->toBe('#hello #world #test');
    });
});
