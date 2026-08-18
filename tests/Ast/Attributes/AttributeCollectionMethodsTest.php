<?php

declare(strict_types=1);

use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\Attributes;
use Illuminate\Support\Collection;

describe('Attributes Collection Methods', function (): void {
    describe('find()', function (): void {
        it('finds attribute by name', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar"></div>');

            $attr = $el->attributes()->find('class');
            expect($attr)->not()->toBeNull()
                ->and($attr->valueText())->toBe('foo');
        });

        it('returns null for non-existent attribute', function (): void {
            $el = $this->parseElement('<div class="foo"></div>');

            expect($el->attributes()->find('id'))->toBeNull();
        });

        it('is case-insensitive', function (): void {
            $el = $this->parseElement('<div CLASS="foo"></div>');

            expect($el->attributes()->find('class'))->not()->toBeNull()
                ->and($el->attributes()->find('CLASS'))->not()->toBeNull();
        });

        it('preserves historical last-match lookup while exposing the first HTML attribute', function (): void {
            $el = $this->parseElement('<div class="first" CLASS="last"></div>');

            expect($el->attributes()->find('class')?->valueText())->toBe('last')
                ->and($el->attributes()->get('class')?->valueText())->toBe('last')
                ->and($el->attributes()->firstNamed('class')?->valueText())->toBe('first');
        });
    });

    describe('exceptNames()', function (): void {
        it('excludes single attribute by name', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar" data-x="1"></div>');

            $filtered = $el->attributes()->exceptNames('class');
            $names = $filtered->map(fn ($a) => $a->nameText())->all();

            expect($filtered)->toHaveCount(2)
                ->and($names)->not()->toContain('class')
                ->and($names)->toContain('id')
                ->and($names)->toContain('data-x');
        });

        it('excludes multiple attributes by name array', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar" data-x="1"></div>');

            $filtered = $el->attributes()->exceptNames(['class', 'id']);
            expect($filtered)->toHaveCount(1)
                ->and($filtered->first()->nameText())->toBe('data-x');
        });

        it('is case-insensitive', function (): void {
            $el = $this->parseElement('<div CLASS="foo" ID="bar"></div>');

            $filtered = $el->attributes()->exceptNames(['class']);
            expect($filtered)->toHaveCount(1)
                ->and($filtered->first()->nameText())->toBe('ID');
        });

        it('handles non-existent attributes gracefully', function (): void {
            $el = $this->parseElement('<div class="foo"></div>');

            $filtered = $el->attributes()->exceptNames(['nonexistent']);
            expect($filtered)->toHaveCount(1);
        });
    });

    describe('onlyNames()', function (): void {
        it('includes single attribute by name', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar" data-x="1"></div>');

            $filtered = $el->attributes()->onlyNames('class');
            expect($filtered)->toHaveCount(1)
                ->and($filtered->first()->nameText())->toBe('class');
        });

        it('includes multiple attributes by name array', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar" data-x="1"></div>');

            $filtered = $el->attributes()->onlyNames(['class', 'id']);
            expect($filtered)->toHaveCount(2);
        });

        it('is case-insensitive', function (): void {
            $el = $this->parseElement('<div CLASS="foo" ID="bar"></div>');

            $filtered = $el->attributes()->onlyNames(['class', 'id']);
            expect($filtered)->toHaveCount(2);
        });

        it('returns empty collection for non-matching names', function (): void {
            $el = $this->parseElement('<div class="foo"></div>');

            $filtered = $el->attributes()->onlyNames(['nonexistent']);
            expect($filtered)->toHaveCount(0);
        });
    });

    describe('collection boundary', function (): void {
        it('map() returns a base collection', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar"></div>');

            $mapped = $el->attributes()->map(fn (Attribute $attribute): string => $attribute->nameText());

            expect($mapped)->toBeInstanceOf(Collection::class)
                ->and($mapped->all())->toBe(['class', 'id']);
        });

        it('filter() returns Attributes and keeps dense ordering', function (): void {
            $el = $this->parseElement('<div class="foo" id="bar"></div>');

            $filtered = $el->attributes()->filter(
                fn (Attribute $attribute): bool => $attribute->nameText() !== 'class'
            );

            expect($filtered)->toBeInstanceOf(Attributes::class)
                ->and($filtered)->toHaveCount(1)
                ->and($filtered->all()[0]->nameText())->toBe('id');
        });

        it('toCollection() supports generic collection operations', function (): void {
            $el = $this->parseElement('<div id="bar" class="foo"></div>');

            $names = $el->attributes()
                ->toCollection()
                ->sortBy(fn (Attribute $attribute): string => $attribute->nameText())
                ->values()
                ->map(fn (Attribute $attribute): string => $attribute->nameText())
                ->all();

            expect($names)->toBe(['class', 'id']);
        });
    });

    describe('array access mutations', function (): void {
        it('supports append and replace by position', function (): void {
            $attrs = $this->parseElement('<div class="foo" id="bar"></div>')->attributes();
            $replacement = $this->parseElement('<div data-test="x"></div>')->attributes()->first();
            $appended = $this->parseElement('<div title="baz"></div>')->attributes()->first();

            $attrs[0] = $replacement;
            $attrs[] = $appended;

            expect($attrs)->toHaveCount(3)
                ->and($attrs->has('class'))->toBeFalse()
                ->and($attrs->has('data-test'))->toBeTrue()
                ->and($attrs->find('title'))->toBe($appended)
                ->and($attrs[0]?->nameText())->toBe('data-test');
        });

        it('rejects non-attribute values', function (): void {
            $attrs = $this->parseElement('<div class="foo"></div>')->attributes();

            expect(fn () => $attrs[] = 'invalid')->toThrow(InvalidArgumentException::class);
        });
    });
});

