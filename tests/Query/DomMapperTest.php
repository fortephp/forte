<?php

declare(strict_types=1);

use Forte\Querying\DomMapper;

describe('DomMapper element mapping', function (): void {
    it('maps simple element', function (): void {
        $doc = $this->parse('<div class="container">Content</div>');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<div')
            ->and($xml)->toContain('class="container"')
            ->and($xml)->toContain('data-forte-idx');
    });

    it('maps nested elements', function (): void {
        $doc = $this->parse('<div><span>Text</span></div>');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<div')
            ->and($xml)->toContain('<span')
            ->and($xml)->toContain('Text');
    });

    it('maps void elements', function (): void {
        $doc = $this->parse('<input type="text" name="email">');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<input')
            ->and($xml)->toContain('type="text"')
            ->and($xml)->toContain('name="email"');
    });
});

describe('DomMapper directive mapping', function (): void {
    it('maps directive block with content', function (): void {
        $doc = $this->parse('@if($show)<div>Content</div>@endif');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<forte:if')
            ->and($xml)->toContain('args="($show)"')
            ->and($xml)->toContain('<div')
            ->and($xml)->toContain('Content');
    });

    it('maps standalone directive', function (): void {
        $doc = $this->parse('@include("partial")');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<forte:include')
            ->and($xml)->toContain('args="');
    });

    it('maps foreach directive', function (): void {
        $doc = $this->parse('@foreach($items as $item)<li>{{ $item }}</li>@endforeach');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<forte:foreach')
            ->and($xml)->toContain('<li')
            ->and($xml)->toContain('<forte:echo');
    });
});

describe('DomMapper echo mapping', function (): void {
    it('maps escaped echo', function (): void {
        $doc = $this->parse('{{ $name }}');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<forte:echo')
            ->and($xml)->toContain('expression="$name"');
    });

    it('maps raw echo', function (): void {
        $doc = $this->parse('{!! $html !!}');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<forte:raw-echo')
            ->and($xml)->toContain('expression="$html"');
    });
});

describe('DomMapper attribute mapping', function (): void {
    it('maps bound attributes with namespace', function (): void {
        $doc = $this->parse('<div :class="$classes">Content</div>');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('forte:bind-class="$classes"');
    });

    it('maps escaped attributes with namespace', function (): void {
        $doc = $this->parse('<div ::class="$classes">Content</div>');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('forte:escape-class="$classes"');
    });

    it('preserves wire:model attributes', function (): void {
        $doc = $this->parse('<input wire:model="name">');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('wire:model="name"');
    });
});

describe('DomMapper component mapping', function (): void {
    it('maps blade component', function (): void {
        $doc = $this->parse('<x-button type="primary">Click</x-button>');

        $mapper = new DomMapper($doc);
        $result = $mapper->build();

        $xml = $result['dom']->saveXML();

        expect($xml)->toContain('<x-button')
            ->and($xml)->toContain('data-forte-component="true"')
            ->and($xml)->toContain('type="primary"');
    });
});
