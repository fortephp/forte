<?php

declare(strict_types=1);

use Forte\Ast\Document\NodeCollection;

describe('Document Echoes', function (): void {
    it('can get all echoes', function (): void {
        $doc = $this->parse('{{ $escaped }} {!! $raw !!} {{{ $triple }}}');

        $allEchoes = $doc->getEchoes();

        expect($allEchoes)->toHaveCount(3)
            ->and($allEchoes)->toBeInstanceOf(NodeCollection::class);
    });

    it('can get raw echoes only', function (): void {
        $doc = $this->parse('{{ $escaped }} {!! $raw !!} {{{ $triple }}}');

        $rawEchoes = $doc->getRawEchoes();

        expect($rawEchoes)->toHaveCount(1)
            ->and($rawEchoes->first()->getDocumentContent())->toBe('{!! $raw !!}');
    });

    it('can get escaped echoes only', function (): void {
        $doc = $this->parse('{{ $escaped }} {!! $raw !!} {{{ $triple }}}');

        $escapedEchoes = $doc->getEscapedEchoes();

        expect($escapedEchoes)->toHaveCount(1)
            ->and($escapedEchoes->first()->getDocumentContent())->toBe('{{ $escaped }}');
    });

    it('can get triple echoes only', function (): void {
        $doc = $this->parse('{{ $escaped }} {!! $raw !!} {{{ $triple }}}');

        $tripleEchoes = $doc->getTripleEchoes();

        expect($tripleEchoes)->toHaveCount(1)
            ->and($tripleEchoes->first()->getDocumentContent())->toBe('{{{ $triple }}}');
    });

    it('returns empty collection when no echoes', function (): void {
        $doc = $this->parse('Hello world');

        $echoes = $doc->getEchoes();

        expect($echoes)->toHaveCount(0);
    });
});
