<?php

declare(strict_types=1);

use Forte\Parser\ParserOptions;

describe('Node Line Utilities', function (): void {
    describe('lineSpan()', function (): void {
        it('returns start and end line for single-line element', function (): void {
            $doc = $this->parse('<div>Content</div>');
            $div = $doc->getChildren()[0];

            $span = $div->lineSpan();

            expect($span)->toBe(['start' => 1, 'end' => 1]);
        });

        it('returns correct span for multi-line element', function (): void {
            $template = <<<'HTML'
<div>
    <p>Content</p>
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            $span = $div->lineSpan();

            expect($span['start'])->toBe(1)
                ->and($span['end'])->toBe(3);
        });

        it('handles nested elements', function (): void {
            $template = <<<'HTML'
<div>
    <span>Text</span>
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            $span = $div->nodes()->whereElementIs('span')->first();
            expect($span)->not->toBeNull()
                ->and($span->lineSpan()['start'])->toBe(2)
                ->and($span->lineSpan()['end'])->toBe(2);
        });
    });

    describe('lineCount()', function (): void {
        it('returns 1 for single-line element', function (): void {
            $doc = $this->parse('<div>Short content</div>');
            $div = $doc->getChildren()[0];

            expect($div->lineCount())->toBe(1);
        });

        it('returns correct count for multi-line element', function (): void {
            $template = <<<'HTML'
<div>
    Line 2
    Line 3
    Line 4
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            expect($div->lineCount())->toBe(5);
        });

        it('returns 1 for inline element', function (): void {
            $doc = $this->parse('<p><span>Inline</span></p>');
            $p = $doc->getChildren()[0];
            $span = $p->firstChild();

            expect($span->lineCount())->toBe(1);
        });
    });

    describe('containsLine()', function (): void {
        it('returns true for line within element', function (): void {
            $template = <<<'HTML'
<div>
    Line 2
    Line 3
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            expect($div->containsLine(1))->toBeTrue()
                ->and($div->containsLine(2))->toBeTrue()
                ->and($div->containsLine(3))->toBeTrue()
                ->and($div->containsLine(4))->toBeTrue();
        });

        it('returns false for line before element', function (): void {
            $template = <<<'HTML'

<div>Content</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->findElementByName('div');

            expect($div)->not->toBeNull()
                ->and($div->containsLine(1))->toBeFalse()
                ->and($div->containsLine(2))->toBeTrue();
        });

        it('returns false for line after element', function (): void {
            $template = <<<'HTML'
<div>Content</div>

HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            expect($div->containsLine(1))->toBeTrue()
                ->and($div->containsLine(2))->toBeFalse();
        });

        it('returns true for start and end lines', function (): void {
            $template = <<<'HTML'
<div>
    Content
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            expect($div->containsLine(1))->toBeTrue()
                ->and($div->containsLine(3))->toBeTrue();
        });
    });

    describe('isMultiline()', function (): void {
        it('returns false for single-line element', function (): void {
            $doc = $this->parse('<span>Single line</span>');
            $span = $doc->getChildren()[0];

            expect($span->isMultiline())->toBeFalse();
        });

        it('returns true for multi-line element', function (): void {
            $template = <<<'HTML'
<div>
    Content
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            expect($div->isMultiline())->toBeTrue();
        });
    });

    describe('isSingleLine()', function (): void {
        it('returns true for single-line element', function (): void {
            $doc = $this->parse('<span>Single line</span>');
            $span = $doc->getChildren()[0];

            expect($span->isSingleLine())->toBeTrue();
        });

        it('returns false for multi-line element', function (): void {
            $template = <<<'HTML'
<div>
    Content
</div>
HTML;
            $doc = $this->parse($template);
            $div = $doc->getChildren()[0];

            expect($div->isSingleLine())->toBeFalse();
        });
    });

    describe('with Blade directives', function (): void {
        test('line utilities work with directive blocks', function (): void {
            $template = <<<'BLADE'
@if($condition)
    <p>Content</p>
@endif
BLADE;
            $doc = $this->parse($template, ParserOptions::defaults());
            $ifBlock = $doc->getChildren()[0];

            expect($ifBlock->lineSpan())->toBe(['start' => 1, 'end' => 3])
                ->and($ifBlock->lineCount())->toBe(3)
                ->and($ifBlock->isMultiline())->toBeTrue()
                ->and($ifBlock->containsLine(2))->toBeTrue();
        });
    });

    describe('deeply nested structure', function (): void {
        test('each node reports correct line info', function (): void {
            $template = <<<'HTML'
<html>
    <body>
        <main>
            <p>Text</p>
        </main>
    </body>
</html>
HTML;
            $doc = $this->parse($template);
            $html = $doc->getChildren()[0];

            expect($html->lineSpan())->toBe(['start' => 1, 'end' => 7])
                ->and($html->lineCount())->toBe(7);

            $body = $html->nodes()->whereElementIs('body')->first();
            expect($body->lineSpan())->toBe(['start' => 2, 'end' => 6]);

            $main = $body->nodes()->whereElementIs('main')->first();
            expect($main->lineSpan())->toBe(['start' => 3, 'end' => 5]);

            $p = $main->nodes()->whereElementIs('p')->first();
            expect($p->lineSpan())->toBe(['start' => 4, 'end' => 4])
                ->and($p->isSingleLine())->toBeTrue();
        });
    });
});
