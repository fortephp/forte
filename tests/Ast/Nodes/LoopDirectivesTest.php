<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Loop Directives Test', function (): void {
    test('foreach statements with continue and break', function (): void {
        $blade = <<<'BLADE'
@foreach($items as $item)
    Start
    @continue($item == 'a')
    Middle
    @break($loop->last)
    End
@endforeach
BLADE;

        $doc = $this->parse($blade, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $foreach = $nodes[0]->asDirectiveBlock();

        expect($foreach->startDirective())->not->toBeNull()
            ->and($foreach->endDirective())->not->toBeNull()
            ->and($foreach->startDirective()->nameText())->toBe('foreach')
            ->and($foreach->startDirective()->arguments())->toBe('($items as $item)')
            ->and($foreach->endDirective()->nameText())->toBe('endforeach');

        $children = $foreach->getChildren();
        expect($children)->toHaveCount(2);

        $startBranch = $children[0]->asDirective();
        expect($startBranch)->toBeInstanceOf(DirectiveNode::class)
            ->and($startBranch->nameText())->toBe('foreach');

        $inner = $startBranch->getChildren();
        $text0 = $inner[0]->asText();
        $continueDirective = $inner[1]->asDirective();
        $text2 = $inner[2]->asText();
        $breakDirective = $inner[3]->asDirective();
        $text4 = $inner[4]->asText();
        $endforeach = $children[1]->asDirective();

        expect($inner)->toHaveCount(5)
            ->and($text0)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text0->getContent()))->toBe('Start')
            ->and($continueDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($continueDirective->nameText())->toBe('continue')
            ->and($continueDirective->arguments())->toBe("(\$item == 'a')")
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text2->getContent()))->toBe('Middle')
            ->and($breakDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($breakDirective->nameText())->toBe('break')
            ->and($breakDirective->arguments())->toBe('($loop->last)')
            ->and($text4)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text4->getContent()))->toBe('End')
            ->and($endforeach)->toBeInstanceOf(DirectiveNode::class)
            ->and($endforeach->nameText())->toBe('endforeach')
            ->and($doc->render())->toBe($blade);
    });

    test('forelse without empty branch parses with just opening and closing children', function (): void {
        $blade = <<<'BLADE'
@forelse($items as $item)
    <span>{{ $item }}</span>
@endforelse
BLADE;

        $doc = $this->parse($blade, ParserOptions::make()->acceptAllDirectives());
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $forElse = $nodes[0]->asDirectiveBlock();

        expect($forElse->startDirective())->not->toBeNull()
            ->and($forElse->endDirective())->not->toBeNull()
            ->and($forElse->startDirective()->nameText())->toBe('forelse')
            ->and($forElse->startDirective()->arguments())->toBe('($items as $item)')
            ->and($forElse->endDirective()->nameText())->toBe('endforelse');

        $children = $forElse->getChildren();
        $forelseDirective = $children[0]->asDirective();
        $endforelseDirective = $children[1]->asDirective();

        expect($children)->toHaveCount(2)
            ->and($forelseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($forelseDirective->nameText())->toBe('forelse')
            ->and($endforelseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endforelseDirective->nameText())->toBe('endforelse');

        $inner = $forelseDirective->getChildren();
        $spanElement = $inner[1]->asElement();
        expect($inner)->toHaveCount(3)
            ->and($inner[0])->toBeInstanceOf(TextNode::class)
            ->and($spanElement)->toBeInstanceOf(ElementNode::class)
            ->and($inner[2])->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($blade);
    });
});
