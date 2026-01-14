<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\TextNode;

describe('Switch Directive', function (): void {
    it('parses switch statement structure', function (): void {
        $blade = <<<'BLADE'
@switch($i)
    @case(1)
        First case...
        @break

    @case(2)
        Second case...
        @break

    @default
        Default case...
@endswitch
BLADE;

        $doc = $this->parse($blade);
        $switchNode = $doc->firstChild()->asDirectiveBlock();

        expect($switchNode)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($switchNode->getChildren())->toHaveCount(2);

        $switch = $switchNode->childAt(0)->asDirective();

        expect($switchNode->nameText())->toBe('switch')
            ->and($switchNode->arguments())->toBe('($i)');

        $endSwitch = $switchNode->childAt(1)->asDirective();
        expect($endSwitch)->toBeInstanceOf(DirectiveNode::class)
            ->and($endSwitch->nameText())->toBe('endswitch')
            ->and($endSwitch->arguments())->toBeNull()
            ->and($switch)->toBeInstanceOf(DirectiveNode::class)
            ->and($switch->nameText())->toBe('switch')
            ->and($switch->arguments())->toBe('($i)');

        $children = $switch->getChildren();
        expect($children)->toHaveCount(4)
            ->and($switch->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and($switch->childAt(1))->toBeInstanceOf(DirectiveNode::class);

        $case1 = $switch->childAt(1)->asDirective();
        expect($case1->nameText())->toBe('case')
            ->and($case1->arguments())->toBe('(1)')
            ->and($case1->getChildren())->toHaveCount(3)
            ->and($case1->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $case1->childAt(0)->getDocumentContent()))->toContain('First case...')
            ->and($case1->childAt(1))->toBeInstanceOf(DirectiveNode::class)
            ->and($case1->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($case1->childAt(1)->asDirective()->arguments())->toBeNull()
            ->and($case1->childAt(1)->getChildren())->toBeEmpty()
            ->and($case1->childAt(2))->toBeInstanceOf(TextNode::class)
            ->and($switch->childAt(2))->toBeInstanceOf(DirectiveNode::class);

        $case2 = $switch->childAt(2)->asDirective();
        expect($case2->nameText())->toBe('case')
            ->and($case2->arguments())->toBe('(2)')
            ->and($case2->getChildren())->toHaveCount(3)
            ->and($case2->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $case2->childAt(0)->getDocumentContent()))->toContain('Second case...')
            ->and($case2->childAt(1))->toBeInstanceOf(DirectiveNode::class)
            ->and($case2->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($case2->childAt(1)->asDirective()->arguments())->toBeNull()
            ->and($switch->childAt(3))->toBeInstanceOf(DirectiveNode::class);

        $default = $switch->childAt(3)->asDirective();
        expect($default->nameText())->toBe('default')
            ->and($default->arguments())->toBeNull()
            ->and($default->getChildren())->toHaveCount(1)
            ->and($default->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $default->childAt(0)->getDocumentContent()))->toBe('Default case...')
            ->and($doc->render())->toBe($blade);
    });

    it('parses nested switch statements', function (): void {
        $blade = <<<'BLADE'
@switch($i)
    @case(1)
        First case...
            @switch($i)
                @case(1)
                    Nested First case...
                    @break

                @case(2)
                    Nested Second case...
                    @break

                @default
                    Nested Default case...

                    @switch($i)
                        @case(1)
                            Nested Two First case...
                            @break

                        @case(2)
                            Nested Two Second case...
                            @break

                        @default
                            Nested Two Default case...
                    @endswitch
            @endswitch
        @break

    @case(2)
        Second case...
        @break

    @default
        Default case...
@endswitch
BLADE;

        $doc = $this->parse($blade);
        $switch = $doc->firstChild()->asDirectiveBlock();

        expect($switch)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($switch->nameText())->toBe('switch')
            ->and($switch->arguments())->toBe('($i)')
            ->and($switch->getChildren())->toHaveCount(2);

        $endSwitch = $switch->childAt(1)->asDirective();
        expect($endSwitch)->toBeInstanceOf(DirectiveNode::class)
            ->and($endSwitch->nameText())->toBe('endswitch')
            ->and($endSwitch->arguments())->toBeNull();

        $switchDirective = $switch->childAt(0);

        expect($switchDirective->childAt(0))->toBeInstanceOf(TextNode::class);

        $case1 = $switchDirective->childAt(1)->asDirective();
        expect($case1)->toBeInstanceOf(DirectiveNode::class)
            ->and($case1->nameText())->toBe('case')
            ->and($case1->arguments())->toBe('(1)')
            ->and($case1->getChildren())->toHaveCount(5)
            ->and($case1->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $case1->childAt(0)->getDocumentContent()))->toBe('First case...');

        $nested = $case1->childAt(1)->asDirectiveBlock();
        expect($nested)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nested->nameText())->toBe('switch')
            ->and($nested->arguments())->toBe('($i)')
            ->and($nested->getChildren())->toHaveCount(2)
            ->and($nested->childAt(1))->toBeInstanceOf(DirectiveNode::class)
            ->and($nested->childAt(1)->asDirective()->nameText())->toBe('endswitch')
            ->and($nested->childAt(1)->asDirective()->arguments())->toBeNull();

        $nestedDirective = $nested->childAt(0);

        expect($nestedDirective->childAt(0))->toBeInstanceOf(TextNode::class);

        $n1 = $nestedDirective->childAt(1)->asDirective();
        expect($n1)->toBeInstanceOf(DirectiveNode::class)
            ->and($n1->nameText())->toBe('case')
            ->and($n1->arguments())->toBe('(1)')
            ->and($n1->getChildren())->toHaveCount(3)
            ->and($n1->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $n1->childAt(0)->getDocumentContent()))->toContain('Nested First case...')
            ->and($n1->childAt(1)->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($n1->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($n1->childAt(2))->toBeInstanceOf(TextNode::class);

        $n2 = $nestedDirective->childAt(2)->asDirective();
        expect($n2->nameText())->toBe('case')
            ->and($n2->arguments())->toBe('(2)')
            ->and($n2->getChildren())->toHaveCount(3)
            ->and(trim((string) $n2->childAt(0)->getDocumentContent()))->toContain('Nested Second case...')
            ->and($n2->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($n2->childAt(2))->toBeInstanceOf(TextNode::class);

        $ndef = $nestedDirective->childAt(3)->asDirective();
        expect($ndef->nameText())->toBe('default')
            ->and($ndef->getChildren())->toHaveCount(3)
            ->and(trim((string) $ndef->childAt(0)->getDocumentContent()))->toContain('Nested Default case...');

        $nestedTwo = $ndef->childAt(1)->asDirectiveBlock();
        expect($nestedTwo)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nestedTwo->nameText())->toBe('switch')
            ->and($nestedTwo->arguments())->toBe('($i)')
            ->and($nestedTwo->getChildren())->toHaveCount(2)
            ->and($nestedTwo->childAt(1))->toBeInstanceOf(DirectiveNode::class)
            ->and($nestedTwo->childAt(1)->asDirective()->nameText())->toBe('endswitch');

        $nestedTwoDirective = $nestedTwo->childAt(0);

        $nt1 = $nestedTwoDirective->childAt(1)->asDirective();
        expect($nt1->nameText())->toBe('case')
            ->and($nt1->arguments())->toBe('(1)')
            ->and($nt1->getChildren())->toHaveCount(3)
            ->and(trim((string) $nt1->childAt(0)->getDocumentContent()))->toContain('Nested Two First case...')
            ->and($nt1->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($nt1->childAt(2))->toBeInstanceOf(TextNode::class);

        $nt2 = $nestedTwoDirective->childAt(2)->asDirective();
        expect($nt2->nameText())->toBe('case')
            ->and($nt2->arguments())->toBe('(2)')
            ->and($nt2->getChildren())->toHaveCount(3)
            ->and(trim((string) $nt2->childAt(0)->getDocumentContent()))->toContain('Nested Two Second case...')
            ->and($nt2->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($nt2->childAt(2))->toBeInstanceOf(TextNode::class);

        $ntdef = $nestedTwoDirective->childAt(3)->asDirective();
        expect($ntdef->nameText())->toBe('default')
            ->and($ntdef->getChildren())->toHaveCount(1)
            ->and(trim((string) $ntdef->childAt(0)->getDocumentContent()))->toContain('Nested Two Default case...')
            ->and($case1->childAt(3)->asDirective()->nameText())->toBe('break')
            ->and($case1->childAt(4))->toBeInstanceOf(TextNode::class);

        $case2 = $switchDirective->childAt(2)->asDirective();
        expect($case2)->toBeInstanceOf(DirectiveNode::class)
            ->and($case2->nameText())->toBe('case')
            ->and($case2->arguments())->toBe('(2)')
            ->and($case2->getChildren())->toHaveCount(3)
            ->and(trim((string) $case2->childAt(0)->getDocumentContent()))->toContain('Second case...')
            ->and($case2->childAt(1)->asDirective()->nameText())->toBe('break')
            ->and($case2->childAt(2))->toBeInstanceOf(TextNode::class);

        $tdef = $switchDirective->childAt(3)->asDirective();
        expect($tdef)->toBeInstanceOf(DirectiveNode::class)
            ->and($tdef->nameText())->toBe('default')
            ->and($tdef->getChildren())->toHaveCount(1)
            ->and(trim((string) $tdef->childAt(0)->getDocumentContent()))->toContain('Default case...')
            ->and($doc->render())->toBe($blade);
    });

    test('document API provides findAll for switch directives', function (): void {
        $blade = <<<'BLADE'
@switch($i)
    @case(1)
        First case...
        @break
    @case(2)
        Second case...
        @break
@endswitch
BLADE;

        $doc = $this->parse($blade);

        $switchBlock = $doc->findBlockDirectiveByName('switch');
        expect($switchBlock)->not->toBeNull()
            ->and($switchBlock->nameText())->toBe('switch');

        $firstCase = null;
        $switchBlock->walk(function ($node) use (&$firstCase): void {
            if ($firstCase === null && $node instanceof DirectiveNode && $node->nameText() === 'case') {
                $firstCase = $node;
            }
        });

        expect($firstCase)->not->toBeNull()
            ->and($firstCase->arguments())->toBe('(1)');

        $cases = [];
        $switchBlock->walk(function ($node) use (&$cases): void {
            if ($node instanceof DirectiveNode && $node->nameText() === 'case') {
                $cases[] = $node;
            }
        });

        expect($cases)->toHaveCount(2)
            ->and($doc->render())->toBe($blade);
    });

    test('document walk traverses entire switch structure', function (): void {
        $blade = <<<'BLADE'
@switch($i)
    @case(1)
        First case...
        @break
    @case(2)
        Second case...
        @break
    @default
        Default case...
@endswitch
BLADE;

        $doc = $this->parse($blade);

        $nodeTypes = [];
        $doc->firstChild()->walk(function ($node) use (&$nodeTypes): void {
            $nodeTypes[] = $node::class;
        });

        expect($nodeTypes)->toContain(DirectiveBlockNode::class)
            ->and($nodeTypes)->toContain(DirectiveNode::class)
            ->and($nodeTypes)->toContain(TextNode::class);

        $directiveCount = 0;
        $doc->firstChild()->walk(function ($node) use (&$directiveCount): void {
            if ($node instanceof DirectiveNode) {
                $directiveCount++;
            }
        });

        expect($directiveCount)->toBe(7)
            ->and($doc->render())->toBe($blade);
    });

    test('basic switch with single case has structure', function (): void {
        $input = <<<'BLADE'
@switch($value)
    @case(1)
        First case
        @break
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('switch')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endswitch')
            ->and($doc->render())->toBe($input);
    });

    test('handles switch with multiple cases', function (): void {
        $input = <<<'BLADE'
@switch($type)
    @case('admin')
        Admin view
        @break
    @case('user')
        User view
        @break
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('switch')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endswitch')
            ->and($doc->render())->toBe($input);
    });

    test('switch with default case', function (): void {
        $input = <<<'BLADE'
@switch($status)
    @case('active')
        Active
        @break
    @default
        Default value
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('switch')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endswitch')
            ->and($doc->render())->toBe($input);
    });

    test('switch with only default case', function (): void {
        $input = <<<'BLADE'
@switch($value)
    @default
        Default only
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('switch')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endswitch')
            ->and($doc->render())->toBe($input);
    });

    test('switch without any cases', function (): void {
        $input = <<<'BLADE'
@switch($value)
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('switch')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endswitch')
            ->and($doc->render())->toBe($input);
    });

    test('switch with blade expressions in cases', function (): void {
        $input = <<<'BLADE'
@switch($user->role)
    @case('admin')
        Hello {{ $user->name }}
        @break
    @case('guest')
        Welcome, guest!
        @break
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        $startDirective = $block->startDirective();

        expect(count($startDirective->getChildren()))->toBeGreaterThan(0)
            ->and($doc->render())->toBe($input);
    });

    test('switch with nested HTML', function (): void {
        $input = <<<'BLADE'
@switch($layout)
    @case('grid')
        <div class="grid">Content</div>
        @break
    @case('list')
        <ul><li>Item</li></ul>
        @break
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($doc->render())->toBe($input);
    });

    test('handles switch with multiple statements in case', function (): void {
        $input = <<<'BLADE'
@switch($value)
    @case(1)
        First line
        Second line
        {{ $variable }}
        @break
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($doc->render())->toBe($input);
    });

    test('switch with complex expressions', function (): void {
        $input = <<<'BLADE'
@switch(getType($item))
    @case($item->getType())
        Complex case
        @break
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($doc->render())->toBe($input);
    });

    test('switch with all features combined', function (): void {
        $input = <<<'BLADE'
@switch($priority)
    @case('high')
        <span class="badge-red">{{ $title }}</span>
        @break
    @case('medium')
        <span class="badge-yellow">{{ $title }}</span>
        @break
    @case('low')
        <span class="badge-green">{{ $title }}</span>
        @break
    @default
        <span class="badge-gray">{{ $title }}</span>
@endswitch
BLADE;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('switch')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endswitch')
            ->and(count($block->getChildren()))->toBe(2)
            ->and($doc->render())->toBe($input);
    });

    test('switch with nested HTML elements', function (): void {
        $blade = <<<'BLADE'
@switch($layout)
    @case('grid')
        <div class="grid">Content</div>
        @break
    @case('list')
        <ul><li>Item</li></ul>
        @break
@endswitch
BLADE;

        $doc = $this->parse($blade);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($doc->render())->toBe($blade);
    });

    test('leading whitespace between @switch and first @case', function (): void {
        $blade = <<<'BLADE'
@switch($value)
    @case(1)
        First case
        @break
@endswitch
BLADE;

        $doc = $this->parse($blade);
        $children = $doc->getChildren();

        $block = $children[0]->asDirectiveBlock();
        expect($block)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($doc->render())->toBe($blade);
    });

    test('no leading content (case immediately after @switch)', function (): void {
        $blade = <<<'BLADE'
@switch($value)
@case(1)
First case
@break
@endswitch
BLADE;

        $doc = $this->parse($blade);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($doc->render())->toBe($blade);
    });

    test('leading content with nested switch structures', function (): void {
        $blade = <<<'BLADE'
@switch($outer)
    Outer leading text
    @case(1)
        @switch($inner)
            Inner leading text
            @case('a')
                Inner case content
                @break
        @endswitch
        @break
@endswitch
BLADE;

        $doc = $this->parse($blade);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($doc->render())->toBe($blade);
    });
});
