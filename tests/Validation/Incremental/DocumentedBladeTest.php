<?php

declare(strict_types=1);

describe('Documented Blade Samples', function (): void {
    it('parses and reconstructs documented Blade samples', function ($path): void {
        $content = file_get_contents($path);
        $doc = $this->parse($content);
        $reconstructed = $doc->render();

        expect($reconstructed)->toBe($content);
    })->with('blade docs');

    it('parses incrementally at multiple byte positions', function ($path): void {
        $this->assertIncrementalParsing(file_get_contents($path), 0.05);
    })->with('blade docs');
});
