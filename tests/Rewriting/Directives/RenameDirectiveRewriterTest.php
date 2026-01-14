<?php

declare(strict_types=1);

use Forte\Rewriting\Passes\Directives\RenameDirective;

describe('Rename Directive Rewriters', function (): void {
    it('renames inline directive', function (): void {
        $doc = $this->parse('@include("header")');

        $result = $doc->apply(new RenameDirective('include', 'livewire'))->render();

        expect($result)->toBe('@livewire("header")');
    });

    it('preserves directive arguments', function (): void {
        $doc = $this->parse('@include("partials.header", ["title" => $title])');

        $result = $doc->apply(new RenameDirective('include', 'livewire'))->render();

        expect($result)->toBe('@livewire("partials.header", ["title" => $title])');
    });

    it('uses callback for dynamic renaming preserving casing', function (): void {
        $doc = $this->parse('@pushOnce("scripts")');

        $result = $doc->apply(new RenameDirective('push*', fn ($name) => str_replace('push', 'stack', $name)))->render();

        expect($result)->toBe('@stackOnce("scripts")');
    });

    it('renames block directive', function (): void {
        $doc = $this->parse('@once<script>alert(1)</script>@endonce');

        $result = $doc->apply(new RenameDirective('once', 'pushOnce'))->render();

        expect($result)->toBe('@pushOnce<script>alert(1)</script>@endpushOnce');
    });

    it('does not affect non-matching directives', function (): void {
        $doc = $this->parse('@include("a")@yield("b")');

        $result = $doc->apply(new RenameDirective('include', 'livewire'))->render();

        expect($result)->toBe('@livewire("a")@yield("b")');
    });
});
