<?php

declare(strict_types=1);

use Forte\Ast\Elements\Attribute;

describe('Vue.js Attributes', function (): void {
    it('parses basic v-* directives as static attributes', function (): void {
        $html = '<div v-cloak v-show="shown" v-if="ok" v-for="item in items"></div>';

        $el = $this->parseElement($html);

        expect($el)->not()->toBeNull();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(4)
            ->and($attrs[0])->toBeInstanceOf(Attribute::class)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-cloak')
            ->and($attrs[1]->nameText())->toBe('v-show')
            ->and($attrs[1]->type())->toBe('static')
            ->and($attrs[1]->quote())->toBe('"')
            ->and($attrs[1]->valueText())->toBe('shown')
            ->and($attrs[2]->nameText())->toBe('v-if')
            ->and($attrs[2]->valueText())->toBe('ok')
            ->and($attrs[3]->nameText())->toBe('v-for')
            ->and($attrs[3]->valueText())->toBe('item in items');
    });

    it('parses all basic v-* directives', function (): void {
        $html = '<div v-cloak v-pre v-once v-show="shown" v-if="ok" v-else-if="maybe" v-else v-for="item in items" v-text="label" v-html="htmlStr"></div>';

        $el = $this->parseElement($html);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(10)
            ->and($attrs[0]->nameText())->toBe('v-cloak')
            ->and($attrs[0]->isBoolean())->toBeTrue()
            ->and($attrs[1]->nameText())->toBe('v-pre')
            ->and($attrs[2]->nameText())->toBe('v-once')
            ->and($attrs[3]->nameText())->toBe('v-show')
            ->and($attrs[3]->valueText())->toBe('shown')
            ->and($attrs[4]->nameText())->toBe('v-if')
            ->and($attrs[4]->valueText())->toBe('ok')
            ->and($attrs[5]->nameText())->toBe('v-else-if')
            ->and($attrs[5]->valueText())->toBe('maybe')
            ->and($attrs[6]->nameText())->toBe('v-else')
            ->and($attrs[7]->nameText())->toBe('v-for')
            ->and($attrs[7]->valueText())->toBe('item in items')
            ->and($attrs[8]->nameText())->toBe('v-text')
            ->and($attrs[8]->valueText())->toBe('label')
            ->and($attrs[9]->nameText())->toBe('v-html')
            ->and($attrs[9]->valueText())->toBe('htmlStr');
    });
});

describe('Vue.js Attributes - v-bind', function (): void {
    it('parses v-bind and : shorthand', function (): void {
        $html = '<div v-bind:class="{ active: isActive }" :class="{ active: isActive }"></div>';

        $el = $this->parseElement($html);
        expect($el)->not()->toBeNull();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(2)
            ->and($attrs[0])->toBeInstanceOf(Attribute::class)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-bind:class')
            ->and($attrs[0]->valueText())->toBe('{ active: isActive }')
            ->and($attrs[1])->toBeInstanceOf(Attribute::class)
            ->and($attrs[1]->type())->toBe('bound')
            ->and($attrs[1]->nameText())->toBe('class')
            ->and($attrs[1]->valueText())->toBe('{ active: isActive }');
    });

    it('parses v-bind with modifiers and dynamic args', function (): void {
        $html = '<div v-bind:class="{ active: isActive }" :class="{ active: isActive }" v-bind:title.camel="pageTitle" :[dataKey].prop="value"></div>';

        $el = $this->parseElement($html);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(4)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-bind:class')
            ->and($attrs[0]->valueText())->toBe('{ active: isActive }')

            ->and($attrs[1]->type())->toBe('bound')
            ->and($attrs[1]->nameText())->toBe('class')
            ->and($attrs[1]->valueText())->toBe('{ active: isActive }')

            ->and($attrs[2]->type())->toBe('static')
            ->and($attrs[2]->nameText())->toBe('v-bind:title.camel')
            ->and($attrs[2]->valueText())->toBe('pageTitle')

            ->and($attrs[3]->type())->toBe('bound')
            ->and($attrs[3]->nameText())->toBe('[dataKey].prop')
            ->and($attrs[3]->valueText())->toBe('value');
    });

    it('parses v-on directives with modifiers', function (): void {
        $html = '<button v-on:click.prevent="submit()"></button>';

        $el = $this->parseElement($html);
        expect($el)->not()->toBeNull();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0])->toBeInstanceOf(Attribute::class)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-on:click.prevent')
            ->and($attrs[0]->valueText())->toBe('submit()');
    });

    it('parses v-on and @ shorthand with modifiers', function (): void {
        $html = '<button v-on:click.prevent="submit()" @click.stop="submit()" @keyup.enter.exact="onEnter()"></button>';

        $el = $this->parseElement($html);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(3)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-on:click.prevent')
            ->and($attrs[0]->valueText())->toBe('submit()')
            ->and($attrs[1]->render())->toBe('@click.stop="submit()"')
            ->and($attrs[2]->render())->toBe('@keyup.enter.exact="onEnter()"');
    });

    it('parses v-model', function (): void {
        $html = '<input v-model="nameText">';

        $el = $this->parseElement($html);
        expect($el)->not()->toBeNull();

        $attrs = $el->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0])->toBeInstanceOf(Attribute::class)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-model')
            ->and($attrs[0]->valueText())->toBe('nameText');
    });

    it('parses v-model with modifiers', function (): void {
        $html = '<input v-model="nameText" v-model.trim="nameText" v-model.lazy.number="age">';

        $el = $this->parseElement($html);
        $attrs = $el->attributes()->all();

        expect($attrs)->toHaveCount(3)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-model')
            ->and($attrs[0]->valueText())->toBe('nameText')

            ->and($attrs[1]->nameText())->toBe('v-model.trim')
            ->and($attrs[1]->valueText())->toBe('nameText')

            ->and($attrs[2]->nameText())->toBe('v-model.lazy.number')
            ->and($attrs[2]->valueText())->toBe('age');
    });

    it('parses v-slot', function (): void {
        $html = '<template v-slot:default="{ item }"></template>';

        $elements = $this->parseElements($html);
        expect($elements)->toHaveCount(1);

        $attrs = $elements[0]->attributes()->all();
        expect($attrs)->toHaveCount(1)
            ->and($attrs[0])->toBeInstanceOf(Attribute::class)
            ->and($attrs[0]->type())->toBe('static')
            ->and($attrs[0]->nameText())->toBe('v-slot:default')
            ->and($attrs[0]->quote())->toBe('"')
            ->and($attrs[0]->valueText())->toBe('{ item }');
    });

    it('parses v-slot and # shorthand', function (): void {
        $html = '<template v-slot:default="{ item }"></template><template #footer="{ page }"></template>';

        $elements = $this->parseElements($html);
        expect($elements)->toHaveCount(2);

        $attrs1 = $elements[0]->attributes()->all();
        expect($attrs1)->toHaveCount(1)
            ->and($attrs1[0]->type())->toBe('static')
            ->and($attrs1[0]->nameText())->toBe('v-slot:default')
            ->and($attrs1[0]->quote())->toBe('"')
            ->and($attrs1[0]->valueText())->toBe('{ item }');

        $attrs2 = $elements[1]->attributes()->all();
        expect($attrs2)->toHaveCount(1)
            ->and($attrs2[0]->type())->toBe('static')
            ->and($attrs2[0]->nameText())->toBe('#footer')
            ->and($attrs2[0]->quote())->toBe('"')
            ->and($attrs2[0]->valueText())->toBe('{ page }');
    });
});
