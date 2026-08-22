<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;

describe('repeated queries', function (): void {
    test('return the same elements every time', function (): void {
        $doc = $this->parse('<div><p>a</p><p>b</p><span>c</span></div>');

        $first = $doc->getElements()->map(fn (ElementNode $e) => $e->tagNameText())->all();
        $second = $doc->getElements()->map(fn (ElementNode $e) => $e->tagNameText())->all();
        $third = $doc->getElements()->map(fn (ElementNode $e) => $e->tagNameText())->all();

        expect($first)->toBe(['div', 'p', 'p', 'span'])
            ->and($second)->toBe($first)
            ->and($third)->toBe($first);
    });

    test('return the same node instances every time', function (): void {
        $doc = $this->parse('<div><p>a</p></div>');

        $first = $doc->getElements()->all();
        $second = $doc->getElements()->all();

        expect($second)->toHaveCount(count($first));

        foreach ($first as $index => $node) {
            expect($second[$index])->toBe($node);
        }
    });

    test('do not interfere across queries for different node kinds', function (): void {
        $doc = $this->parse('<div>{{ $a }}</div>@if ($b)<span>x</span>@endif');

        $elementsBefore = $doc->getElements()->count();
        $echoes = $doc->allEchoes()->count();
        $directives = $doc->getBlockDirectives()->count();
        $elementsAfter = $doc->getElements()->count();

        expect($elementsBefore)->toBe(2)
            ->and($echoes)->toBe(1)
            ->and($directives)->toBe(1)
            ->and($elementsAfter)->toBe($elementsBefore);
    });

    test('are independent between documents', function (): void {
        $first = Document::parse('<div><p>a</p></div>');
        $second = Document::parse('<section><article>b</article><aside>c</aside></section>');

        $firstNames = $first->getElements()->map(fn (ElementNode $e) => $e->tagNameText())->all();
        $secondNames = $second->getElements()->map(fn (ElementNode $e) => $e->tagNameText())->all();
        $firstAgain = $first->getElements()->map(fn (ElementNode $e) => $e->tagNameText())->all();

        expect($firstNames)->toBe(['div', 'p'])
            ->and($secondNames)->toBe(['section', 'article', 'aside'])
            ->and($firstAgain)->toBe(['div', 'p']);
    });

    test('find the same nodes after another query has run', function (string $input): void {
        $doc = $this->parse($input);

        $before = count($doc->findAll(fn () => true));
        $doc->getElements()->count();
        $after = count($doc->findAll(fn () => true));

        expect($after)->toBe($before)->and($before)->toBeGreaterThan(0);
    })->with([
        'plain markup' => ['<div><p>a</p></div>'],
        'blade echo' => ['<p>{{ $name }}</p>'],
        'directive block' => ['@foreach ($rows as $row)<li>{{ $row }}</li>@endforeach'],
        'component' => ['<x-alert :message="$m">body</x-alert>'],
        'deeply nested' => [str_repeat('<div>', 30).'x'.str_repeat('</div>', 30)],
        'comments and text' => ['<!-- c -->text{{-- b --}}<p>x</p>'],
    ]);

    test('an empty document stays empty however often it is asked', function (): void {
        $doc = $this->parse('');

        expect($doc->getElements()->count())->toBe(0)
            ->and($doc->getElements()->count())->toBe(0)
            ->and(count($doc->findAll(fn () => true)))->toBe(0);
    });
});
