<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;
use Illuminate\Support\Collection;

describe('Node Collections', function (): void {
    it('can filter by type', function (): void {
        $doc = $this->parse('<div></div> {{ $echo }} text');

        $collection = $doc->getRootNodes();

        $elements = $collection->ofType(ElementNode::class);
        $echoes = $collection->ofType(EchoNode::class);
        $texts = $collection->ofType(TextNode::class);

        expect($elements)->toHaveCount(1)
            ->and($echoes)->toHaveCount(1)
            ->and($texts)->toHaveCount(2);
    });

    it('can filter nodes on specific line', function (): void {
        $doc = $this->parse("{{ \$line1 }}\n{{ \$line2 }}\n{{ \$line3 }}");

        $collection = $doc->getRootNodes();

        $line2 = $collection->onLine(2)->ofType(EchoNode::class);

        expect($line2)->toHaveCount(1);
    });

    it('can filter nodes starting on line', function (): void {
        $doc = $this->parse("{{ \$line1 }}\n{{ \$line2 }}\n{{ \$line3 }}");

        $collection = $doc->getRootNodes();

        $startingLine2 = $collection->startingOnLine(2)->ofType(EchoNode::class);

        expect($startingLine2)->toHaveCount(1);
    });

    it('can filter nodes ending on line', function (): void {
        $doc = $this->parse("{{ \$line1 }}\n{{ \$line2 }}\n{{ \$line3 }}");

        $collection = $doc->getRootNodes();

        $endingLine3 = $collection->endingOnLine(3)->ofType(EchoNode::class);

        expect($endingLine3)->toHaveCount(1);
    });

    it('can filter nodes containing offset', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $collection = $doc->getRootNodes();

        $atOffset6 = $collection->containingOffset(6);

        expect($atOffset6)->toHaveCount(1)
            ->and($atOffset6->first())->toBeInstanceOf(EchoNode::class);
    });

    it('can filter nodes between offsets', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $collection = $doc->getRootNodes();

        $between = $collection->betweenOffsets(0, 7);

        expect($between)->toHaveCount(1);
    });

    it('can filter root nodes', function (): void {
        $doc = $this->parse('<div><span></span></div>');

        $all = NodeCollection::make($doc->getRootNodes()->first()->getDescendants());

        $roots = $all->roots();

        expect($roots)->toHaveCount(0);
    });

    it('can filter leaf nodes', function (): void {
        $doc = $this->parse('<div>text</div>');

        $element = $doc->getRootNodes()->first();
        $all = NodeCollection::make($element->getDescendants());

        $leaves = $all->leaves();

        expect($leaves)->toHaveCount(1);
    });

    it('can filter nodes with children', function (): void {
        $doc = $this->parse('<div><span></span></div>');

        $collection = $doc->getRootNodes();

        $withChildren = $collection->withChildren();

        expect($withChildren)->toHaveCount(1);
    });

    it('can filter directives by name', function (): void {
        $doc = $this->parse('@if(true) @endif @foreach($items as $item) @endforeach', ParserOptions::make()->acceptAllDirectives());

        $collection = $doc->getRootNodes();

        $ifs = $collection->whereDirectiveName('if');

        expect($ifs)->toHaveCount(1)
            ->and($ifs->first())->toBeInstanceOf(DirectiveBlockNode::class);
    });

    it('can render all nodes', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $rendered = $doc->getRootNodes()->render();

        expect($rendered)->toBe('Hello {{ $world }}');
    });

    it('extends Laravel Collection', function (): void {
        $doc = $this->parse('<div></div> {{ $echo }}');

        $collection = $doc->getRootNodes();

        expect($collection->count())->toBe(3)
            ->and($collection->first())->toBeInstanceOf(ElementNode::class)
            ->and($collection->map(fn ($n) => $n::class))->toBeInstanceOf(Collection::class);
    });

    it('has type-specific shortcuts', function (): void {
        $doc = $this->parse('<div></div> {{ $echo }} text');

        $collection = $doc->getRootNodes();

        expect($collection->elements())->toHaveCount(1)
            ->and($collection->echoes())->toHaveCount(1)
            ->and($collection->text())->toHaveCount(2);
    });
});
