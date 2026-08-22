<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;

describe('Large document scaling', function (): void {
    it('parses generated templates larger than one mebibyte without a size gate', function (): void {
        $line = '<div class="p-4">{{ $value }}</div>'."\n";
        $template = str_repeat($line, 30_000);

        expect(strlen($template))->toBeGreaterThan(1024 * 1024);

        $document = Document::parse($template);

        expect($document->source())->toBe($template)
            ->and($document)->toHaveCount(60_000);
    });
});
