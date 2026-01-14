<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Paired Nodes', function (): void {
    test('simple directives can be paired', function (): void {
        $blade = <<<'BLADE'
@can ('update', [$post])
    Yes they can.
@endcan
BLADE;

        $doc = $this->parse($blade, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockDirective = $nodes[0]->asDirectiveBlock();

        expect($blockDirective->getChildren())->toHaveCount(2);

        $blockChildren = $blockDirective->getChildren();
        expect($blockDirective->startDirective())->toBe($blockChildren[0])
            ->and($blockDirective->endDirective())->toBe($blockChildren[1]);

        $can = $blockDirective->startDirective();
        expect($can)->not()->toBeNull()
            ->and($can->arguments())->toBe("('update', [\$post])")
            ->and($can->nameText())->toBe('can')
            ->and($can->getChildren())->toHaveCount(1);

        $children = $can->getChildren();
        expect($children[0])->toBeInstanceOf(TextNode::class);

        $text = $children[0]->asText();

        expect(trim((string) $text->getContent()))->toBe('Yes they can.');

        $endCan = $blockDirective->endDirective();
        expect($endCan)->not->toBeNull()
            ->and($endCan->nameText())->toBe('endcan')
            ->and($endCan->getChildren())->toHaveCount(0)
            ->and($doc->render())->toBe($blade);
    });

    it('nested directives can be paired', function (): void {
        $blade = <<<'BLADE'
@can ('update', [$post])
    @can ('update', [$post])
        Yes they can.
    @endcan
@endcan
BLADE;

        $doc = $this->parse($blade, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $blockDirective = $nodes[0]->asDirectiveBlock();

        expect($blockDirective->getChildren())->toHaveCount(2);

        $blockChildren = $blockDirective->getChildren();
        expect($blockDirective->startDirective())->toBe($blockChildren[0])
            ->and($blockDirective->endDirective())->toBe($blockChildren[1]);

        $can = $blockDirective->startDirective();
        expect($can)->not->toBeNull()
            ->and($can->arguments())->toBe("('update', [\$post])")
            ->and($can->nameText())->toBe('can')
            ->and($can->getChildren())->toHaveCount(3);

        $children = $can->getChildren();
        expect($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[1])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[2])->toBeInstanceOf(TextNode::class);

        $nestedBlockDirective = $children[1]->asDirectiveBlock();

        expect($nestedBlockDirective->getChildren())->toHaveCount(2);

        $blockChildren = $nestedBlockDirective->getChildren();
        expect($nestedBlockDirective->startDirective())->toBe($blockChildren[0])
            ->and($nestedBlockDirective->endDirective())->toBe($blockChildren[1]);

        $nestedCan = $nestedBlockDirective->startDirective();
        expect($can)->not->toBeNull()
            ->and($nestedCan->arguments())->toBe("('update', [\$post])")
            ->and($nestedCan->nameText())->toBe('can')
            ->and($nestedCan->getChildren())->toHaveCount(1);

        $nestedChildren = $nestedCan->getChildren();
        expect($nestedChildren[0])->toBeInstanceOf(TextNode::class);

        $nestedText = $nestedChildren[0]->asText();

        expect(trim((string) $nestedText->getContent()))->toBe('Yes they can.');

        $nestedEndCan = $nestedBlockDirective->endDirective();
        expect($nestedEndCan)->not()->toBeNull()
            ->and($nestedEndCan->nameText())->toBe('endcan')
            ->and($nestedEndCan->getChildren())->toHaveCount(0);

        $endCan = $blockDirective->endDirective();
        expect($endCan)->not->toBeNull()
            ->and($endCan->nameText())->toBe('endcan')
            ->and($endCan->getChildren())->toHaveCount(0)
            ->and($endCan)->not->toBe($nestedEndCan)
            ->and($can)->not->toBe($nestedCan)
            ->and($doc->render())->toBe($blade);
    });
});
