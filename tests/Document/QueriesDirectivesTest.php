<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Document\NodeCollection;

describe('Document Directives', function (): void {
    it('can get all directives', function (): void {
        $doc = $this->parse('@include("header") @extends("layout")');

        $directives = $doc->getDirectives();

        expect($directives)->toHaveCount(2)
            ->and($directives)->toBeInstanceOf(NodeCollection::class);
    });

    it('can get all block directives', function (): void {
        $doc = $this->parse('@if(true) content @endif @foreach($items as $item) {{ $item }} @endforeach');

        $blocks = $doc->getBlockDirectives();

        expect($blocks)->toHaveCount(2)
            ->and($blocks->first())->toBeInstanceOf(DirectiveBlockNode::class);
    });

    it('can find directive by name', function (): void {
        $doc = $this->parse('@include("header") @extends("layout") @include("footer")');

        $include = $doc->findDirectiveByName('include');

        expect($include)->toBeInstanceOf(DirectiveNode::class)
            ->and($include->nameText())->toBe('include');
    });

    it('stops find directive at first match', function (): void {
        $doc = $this->parse('@include("one") @include("two")');

        $include = $doc->findDirectiveByName('include');

        expect($include->getDocumentContent())->toBe('@include("one")');
    });

    it('can find all directives by name', function (): void {
        $doc = $this->parse('@include("one") @extends("layout") @include("two")');

        $includes = $doc->findDirectivesByName('include');

        expect($includes)->toHaveCount(2);
    });

    it('can find block directive by name', function (): void {
        $doc = $this->parse('@if(true) @endif @foreach($items as $item) @endforeach');

        $if = $doc->findBlockDirectiveByName('if');

        expect($if)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($if->nameText())->toBe('if');
    });

    it('can find all block directives by name', function (): void {
        $doc = $this->parse('@if(true) @endif @if(false) @endif @foreach($items as $item) @endforeach');

        $ifs = $doc->findBlockDirectivesByName('if');

        expect($ifs)->toHaveCount(2);
    });

    it('returns null when directive not found', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $directive = $doc->findDirectiveByName('include');

        expect($directive)->toBeNull();
    });

    it('returns empty collection when no directives match', function (): void {
        $doc = $this->parse('@extends("layout")');

        $includes = $doc->findDirectivesByName('include');

        expect($includes)->toHaveCount(0);
    });

    it('excludes block directive children from getDirectives', function (): void {
        $doc = $this->parse('@if(true) content @endif');

        $directives = $doc->getDirectives();

        expect($directives)->toHaveCount(0);
    });

    it('includes standalone directives inside block content', function (): void {
        $doc = $this->parse('@if(true) @include("partial") @endif');

        $directives = $doc->getDirectives();

        expect($directives)->toHaveCount(1)
            ->and($directives->first()->nameText())->toBe('include');
    });

    it('excludes all component directives from blocks with intermediates', function (): void {
        $doc = $this->parse('@if(true) a @elseif(false) b @else c @endif');

        $directives = $doc->getDirectives();

        expect($directives)->toHaveCount(0);
    });

    it('excludes nested block component directives', function (): void {
        $doc = $this->parse('@if(true) @foreach($items as $item) {{ $item }} @endforeach @endif');

        $directives = $doc->getDirectives();

        expect($directives)->toHaveCount(0);
    });

    it('returns standalone directives mixed with blocks', function (): void {
        $doc = $this->parse('@include("header") @if(true) content @endif @extends("layout")');

        $directives = $doc->getDirectives();

        expect($directives)->toHaveCount(2);

        $names = $directives->map(fn ($d) => $d->nameText())->all();
        expect($names)->toContain('include')
            ->and($names)->toContain('extends')
            ->and($names)->not->toContain('if')
            ->and($names)->not->toContain('endif');
    });

    it('findDirectiveByName excludes block components', function (): void {
        $doc = $this->parse('@if(true) @endif @include("partial")');

        expect($doc->findDirectiveByName('if'))->toBeNull()
            ->and($doc->findDirectiveByName('endif'))->toBeNull()
            ->and($doc->findDirectiveByName('include'))->not->toBeNull();
    });
});
