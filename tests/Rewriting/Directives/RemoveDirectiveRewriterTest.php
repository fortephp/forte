<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Directives\RemoveDirective;

describe('Remove Directive Rewriters', function (): void {
    it('removes inline directive by exact name', function (): void {
        $doc = $this->parse('<div>@include("header")</div>');

        $result = $doc->apply(new RemoveDirective('include'))->render();

        expect($result)->toBe('<div></div>');
    });

    it('removes multiple inline directives', function (): void {
        $doc = $this->parse('@include("a")@include("b")@include("c")');

        $result = $doc->apply(new RemoveDirective('include'))->render();

        expect($result)->toBe('');
    });

    it('removes directive using wildcard pattern', function (): void {
        $doc = $this->parse('@push("scripts")@pushOnce("styles")@pushIf($cond, "stack")');

        $result = $doc->apply(new RemoveDirective('push*'))->render();

        expect($result)->toBe('');
    });

    it('removes block directive', function (): void {
        $doc = $this->parse('<div>@if($show)<span>Content</span>@endif</div>');

        $result = $doc->apply(new RemoveDirective('if'))->render();

        expect($result)->toBe('<div></div>');
    });

    it('removes nested block directives', function (): void {
        $doc = $this->parse('@if($a)@if($b)Inner@endif@endif');

        $result = $doc->apply(new RemoveDirective('if'))->render();

        expect($result)->toBe('');
    });

    it('does not affect non-matching directives', function (): void {
        $doc = $this->parse('@include("header")@yield("content")');

        $result = $doc->apply(new RemoveDirective('include'))->render();

        expect($result)->toBe('@yield("content")');
    });
});
