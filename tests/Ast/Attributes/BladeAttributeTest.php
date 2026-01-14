<?php

declare(strict_types=1);

use Forte\Ast\EchoNode;
use Forte\Ast\Elements\Attribute;

describe('Blade Attributes - Standalone Echo', function (): void {
    it('parses standalone echo attribute', function (): void {
        $source = '<div {{ $attrs }}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(1);

        $attr = $attributes[0];
        expect($attr)->toBeInstanceOf(Attribute::class)
            ->and($attr->isBladeConstruct())->toBeTrue();

        $standaloneNode = $attr->getBladeConstruct()->asEcho();
        expect($standaloneNode)->toBeInstanceOf(EchoNode::class)
            ->and($standaloneNode->echoType())->toBe('escaped')
            ->and($standaloneNode->content())->toBe(' $attrs ');
    });

    it('parses standalone raw echo attribute', function (): void {
        $source = '<div {!! $attrs !!}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(1);

        $attr = $attributes[0];
        expect($attr->isBladeConstruct())->toBeTrue();

        $standaloneNode = $attr->getBladeConstruct()->asEcho();
        expect($standaloneNode)->toBeInstanceOf(EchoNode::class)
            ->and($standaloneNode->echoType())->toBe('raw')
            ->and($standaloneNode->content())->toBe(' $attrs ');
    });

    it('parses standalone triple echo attribute', function (): void {
        $source = '<div {{{ $attrs }}}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(1);

        $attr = $attributes[0];
        expect($attr->isBladeConstruct())->toBeTrue();

        $standaloneNode = $attr->getBladeConstruct()->asEcho();
        expect($standaloneNode)->toBeInstanceOf(EchoNode::class)
            ->and($standaloneNode->echoType())->toBe('triple');
    });
});

describe('Blade Attributes - Multiple Echoes', function (): void {
    it('parses multiple standalone echo attributes', closure: function (): void {
        $source = '<div {{ $a }} {{ $b }}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(2)
            ->and($attributes[0]->isBladeConstruct())->toBeTrue();

        $node1 = $attributes[0]->getBladeConstruct()->asEcho();
        expect($node1)->toBeInstanceOf(EchoNode::class)
            ->and($node1->content())->toBe(' $a ')
            ->and($attributes[1]->isBladeConstruct())->toBeTrue();

        $node2 = $attributes[1]->getBladeConstruct()->asEcho();
        expect($node2)->toBeInstanceOf(EchoNode::class)
            ->and($node2->content())->toBe(' $b ');
    });
});

describe('Blade Attributes - Mixed with Traditional', function (): void {
    it('parses mixed traditional and echo attributes', function (): void {
        $source = '<div class="static" {{ $attrs }}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(2)
            ->and($attributes[0]->isBladeConstruct())->toBeFalse()
            ->and($attributes[0]->type())->toBe('static')
            ->and($attributes[0]->nameText())->toBe('class')
            ->and($attributes[0]->valueText())->toBe('static')
            ->and($attributes[1]->isBladeConstruct())->toBeTrue();

        $standaloneNode = $attributes[1]->getBladeConstruct();
        expect($standaloneNode)->toBeInstanceOf(EchoNode::class);
    });

    it('parses echo before traditional attribute', function (): void {
        $source = '<div {{ $attrs }} class="static"></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(2)
            ->and($attributes[0]->isBladeConstruct())->toBeTrue()
            ->and($attributes[1]->isBladeConstruct())->toBeFalse()
            ->and($attributes[1]->type())->toBe('static')
            ->and($attributes[1]->nameText())->toBe('class');
    });
});

describe('Blade Attributes - Complex Expressions', function (): void {
    it('parses complex echo with method calls', function (): void {
        $source = '<ul {{ $attributes->merge([\'class\' => \'bg-\'.$color.\'-200\']) }}></ul>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->isBladeConstruct())->toBeTrue();

        $standaloneNode = $attributes[0]->getBladeConstruct()->asEcho();
        expect($standaloneNode)->toBeInstanceOf(EchoNode::class)
            ->and($standaloneNode->content())->toBe(" \$attributes->merge(['class' => 'bg-'.\$color.'-200']) ");
    });

    it('parses echo with ternary operator', function (): void {
        $source = '<div {{ $active ? "active" : "" }}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull();

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->isBladeConstruct())->toBeTrue();
    });
});

describe('Blade Attributes - Rendering', function (): void {
    it('renders element with standalone echo attribute correctly', function (): void {
        $source = '<div {{ $attrs }}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull()
            ->and($element->render())->toBe($source);
    });

    it('renders element with multiple echo attributes correctly', function (): void {
        $source = '<div {{ $a }} {{ $b }}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull()
            ->and($element->render())->toBe($source);
    });

    it('renders mixed traditional and echo attributes correctly', function (): void {
        $source = '<div class="test" {{ $attrs }} id="main"></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull()
            ->and($element->render())->toBe($source);
    });

    it('renders raw echo attribute correctly', function (): void {
        $source = '<div {!! $attrs !!}></div>';
        $element = $this->parseElement($source);

        expect($element)->not()->toBeNull()
            ->and($element->render())->toBe($source);
    });
});
