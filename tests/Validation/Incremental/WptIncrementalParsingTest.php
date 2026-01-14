<?php

declare(strict_types=1);

describe('WPT HTML Validation', function (): void {
    it('parses incrementally at multiple byte positions', function (string $path): void {
        $this->assertIncrementalParsing(file_get_contents($path), 0.05);
    })->with('wpt html');

    it('parses and reconstructs full documents', function (string $path): void {
        $content = file_get_contents($path);
        $doc = $this->parse($content);

        expect($doc->render())->toBe($content);
    })->with('wpt html');
});
