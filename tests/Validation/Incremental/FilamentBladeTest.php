<?php

declare(strict_types=1);

describe('Filament Package Blade Files', function (): void {
    it('parses and reconstructs Filament Blade templates', function ($path): void {
        $content = file_get_contents($path);
        $doc = $this->parse($content);
        $reconstructed = $doc->render();

        expect($reconstructed)->toBe($content);
    })->with('filament samples');

    it('parses incrementally at multiple byte positions', function ($path): void {
        $content = file_get_contents($path);
        $this->assertIncrementalParsing(file_get_contents($path), 0.05);
    })->with('filament samples');
});
