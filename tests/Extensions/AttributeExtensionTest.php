<?php

declare(strict_types=1);

use Forte\Extensions\AbstractAttributeExtension;
use Forte\Lexer\Extension\AttributeLexerContext;
use Forte\Parser\ParserOptions;

class SpreadAttributeExtension extends AbstractAttributeExtension
{
    public function id(): string
    {
        return 'spread';
    }

    public function attributePrefix(): string
    {
        return '...';
    }

    public function shouldActivate(AttributeLexerContext $ctx): bool
    {
        return $ctx->peek() === '$';
    }

    public function acceptsValue(): bool
    {
        return false;
    }

    public function tokenizeAttributeName(AttributeLexerContext $ctx): int
    {
        $consumed = 3; // "..."

        if ($ctx->peek($consumed) === '$') {
            $consumed++;
            while (true) {
                $char = $ctx->peek($consumed);
                if ($char === null) {
                    break;
                }
                if (ctype_alnum($char) || $char === '_') {
                    $consumed++;
                } else {
                    break;
                }
            }
        }

        return $consumed;
    }
}

class VueRefExtension extends AbstractAttributeExtension
{
    public function id(): string
    {
        return 'vue-ref';
    }

    public function attributePrefix(): string
    {
        return '#';
    }
}

describe('AttributeExtension basics', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('creates extension with correct prefix', function (): void {
        $ext = new SpreadAttributeExtension;

        expect($ext->attributePrefix())->toBe('...')
            ->and($ext->id())->toBe('spread')
            ->and($ext->acceptsValue())->toBeFalse();
    });

    it('simple extension uses defaults', function (): void {
        $ext = new VueRefExtension;

        expect($ext->attributePrefix())->toBe('#')
            ->and($ext->acceptsValue())->toBeTrue();
    });
});

describe('Lexer attribute extension integration', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('tokenizes spread attribute', function (): void {
        $options = ParserOptions::withExtensions(SpreadAttributeExtension::class);
        $doc = $this->parse('<div ...$params>', $options);

        expect($doc->render())->toBe('<div ...$params>');
    });

    it('tokenizes vue ref attribute', function (): void {
        $options = ParserOptions::withExtensions(VueRefExtension::class);
        $doc = $this->parse('<input #myInput>', $options);

        expect($doc->render())->toBe('<input #myInput>');
    });

    it('tokenizes spread attribute with other attributes', function (): void {
        $options = ParserOptions::withExtensions(SpreadAttributeExtension::class);
        $doc = $this->parse('<div class="foo" ...$params id="bar">', $options);

        expect($doc->render())->toBe('<div class="foo" ...$params id="bar">');
    });

    it('tokenizes multiple spread attributes', function (): void {
        $options = ParserOptions::withExtensions(SpreadAttributeExtension::class);
        $doc = $this->parse('<div ...$params ...$more>', $options);

        expect($doc->render())->toBe('<div ...$params ...$more>');
    });
});

