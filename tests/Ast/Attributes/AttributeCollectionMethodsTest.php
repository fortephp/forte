<?php

declare(strict_types=1);

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
});

describe('Attribute Methods', function (): void {
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
});
