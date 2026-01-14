<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\TextNode;

describe('Section Directive', function (): void {
    test('unpaired section directive without infinite loop', function (): void {
        $input = "@section('content')";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[0]->asDirective()->nameText())->toBe('section')
            ->and($children[0]->asDirective()->arguments())->toBe("('content')")
            ->and($doc->render())->toBe($input);
    });

    test('properly paired section with endsection', function (): void {
        $input = "@section('content') Hello World @endsection";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endsection')
            ->and(count($block->getChildren()))->toBeGreaterThan(1)
            ->and($doc->render())->toBe($input);
    });

    test('section with show terminator', function (): void {
        $input = "@section('sidebar') Sidebar content @show";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('show')
            ->and($doc->render())->toBe($input);
    });

    test('section with append terminator', function (): void {
        $input = "@section('scripts') <script>alert('hi')</script> @append";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('append')
            ->and($doc->render())->toBe($input);
    });

    test('section with overwrite terminator', function (): void {
        $input = "@section('footer') Footer @overwrite";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('overwrite')
            ->and($doc->render())->toBe($input);
    });

    test('section with stop terminator', function (): void {
        $input = "@section('title') Page Title @stop";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('section')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('stop')
            ->and($doc->render())->toBe($input);
    });

    test('section with multiple arguments as simple directive', function (): void {
        $input = "@section('content', 'Default Value')";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);
        $directive = $children[0]->asDirective();

        expect($directive)->toBeInstanceOf(DirectiveNode::class)
            ->and($directive->nameText())->toBe('section');

        $args = $directive->arguments();
        expect($args)->toContain("'content'")
            ->and($args)->toContain("'Default Value'")
            ->and($doc->render())->toBe($input);
    });

    test('nested content with blade expressions', function (): void {
        $input = "@section('content') Hello {{ \$name }} @endsection";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        $startDirective = $block->startDirective();

        expect($startDirective->getChildren())->toHaveCount(3)
            ->and($doc->render())->toBe($input);
    });

    test('multiple unpaired sections without infinite loop', function (): void {
        $input = "@section('header') @section('footer')";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[0]->nameText())->toBe('section')
            ->and($children[1])->toBeInstanceOf(TextNode::class)
            ->and($children[2])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[2]->asDirective()->nameText())->toBe('section')
            ->and($doc->render())->toBe($input);
    });

    test('section without arguments', function (): void {
        $input = '@section';

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[0]->asDirective()->nameText())->toBe('section')
            ->and($children[0]->asDirective()->hasArguments())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    test('nested sections', function (): void {
        $input = "@section('outer') Outer @section('inner') Inner @endsection More outer @endsection";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $outerBlock = $children[0]->asDirectiveBlock();
        expect($outerBlock->nameText())->toBe('section')
            ->and($outerBlock->endDirective())->not->toBeNull()
            ->and($outerBlock->endDirective()->nameText())->toBe('endsection')
            ->and($doc->render())->toBe($input);
    });
});