describe('Attribute AST access', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('can access spread attribute type', function (): void {
        $options = ParserOptions::withExtensions(SpreadAttributeExtension::class);
        $doc = $this->parse('<div class="foo" ...$params id="bar">', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(3)
            ->and($attrs[0]->nameText())->toBe('class')
            ->and($attrs[0]->valueText())->toBe('foo')
            ->and($attrs[0]->isExtensionAttribute())->toBeFalse()
            ->and($attrs[0]->extensionId())->toBeNull()
            ->and($attrs[1]->type())->toBe('Attribute')
            ->and($attrs[1]->isExtensionAttribute())->toBeTrue()
            ->and($attrs[1]->extensionId())->toBe('spread')
            ->and($attrs[1]->rawName())->toBe('...$params')
            ->and($attrs[2]->nameText())->toBe('id')
            ->and($attrs[2]->valueText())->toBe('bar')
            ->and($attrs[2]->isExtensionAttribute())->toBeFalse();
    });

    it('spread attribute has no value', function (): void {
        $options = ParserOptions::withExtensions(SpreadAttributeExtension::class);
        $doc = $this->parse('<div ...$params>', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(1);

        $spreadAttr = $attrs[0];
        expect($spreadAttr->isBoolean())->toBeTrue()
            ->and($spreadAttr->isExtensionAttribute())->toBeTrue()
            ->and($spreadAttr->extensionId())->toBe('spread')
            ->and($spreadAttr->rawName())->toBe('...$params');
    });

    it('can access multiple spread attributes', function (): void {
        $options = ParserOptions::withExtensions(SpreadAttributeExtension::class);
        $doc = $this->parse('<div ...$params ...$more>', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(2)
            ->and($attrs[0]->rawName())->toBe('...$params')
            ->and($attrs[0]->extensionId())->toBe('spread')
            ->and($attrs[1]->rawName())->toBe('...$more')
            ->and($attrs[1]->extensionId())->toBe('spread');
    });
});

describe('Vue ref with value', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('parses ref with value', function (): void {
        $options = ParserOptions::withExtensions(VueRefExtension::class);
        $doc = $this->parse('<input #myInput="myRef">', $options);

        expect($doc->render())->toBe('<input #myInput="myRef">');
    });

    it('can access vue ref attribute properties', function (): void {
        $options = ParserOptions::withExtensions(VueRefExtension::class);
        $doc = $this->parse('<input #myInput="myRef">', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(1);

        $refAttr = $attrs[0];
        expect($refAttr->isExtensionAttribute())->toBeTrue()
            ->and($refAttr->extensionId())->toBe('vue-ref')
            ->and($refAttr->rawName())->toBe('#myInput')
            ->and($refAttr->valueText())->toBe('myRef')
            ->and($refAttr->isBoolean())->toBeFalse();
    });

    it('vue ref without value is boolean', function (): void {
        $options = ParserOptions::withExtensions(VueRefExtension::class);
        $doc = $this->parse('<input #myInput>', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(1);

        $refAttr = $attrs[0];
        expect($refAttr->rawName())->toBe('#myInput')
            ->and($refAttr->isBoolean())->toBeTrue()
            ->and($refAttr->valueText())->toBeNull();
    });
});

class VueEventExtension extends AbstractAttributeExtension
{
    public function id(): string
    {
        return 'vue-event';
    }

    public function attributePrefix(): string
    {
        return '@';
    }

    public function shouldActivate(AttributeLexerContext $ctx): bool
    {
        $nextChar = $ctx->peek() ?? '';

        return ctype_alpha($nextChar);
    }
}

describe('Conditional extension activation', function (): void {
    beforeEach(function (): void {
        $this->freshRegistries();
    });

    it('extension activates for @event syntax', function (): void {
        $options = ParserOptions::withExtensions(VueEventExtension::class);
        $doc = $this->parse('<button @click="handleClick">', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(1);

        $eventAttr = $attrs[0];
        expect($eventAttr->isExtensionAttribute())->toBeTrue()
            ->and($eventAttr->extensionId())->toBe('vue-event')
            ->and($eventAttr->rawName())->toBe('@click')
            ->and($eventAttr->valueText())->toBe('handleClick');
    });

    it('extension does not activate for email-like patterns', function (): void {
        $options = ParserOptions::withExtensions(VueEventExtension::class);
        $doc = $this->parse('<a href="mailto:foo@bar.com">', $options);

        expect($doc->render())->toBe('<a href="mailto:foo@bar.com">');

        $element = $doc->elements->first();
        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(1);

        $hrefAttr = $attrs[0];
        expect($hrefAttr->isExtensionAttribute())->toBeFalse()
            ->and($hrefAttr->nameText())->toBe('href')
            ->and($hrefAttr->valueText())->toBe('mailto:foo@bar.com');
    });

    it('extension does not activate for @ followed by number', function (): void {
        $options = ParserOptions::withExtensions(VueEventExtension::class);
        $doc = $this->parse('<div data-val="@123">', $options);

        expect($doc->render())->toBe('<div data-val="@123">');

        $element = $doc->elements->first();
        $attrs = $element->getAttributes();

        expect($attrs[0]->isExtensionAttribute())->toBeFalse()
            ->and($attrs[0]->nameText())->toBe('data-val');
    });

    it('can mix activated and non-activated patterns', function (): void {
        $options = ParserOptions::withExtensions(VueEventExtension::class);
        $doc = $this->parse('<button @click="submit" data-email="foo@bar.com">', $options);
        $element = $doc->elements->first();

        $attrs = $element->getAttributes();
        expect(count($attrs))->toBe(2)
            ->and($attrs[0]->isExtensionAttribute())->toBeTrue()
            ->and($attrs[0]->extensionId())->toBe('vue-event')
            ->and($attrs[0]->rawName())->toBe('@click')
            ->and($attrs[1]->isExtensionAttribute())->toBeFalse()
            ->and($attrs[1]->nameText())->toBe('data-email')
            ->and($attrs[1]->valueText())->toBe('foo@bar.com');
    });
});