describe('Attribute Methods', function (): void {
    describe('semantic value and presence helpers', function (): void {
        it('owns dynamic and static-value semantics on the attribute', function (): void {
            $el = $this->parseElement('<div TITLE="A&amp;B" data-id="{{ $id }}" :class="$classes" disabled></div>');
            $title = $el->attribute('title');
            $dataId = $el->attribute('data-id');
            $class = $el->attribute('class');
            $disabled = $el->attribute('disabled');

            expect($title?->isNamed(['id', 'title']))->toBeTrue()
                ->and($title?->isDynamic())->toBeFalse()
                ->and($title?->staticValue())->toBe('A&B')
                ->and($title?->staticValueLower())->toBe('a&b')
                ->and($title?->tokens())->toBe(['A&B'])
                ->and($title?->isUnconditionallyPresent())->toBeTrue()
                ->and($dataId?->isDynamic())->toBeTrue()
                ->and($dataId?->staticValue())->toBeNull()
                ->and($dataId?->isUnconditionallyPresent())->toBeTrue()
                ->and($class?->isDynamic())->toBeTrue()
                ->and($class?->isUnconditionallyPresent())->toBeFalse()
                ->and($disabled?->staticValue())->toBeNull()
                ->and($disabled?->isUnconditionallyPresent())->toBeTrue();
        });

        it('uses HTML whitespace for token-list helpers', function (): void {
            $el = $this->parseElement("<div role=\"button\tCHECKBOX\" class=\"foo\x0Bbar\"></div>");

            expect($el->attribute('role')?->tokensLower())->toBe(['button', 'checkbox'])
                ->and($el->staticAttributeTokensLower('role'))->toBe(['button', 'checkbox'])
                ->and($el->attributeTokens('class'))->toBe(["foo\x0Bbar"])
                ->and($el->attributeTokens('missing'))->toBe([])
                ->and($el->staticAttributeTokens('missing'))->toBeNull();
        });

        it('keeps element conveniences as null-safe attribute proxies', function (): void {
            $el = $this->parseElement('<div title="A&amp;B" data-id="{{ $id }}"></div>');

            expect($el->staticAttributeValue('title'))->toBe($el->attribute('title')?->staticValue())
                ->and($el->staticAttributeValueLower('title'))->toBe($el->attribute('title')?->staticValueLower())
                ->and($el->attributeIsDynamic('data-id'))->toBe($el->attribute('data-id')?->isDynamic())
                ->and($el->hasUnconditionallyPresentAttribute('data-id'))->toBe($el->attribute('data-id')?->isUnconditionallyPresent())
                ->and($el->attributeIsDynamic('missing'))->toBeFalse()
                ->and($el->staticAttributeValue('missing'))->toBeNull();
        });
    });

    describe('hasComplexValue()', function (): void {
        it('returns true for interpolated values', function (): void {
            $el = $this->parseElement('<div class="foo-{{ $bar }}"></div>');
            $attr = $el->attributes()->find('class');

            expect($attr->hasComplexValue())->toBeTrue()
                ->and($attr->hasComplexValue())->toBe($attr->hasComplexValue());
        });

        it('returns false for static values', function (): void {
            $el = $this->parseElement('<div class="foo"></div>');
            $attr = $el->attributes()->find('class');

            expect($attr->hasComplexValue())->toBeFalse();
        });
    });

    describe('valueOrDefault()', function (): void {
        it('returns value for attributes with values', function (): void {
            $el = $this->parseElement('<div class="foo"></div>');
            $attr = $el->attributes()->find('class');

            expect($attr->valueOrDefault('default'))->toBe('foo');
        });

        it('returns default for boolean attributes', function (): void {
            $el = $this->parseElement('<div disabled></div>');
            $attr = $el->attributes()->find('disabled');

            expect($attr->valueOrDefault('default'))->toBe('default');
        });

        it('returns empty string as default when not specified', function (): void {
            $el = $this->parseElement('<div disabled></div>');
            $attr = $el->attributes()->find('disabled');

            expect($attr->valueOrDefault())->toBe('');
        });

        it('returns empty value when attribute value is empty string', function (): void {
            $el = $this->parseElement('<div class=""></div>');
            $attr = $el->attributes()->find('class');

            expect($attr->valueOrDefault('default'))->toBe('');
        });
    });

    describe('isBoolean() with shorthand attributes', function (): void {
        it('returns true for actual boolean attributes', function (): void {
            $el = $this->parseElement('<input disabled required>');
            $disabled = $el->attributes()->find('disabled');
            $required = $el->attributes()->find('required');

            expect($disabled->isBoolean())->toBeTrue()
                ->and($required->isBoolean())->toBeTrue();
        });

        it('returns false for shorthand variable attributes', function (): void {
            $el = $this->parseElement('<div :$variable></div>');
            $attr = $el->attributes()->find('variable');

            expect($attr->isBoolean())->toBeFalse()
                ->and($attr->isVariableShorthand())->toBeTrue();
        });

        it('distinguishes boolean from shorthand in mixed usage', function (): void {
            $el = $this->parseElement('<div disabled :$bound></div>');
            $disabled = $el->attributes()->find('disabled');
            $bound = $el->attributes()->find('bound');

            expect($disabled->isBoolean())->toBeTrue()
                ->and($disabled->isVariableShorthand())->toBeFalse()
                ->and($bound->isBoolean())->toBeFalse()
                ->and($bound->isVariableShorthand())->toBeTrue();
        });
    });

    describe('standalone blade attribute behavior', function (): void {
        it('does not treat standalone blade constructs as boolean attributes', function (): void {
            $el = $this->parseElement('<div {{ $attrs }} disabled></div>');
            $attrs = $el->attributes()->all();

            expect($attrs)->toHaveCount(2)
                ->and($attrs[0]->isBladeConstruct())->toBeTrue()
                ->and($attrs[0]->isBoolean())->toBeFalse()
                ->and($attrs[1]->nameText())->toBe('disabled')
                ->and($attrs[1]->isBoolean())->toBeTrue();
        });

        it('supports complex() without throwing when standalone blade constructs are present', function (): void {
            $el = $this->parseElement('<div {{ $attrs }} class="foo-{{ $bar }}" id="x"></div>');
            $complex = $el->attributes()->complex();

            expect($complex)->toHaveCount(1)
                ->and($complex->first()->nameText())->toBe('class')
                ->and($complex->first()->hasComplexValue())->toBeTrue();
        });
    });
});
