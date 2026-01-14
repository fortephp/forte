<?php

declare(strict_types=1);

describe('Partial Closing', function (): void {
    it('handles partial echo endings', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '{{ $x }',
        '{{ $x',
        '{!! $x !',
        '{!! $x',
        '{{{ $x }}',
        '{{{ $x }',
        '{{ $x }}}}',
    ]);

    it('handles partial directive endings', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '@if($x)content',
        '@if($x)content@end',
        '@if($x)content@endi',
        '@foreach($items as $item){{ $item }}',
        '@section("name")content',
        '@if($a)@if($b)inner@endif',
    ]);

    it('handles partial comment endings', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '{{-- comment',
        '{{-- comment --',
        '{{-- comment -',
        '<!-- comment',
        '<!-- comment -',
        '<!-- comment --',
    ]);

    it('handles partial PHP endings', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '<?php echo $x;',
        '<?php echo $x; ?',
        '<?= $x',
        '<?= $x ?',
    ]);

    it('handles partial HTML endings', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '<div',
        '<div class="x"',
        '<div class="x" id',
        '<div>content',
        '<div>content</div',
        '<div>content</',
    ]);
});
