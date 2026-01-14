<?php

declare(strict_types=1);

use Forte\Ast\Elements\Attribute;

describe('Alpine.js Attributes', function (): void {
    it('parses x-data with object value', function (): void {
        $el = $this->parseElement('<div x-data="{ open: false }"></div>');

        $attrs = $el->attributes();
        expect($attrs)->toHaveCount(1);

        $xData = $attrs->get('x-data');
        expect($xData)->toBeInstanceOf(Attribute::class)
            ->and($xData->nameText())->toBe('x-data')
            ->and($xData->type())->toBe('static')
            ->and($xData->valueText())->toBe('{ open: false }')
            ->and($xData->quote())->toBe('"')
            ->and($xData->isBoolean())->toBeFalse();
    });

    it('parses x-show directive', function (): void {
        $el = $this->parseElement('<div x-show="open"></div>');

        $xShow = $el->attributes()->get('x-show');
        expect($xShow->nameText())->toBe('x-show')
            ->and($xShow->type())->toBe('static')
            ->and($xShow->valueText())->toBe('open');
    });

    it('parses x-cloak as boolean attribute', function (): void {
        $el = $this->parseElement('<div x-cloak></div>');

        $xCloak = $el->attributes()->get('x-cloak');
        expect($xCloak->nameText())->toBe('x-cloak')
            ->and($xCloak->type())->toBe('static')
            ->and($xCloak->valueText())->toBeNull()
            ->and($xCloak->isBoolean())->toBeTrue()
            ->and($xCloak->quote())->toBeNull();
    });

    it('parses x-id with array value', function (): void {
        $el = $this->parseElement('<div x-id="[\'dropdown\']"></div>');

        $xId = $el->attributes()->get('x-id');
        expect($xId->nameText())->toBe('x-id')
            ->and($xId->valueText())->toBe('[\'dropdown\']');
    });

    it('parses multiple x-* directives in order', function (): void {
        $el = $this->parseElement('<div x-data="{ open: false }" x-show="open" x-cloak x-id="[id]"></div>');

        $attrs = $el->attributes();
        expect($attrs)->toHaveCount(4);

        $all = $attrs->all();

        expect($all[0]->nameText())->toBe('x-data')
            ->and($all[0]->type())->toBe('static')
            ->and($all[0]->valueText())->toBe('{ open: false }')
            ->and($all[1]->nameText())->toBe('x-show')
            ->and($all[1]->valueText())->toBe('open')
            ->and($all[2]->nameText())->toBe('x-cloak')
            ->and($all[2]->isBoolean())->toBeTrue()
            ->and($all[3]->nameText())->toBe('x-id')
            ->and($all[3]->valueText())->toBe('[id]');
    });

    it('parses x-bind:class as static attribute', function (): void {
        $el = $this->parseElement('<div x-bind:class="{ open: open }"></div>');

        $attr = $el->attributes()->get('x-bind:class');

        expect($attr->nameText())->toBe('x-bind:class')
            ->and($attr->type())->toBe('static')
            ->and($attr->valueText())->toBe('{ open: open }')
            ->and($attr->isBound())->toBeFalse();
    });

    it('parses :class shorthand as bound attribute', function (): void {
        $el = $this->parseElement('<div :class="{ open: open }"></div>');

        $attr = $el->attributes()->get('class');

        expect($attr->nameText())->toBe('class')
            ->and($attr->type())->toBe('bound')
            ->and($attr->valueText())->toBe('{ open: open }')
            ->and($attr->isBound())->toBeTrue()
            ->and($attr->isStatic())->toBeFalse();
    });

    it('parses :disabled shorthand', function (): void {
        $el = $this->parseElement('<button :disabled="isDisabled"></button>');

        $attr = $el->attributes()->get('disabled');
        expect($attr->nameText())->toBe('disabled')
            ->and($attr->type())->toBe('bound')
            ->and($attr->valueText())->toBe('isDisabled');
    });

    it('distinguishes x-bind: from : shorthand', function (): void {
        $el = $this->parseElement('<div x-bind:class="{ a: true }" :class="{ b: true }"></div>');

        $attrs = $el->attributes();
        expect($attrs)->toHaveCount(2);

        $all = $attrs->all();

        expect($all[0]->nameText())->toBe('x-bind:class')
            ->and($all[0]->type())->toBe('static')
            ->and($all[0]->valueText())->toBe('{ a: true }')
            ->and($all[1]->nameText())->toBe('class')
            ->and($all[1]->type())->toBe('bound')
            ->and($all[1]->valueText())->toBe('{ b: true }');
    });

    it('parses multiple bound attributes', function (): void {
        $el = $this->parseElement('<div :class="classes" :id="itemId" :data-index="idx"></div>');

        $attrs = $el->attributes();
        expect($attrs)->toHaveCount(3)
            ->and($attrs->get('class')->isBound())->toBeTrue()
            ->and($attrs->get('class')->valueText())->toBe('classes')
            ->and($attrs->get('id')->isBound())->toBeTrue()
            ->and($attrs->get('id')->valueText())->toBe('itemId')
            ->and($attrs->get('data-index')->isBound())->toBeTrue()
            ->and($attrs->get('data-index')->valueText())->toBe('idx');
    });

    it('parses x-on:click event', function (): void {
        $el = $this->parseElement('<button x-on:click="handleClick()"></button>');

        $attr = $el->attributes()->get('x-on:click');
        expect($attr->nameText())->toBe('x-on:click');
        expect($attr->type())->toBe('static');
        expect($attr->valueText())->toBe('handleClick()');
    });

    it('parses x-on:click with modifiers', function (): void {
        $el = $this->parseElement('<button x-on:click.prevent="submit()"></button>');

        $attr = $el->attributes()->get('x-on:click.prevent');
        expect($attr->nameText())->toBe('x-on:click.prevent')
            ->and($attr->type())->toBe('static')
            ->and($attr->valueText())->toBe('submit()');
    });

    it('parses @click shorthand', function (): void {
        $el = $this->parseElement('<button x-on:submit.prevent="save()"></button>');

        $attr = $el->attributes()->get('x-on:submit.prevent');
        expect($attr->nameText())->toBe('x-on:submit.prevent')
            ->and($attr->valueText())->toBe('save()');
    });

    it('parses x-on with multiple modifiers', function (): void {
        $el = $this->parseElement('<input x-on:keydown.enter.prevent="submit()">');

        $attr = $el->attributes()->get('x-on:keydown.enter.prevent');
        expect($attr->nameText())->toBe('x-on:keydown.enter.prevent')
            ->and($attr->valueText())->toBe('submit()');
    });

    it('parses x-transition as boolean', function (): void {
        $el = $this->parseElement('<div x-transition></div>');

        $attr = $el->attributes()->get('x-transition');
        expect($attr->nameText())->toBe('x-transition')
            ->and($attr->isBoolean())->toBeTrue();
    });

    it('parses x-transition variants', function (): void {
        $el = $this->parseElement('<div x-transition x-transition:enter="duration-300" x-transition:leave="duration-200"></div>');

        $attrs = $el->attributes();
        expect($attrs)->toHaveCount(3);

        $all = $attrs->all();

        expect($all[0]->nameText())->toBe('x-transition')
            ->and($all[0]->isBoolean())->toBeTrue()
            ->and($all[1]->nameText())->toBe('x-transition:enter')
            ->and($all[1]->valueText())->toBe('duration-300')
            ->and($all[2]->nameText())->toBe('x-transition:leave')
            ->and($all[2]->valueText())->toBe('duration-200');
    });

    it('parses x-transition with modifiers', function (): void {
        $el = $this->parseElement('<div x-transition.opacity.duration.300ms></div>');

        $attr = $el->attributes()->get('x-transition.opacity.duration.300ms');
        expect($attr->nameText())->toBe('x-transition.opacity.duration.300ms')
            ->and($attr->isBoolean())->toBeTrue();
    });

    it('parses x-model directive', function (): void {
        $el = $this->parseElement('<input x-model="nameText">');

        $attr = $el->attributes()->get('x-model');
        expect($attr->nameText())->toBe('x-model')
            ->and($attr->valueText())->toBe('nameText');
    });

    it('parses x-model with modifiers', function (): void {
        $el = $this->parseElement('<input x-model.lazy="search">');

        $attr = $el->attributes()->get('x-model.lazy');
        expect($attr->nameText())->toBe('x-model.lazy')
            ->and($attr->valueText())->toBe('search');
    });

    it('parses x-text and x-html', function (): void {
        $el = $this->parseElement('<span x-text="message" x-html="richContent"></span>');

        $attrs = $el->attributes();

        expect($attrs->get('x-text')->valueText())->toBe('message')
            ->and($attrs->get('x-html')->valueText())->toBe('richContent');
    });

    it('parses x-ref directive', function (): void {
        $el = $this->parseElement('<div x-ref="container"></div>');

        $attr = $el->attributes()->get('x-ref');
        expect($attr->nameText())->toBe('x-ref')
            ->and($attr->valueText())->toBe('container');
    });

    it('parses x-init directive', function (): void {
        $el = $this->parseElement('<div x-init="console.log(\'ready\')"></div>');

        $attr = $el->attributes()->get('x-init');
        expect($attr->nameText())->toBe('x-init')
            ->and($attr->valueText())->toBe('console.log(\'ready\')');
    });

    it('parses x-effect directive', function (): void {
        $el = $this->parseElement('<div x-effect="console.log(count)"></div>');

        $attr = $el->attributes()->get('x-effect');
        expect($attr->valueText())->toBe('console.log(count)');
    });

    it('parses x-ignore directive', function (): void {
        $el = $this->parseElement('<div x-ignore></div>');

        $attr = $el->attributes()->get('x-ignore');
        expect($attr->isBoolean())->toBeTrue();
    });

    it('parses Alpine directives alongside regular attributes', function (): void {
        $el = $this->parseElement('<div id="app" class="container" x-data="{ open: false }" x-show="open"></div>');

        $attrs = $el->attributes();
        expect($attrs)->toHaveCount(4)
            ->and($attrs->get('id')->type())->toBe('static')
            ->and($attrs->get('id')->valueText())->toBe('app')
            ->and($attrs->get('class')->type())->toBe('static')
            ->and($attrs->get('class')->valueText())->toBe('container')
            ->and($attrs->get('x-data')->valueText())->toBe('{ open: false }')
            ->and($attrs->get('x-show')->valueText())->toBe('open');
    });

    it('parses bound attributes alongside static', function (): void {
        $el = $this->parseElement('<div class="base" :class="dynamicClass" id="item"></div>');

        $attrs = $el->attributes();
        $all = $attrs->all();

        expect($all[0]->nameText())->toBe('class')
            ->and($all[0]->type())->toBe('static')
            ->and($all[0]->valueText())->toBe('base')
            ->and($all[1]->nameText())->toBe('class')
            ->and($all[1]->type())->toBe('bound')
            ->and($all[1]->valueText())->toBe('dynamicClass')
            ->and($all[2]->nameText())->toBe('id')
            ->and($all[2]->type())->toBe('static');
    });
});
