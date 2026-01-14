<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Directives\WrapDirective;

describe('Directive Wrapping Rewriters', function (): void {
    it('wraps inline directive with element', function (): void {
        $doc = $this->parse('@yield("content")');

        $result = $doc->apply(new WrapDirective('yield', 'section'))->render();

        expect($result)->toBe('<section>@yield("content")</section>');
    });

    it('wraps directive with attributes', function (): void {
        $doc = $this->parse('@yield("content")');

        $result = $doc->apply(new WrapDirective('yield', 'div', ['class' => 'content-wrapper']))->render();

        expect($result)->toBe('<div class="content-wrapper">@yield("content")</div>');
    });

    it('wraps block directive', function (): void {
        $doc = $this->parse('@foreach($items as $item)<li>{{ $item }}</li>@endforeach');

        $result = $doc->apply(new WrapDirective('foreach', 'ul'))->render();

        expect($result)->toContain('<ul>@foreach($items as $item)')
            ->and($result)->toContain('@endforeach</ul>');
    });

    it('uses wildcard pattern', function (): void {
        $doc = $this->parse('@include("a")@includeIf("b")@includeWhen($c, "d")');

        $result = $doc->apply(new WrapDirective('include*', 'span'))->render();

        expect($result)->toBe('<span>@include("a")</span><span>@includeIf("b")</span><span>@includeWhen($c, "d")</span>');
    });

    it('does not affect non-matching directives', function (): void {
        $doc = $this->parse('@yield("a")@include("b")');

        $result = $doc->apply(new WrapDirective('yield', 'section'))->render();

        expect($result)->toBe('<section>@yield("a")</section>@include("b")');
    });
});
