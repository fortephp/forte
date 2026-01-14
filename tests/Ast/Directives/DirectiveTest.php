<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\TextNode;

describe('Directive Parsing', function (): void {
    it('parses simple paired directive (foreach/endforeach)', function (): void {
        $source = '@foreach($items as $item) Hello @endforeach';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $foreach = $children[0]->asDirectiveBlock();

        expect($foreach)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($foreach->nameText())->toBe('foreach')
            ->and($foreach->arguments())->toBe('($items as $item)');

        $foreachDirective = $foreach->childAt(0)->asDirective();
        $endforeachDirective = $foreach->childAt(1)->asDirective();

        expect($foreach->getChildren())->toHaveCount(2)
            ->and($foreachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($foreachDirective->nameText())->toBe('foreach')
            ->and($foreachDirective->getChildren())->toHaveCount(1)
            ->and($foreachDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and($foreachDirective->firstChild()->asText()->getDocumentContent())->toBe(' Hello ')
            ->and($endforeachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endforeachDirective->nameText())->toBe('endforeach')
            ->and($doc->render())->toBe($source);

    });

    it('parses standalone directive', function (): void {
        $source = 'Before @csrf After';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $csrfDirective = $children[1]->asDirective();
        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->asText()->getDocumentContent())->toBe('Before ')
            ->and($csrfDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($csrfDirective->nameText())->toBe('csrf')
            ->and($csrfDirective->arguments())->toBeNull()
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[2]->asText()->getDocumentContent())->toBe(' After')
            ->and($doc->render())->toBe($source);
    });

    it('parses directive with arguments', function (): void {
        $source = '@foreach($items as $item) Hi @endforeach';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $foreach = $children[0]->asDirectiveBlock();
        expect($children)->toHaveCount(1)
            ->and($foreach)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($foreach->nameText())->toBe('foreach')
            ->and($foreach->arguments())->toBe('($items as $item)')
            ->and($doc->render())->toBe($source);
    });

    it('parses nested directives', function (): void {
        $source = '@foreach($a as $b) @foreach($c as $d) Text @endforeach @endforeach';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $outerBlock = $children[0]->asDirectiveBlock();
        expect($children)->toHaveCount(1)
            ->and($outerBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($outerBlock->nameText())->toBe('foreach')
            ->and($outerBlock->getChildren())->toHaveCount(2);

        $outerDirective = $outerBlock->childAt(0)->asDirective();
        expect($outerDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($outerDirective->nameText())->toBe('foreach')
            ->and(count($outerDirective->getChildren()))->toBeGreaterThanOrEqual(1);

        $innerBlock = $outerDirective->firstChildOfType(DirectiveBlockNode::class)->asDirectiveBlock();
        expect($innerBlock)->not->toBeNull()
            ->and($innerBlock->nameText())->toBe('foreach');

        $innerForeachDirective = $innerBlock->childAt(0)->asDirective();
        $innerEndforeachDirective = $innerBlock->childAt(1)->asDirective();

        expect($innerBlock->getChildren())->toHaveCount(2)
            ->and($innerForeachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($innerForeachDirective->nameText())->toBe('foreach')
            ->and($innerEndforeachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($innerEndforeachDirective->nameText())->toBe('endforeach')
            ->and($doc->render())->toBe($source);
    });

    it('auto-closes elements when directive ends', function (): void {
        $source = '@foreach($items as $item)<div><span>Hi @endforeach';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        $foreachBlock = $children[0]->asDirectiveBlock();
        expect($children)->toHaveCount(1)
            ->and($foreachBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($foreachBlock->nameText())->toBe('foreach')
            ->and($doc->render())->toBe($source)
            ->and($foreachBlock->getChildren())->toHaveCount(2);

        $startDirective = $foreachBlock->childAt(0)->asDirective();
        expect($startDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($startDirective->nameText())->toBe('foreach')
            ->and($startDirective->nodes()->whereElementIs('div')->first())->not->toBeNull();
    });

    it('handles directive with condition scope isolation', function (): void {
        $source = <<<'BLADE'
@foreach($things as $thing)
<div>
<span>HI!</span>
@endforeach

<p>Hi!</p>

@foreach($things as $thing)
</div>
@endforeach
BLADE;
        $doc = $this->parse($source);

        $foreachBlocks = $doc->getRootNodes()->whereDirectiveName('foreach')->all();

        expect($foreachBlocks)->toHaveCount(2)
            ->and($doc->render())->toBe($source);
    });

    it('handles multiple sibling directives', function (): void {
        $source = '@if ($a) A @endif Text @if ($b) B @endif';
        $doc = $this->parse($source);

        $ifBlocks = $doc->getRootNodes()->whereDirectiveName('if')->all();

        expect($ifBlocks)->toHaveCount(2)
            ->and($doc->render())->toBe($source);
    });
});
