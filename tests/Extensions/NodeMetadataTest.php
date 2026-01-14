<?php

declare(strict_types=1);

use Forte\Ast\EchoNode;

describe('Node Metadata', function (): void {
    it('can set and get metadata on nodes', function (): void {
        $doc = $this->parse('{{ $var }} text');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        expect($echo)->not->toBeNull();

        $echo->setData('custom_key', 'custom_value');
        expect($echo->getData('custom_key'))->toBe('custom_value');
    });

    it('returns default value when key does not exist', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);

        expect($echo->getData('nonexistent'))->toBeNull()
            ->and($echo->getData('nonexistent', 'default'))->toBe('default');
    });

    it('can check if metadata exists', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->setData('exists', true);

        expect($echo->hasData('exists'))->toBeTrue()
            ->and($echo->hasData('not_exists'))->toBeFalse();
    });

    it('can remove metadata', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->setData('remove_me', 'value');

        expect($echo->hasData('remove_me'))->toBeTrue();

        $echo->removeData('remove_me');

        expect($echo->hasData('remove_me'))->toBeFalse();
    });

    it('can get all metadata', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->setData('key1', 'value1');
        $echo->setData('key2', 'value2');

        $all = $echo->getAllData();

        expect($all)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    });

    it('stores complex data structures', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->setData('modifiers', [
            ['name' => 'upper', 'args' => []],
            ['name' => 'truncate', 'args' => [50]],
        ]);

        $modifiers = $echo->getData('modifiers');

        expect($modifiers)->toBeArray()
            ->and($modifiers[0]['name'])->toBe('upper')
            ->and($modifiers[1]['args'][0])->toBe(50);
    });

    it('returns fluent interface from setData', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);

        $result = $echo->setData('key', 'value');

        expect($result)->toBe($echo);
    });

    it('isolates metadata per node', function (): void {
        $doc = $this->parse('{{ $first }} {{ $second }}');

        $echos = $doc->findAll(fn ($n) => $n instanceof EchoNode);

        $echos[0]->setData('name', 'first');
        $echos[1]->setData('name', 'second');

        expect($echos[0]->getData('name'))->toBe('first')
            ->and($echos[1]->getData('name'))->toBe('second');
    });
});

describe('Node Tagging', function (): void {
    it('can tag and check tags on nodes', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->tag('processed');

        expect($echo->hasTag('processed'))->toBeTrue()
            ->and($echo->hasTag('unprocessed'))->toBeFalse();
    });

    it('can add multiple tags', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->tag('validated');
        $echo->tag('transformed');
        $echo->tag('output');

        expect($echo->hasTag('validated'))->toBeTrue()
            ->and($echo->hasTag('transformed'))->toBeTrue()
            ->and($echo->hasTag('output'))->toBeTrue();
    });

    it('can remove tags', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->tag('removable');

        expect($echo->hasTag('removable'))->toBeTrue();

        $echo->untag('removable');

        expect($echo->hasTag('removable'))->toBeFalse();
    });

    it('can get all tags', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->tag('a');
        $echo->tag('b');
        $echo->tag('c');

        $tags = $echo->getTags();

        expect($tags)->toContain('a')
            ->and($tags)->toContain('b')
            ->and($tags)->toContain('c')
            ->and(count($tags))->toBe(3);
    });

    it('returns fluent interface from tag', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);

        $result = $echo->tag('test');

        expect($result)->toBe($echo);
    });

    it('can find nodes by tag via document', function (): void {
        $doc = $this->parse('{{ $first }} text {{ $second }} {{ $third }}');

        $echos = $doc->findAll(fn ($n) => $n instanceof EchoNode);
        $echos[0]->tag('special');
        $echos[2]->tag('special');

        $tagged = $doc->findNodesByTag('special');

        expect(count($tagged))->toBe(2)
            ->and($tagged[0]->index())->toBe($echos[0]->index())
            ->and($tagged[1]->index())->toBe($echos[2]->index());
    });

    it('isolates tags per node', function (): void {
        $doc = $this->parse('{{ $first }} {{ $second }}');

        $echos = $doc->findAll(fn ($n) => $n instanceof EchoNode);
        $echos[0]->tag('only-first');

        expect($echos[0]->hasTag('only-first'))->toBeTrue()
            ->and($echos[1]->hasTag('only-first'))->toBeFalse();
    });

    it('handles tagging same tag multiple times', function (): void {
        $doc = $this->parse('{{ $var }}');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);
        $echo->tag('duplicate');
        $echo->tag('duplicate');
        $echo->tag('duplicate');

        $tags = $echo->getTags();
        expect(count($tags))->toBe(1)
            ->and($tags[0])->toBe('duplicate');
    });
});
