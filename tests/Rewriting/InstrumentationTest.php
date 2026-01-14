<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Instrumentation;

describe('Instrumentation', function (): void {
    it('instruments elements with default comments', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(Instrumentation::make()->elements())->render();

        expect($result)
            ->toContain(
                '<!-- forte:start',
                '<!-- forte:end -->',
                'Content',
            );
    });

    it('instruments elements matching specific patterns', function (): void {
        $doc = $this->parse('<div>A</div><span>B</span><section>C</section>');

        $result = $doc->apply(Instrumentation::make()->elements(['div', 'section']))->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(2)
            ->and($result)->toContain('<span>B</span>');
    });

    it('instruments elements with wildcard patterns', function (): void {
        $doc = $this->parse('<div>A</div><span>B</span>');

        $result = $doc->apply(Instrumentation::make()->elements(['*']))->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(2);
    });

    it('uses custom prefix', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements()
                ->prefix('custom')
        )->render();

        expect($result)->toContain('<!-- custom:start', '<!-- custom:end -->');
    });

    it('instruments components', function (): void {
        $doc = $this->parse('<x-alert>Warning!</x-alert>');

        $result = $doc->apply(Instrumentation::make()->components())->render();

        expect($result)->toContain(
            '<!-- forte:start',
            'Warning!',
            '<!-- forte:end -->',
        );
    });

    it('instruments components with patterns', function (): void {
        $doc = $this->parse('<x-button>Click</x-button><x-card>Card</x-card><livewire:counter />');

        $result = $doc->apply(
            Instrumentation::make()->components(['x-button', 'livewire:*'])
        )->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(2)
            ->and($result)->toContain('<x-card>Card</x-card>');
    });

    test('default instruments both elements and components', function (): void {
        $doc = $this->parse('<div>A</div><x-alert>B</x-alert>');

        $result = $doc->apply(Instrumentation::make())->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(2);
    });

    it('instruments directives', function (): void {
        $doc = $this->parse('@include("header")');

        $result = $doc->apply(Instrumentation::make()->directives())->render();

        expect($result)->toContain(
            '<!-- forte:start',
            '@include("header")',
            '<!-- forte:end -->'
        );
    });

    it('instruments directive blocks', function (): void {
        $doc = $this->parse('@if($show)<span>Visible</span>@endif');

        $result = $doc->apply(
            Instrumentation::make()
                ->directiveBlocks()
        )->render();

        expect($result)->toContain(
            '<!-- forte:start',
            '@if($show)',
            '@endif',
            '<!-- forte:end -->',
        );
    });

    it('instruments directives with patterns', function (): void {
        $doc = $this->parse('@include("a")@yield("b")@extends("layout")');

        $result = $doc->apply(
            Instrumentation::make()
                ->directives(['include', 'yield'])
        )->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(2)
            ->and($result)->toContain('@extends("layout")');
    });

    it('uses custom callback for markers', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()->elements()->using(fn ($meta) => [
                "<!-- START:{$meta['name']} -->",
                '<!-- END -->',
            ])
        )->render();

        expect($result)->toContain(
            '<!-- START:div -->',
            '<!-- END -->'
        );
    });

    test('callback receives full metadata', function (): void {
        $doc = $this->parse('<x-alert type="warning">Hello</x-alert>');

        $captured = null;
        $doc->apply(
            Instrumentation::make()
                ->components()
                ->using(function ($meta) use (&$captured) {
                    $captured = $meta;

                    return ['', ''];
                })
        )->render();

        expect($captured)->toBeArray()
            ->and($captured['type'])->toBe('component')
            ->and($captured['name'])->toBe('x-alert')
            ->and($captured['componentName'])->toBe('alert')
            ->and($captured['componentType'])->toBe('blade')
            ->and($captured['prefix'])->toBe('x-')
            ->and($captured)->not->toHaveKey('line')
            ->and($captured)->toHaveKey('depth');
    });

    test('callback can add attributes to elements', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()->elements()->using(fn ($meta) => [
                '', // no start comment
                '', // no end comment
                ['data-instrumented' => 'true', 'data-type' => $meta['type']],
            ])
        )->render();

        expect($result)
            ->toContain(
                'data-instrumented="true"',
                'data-type="element"'
            )
            ->and($result)->not->toContain('<!-- forte');
    });

    test('callback can combine comments and attributes', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()->elements()->using(function ($meta) {
                $encoded = base64_encode(json_encode($meta));

                return [
                    '<!-- begin -->',
                    '<!-- end -->',
                    ['data-source' => $encoded],
                ];
            })
        )->render();

        expect($result)
            ->toContain(
                '<!-- begin -->',
                '<!-- end -->',
                'data-source="'
            );
    });

    it('can include line numbers', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $captured = null;
        $doc->apply(
            Instrumentation::make()
                ->elements()
                ->withLineNumbers()
                ->using(function ($meta) use (&$captured) {
                    $captured = $meta;

                    return ['', ''];
                })
        )->render();

        expect($captured)->toHaveKey('line')
            ->and($captured['line'])->toBe(1);
    });

    it('can add extra metadata', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $captured = null;
        $doc->apply(
            Instrumentation::make()
                ->elements()
                ->withMeta(['viewPath' => 'welcome.blade.php', 'app' => 'myapp'])
                ->using(function ($meta) use (&$captured) {
                    $captured = $meta;

                    return ['', ''];
                })
        )->render();

        expect($captured['viewPath'])->toBe('welcome.blade.php')
            ->and($captured['app'])->toBe('myapp');
    });

    it('can decode markers back to metadata', function (): void {
        $meta = ['type' => 'element', 'name' => 'div', 'line' => 1];
        $encoded = base64_encode(json_encode($meta));

        $decoded = Instrumentation::decode($encoded);
        expect($decoded)->toBe($meta);

        $comment = "<!-- forte:start {$encoded} -->";
        $decoded = Instrumentation::decode($comment);
        expect($decoded)->toBe($meta);
    });

    test('decode returns null for invalid input', function (): void {
        expect(Instrumentation::decode('invalid'))->toBeNull()
            ->and(Instrumentation::decode('<!-- not a marker -->'))->toBeNull();
    });

    test('matching() sets patterns for all types', function (): void {
        $doc = $this->parse('<div>A</div><span>B</span><x-card>C</x-card><x-button>D</x-button>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements()
                ->components()
                ->matching(['div', 'x-card'])
        )->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(2)
            ->and($result)->toContain(
                '<span>B</span>',
                '<x-button>D</x-button>'
            );
    });

    it('preserves sole root element structure', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(Instrumentation::make()->elements())->render();

        expect($result)->toMatch('/<div>.*<!-- forte:start.*Content.*<!-- forte:end.*<\/div>/s');
    });

    it('wraps non-sole-root elements normally', function (): void {
        $doc = $this->parse('<div>A</div><span>B</span>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements(['div'])
        )->render();

        expect($result)->toContain(
            '<!-- forte:start',
            '<div>A</div>',
            '<!-- forte:end -->'
        );
    });

    it('handles nested structures', function (): void {
        $doc = $this->parse('<div><span>A</span><x-card><p>B</p></x-card></div>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements()
                ->components()
        )->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(4);
    });

    it('handles mixed content', function (): void {
        $doc = $this->parse('<div>@include("header")<x-nav /><footer>Bottom</footer></div>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements(['footer'])
                ->components()
                ->directives(['include'])
        )->render();

        expect(substr_count((string) $result, '<!-- forte:start'))->toBe(3);
    });

    test('asAttribute injects data-instrumentation by default', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements()
                ->asAttribute()
        )->render();

        expect($result)->toContain('data-instrumentation="')
            ->and($result)->not->toContain('<!-- forte');
    });

    test('asAttribute uses custom attribute name', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()->elements()->asAttribute('data-debug')
        )->render();

        expect($result)->toContain('data-debug="')
            ->and($result)->not->toContain('data-instrumentation');
    });

    test('asAttribute encodes metadata as base64', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()->elements()->asAttribute()
        )->render();

        preg_match('/data-instrumentation="([^"]+)"/', (string) $result, $matches);
        expect($matches)->toHaveCount(2);

        $decoded = json_decode(base64_decode($matches[1]), true);
        expect($decoded)->toBeArray()
            ->and($decoded['type'])->toBe('element')
            ->and($decoded['name'])->toBe('div');
    });

    test('asJsonAttribute stores readable JSON', function (): void {
        $doc = $this->parse('<div>Content</div>');

        $result = $doc->apply(
            Instrumentation::make()
                ->elements()
                ->asJsonAttribute()
        )->render();

        expect($result)->toContain('data-instrumentation="', '"type"', '"element"');
    });

    test('asAttribute works with components', function (): void {
        $doc = $this->parse('<x-alert>Warning</x-alert>');

        $result = $doc->apply(
            Instrumentation::make()
                ->components()
                ->asAttribute()
        )->render();

        expect($result)->toContain('data-instrumentation="');

        preg_match('/data-instrumentation="([^"]+)"/', (string) $result, $matches);
        $decoded = json_decode(base64_decode($matches[1]), true);
        expect($decoded['type'])->toBe('component')
            ->and($decoded['componentName'])->toBe('alert');
    });
});
