<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\TextNode;

describe('Condition Directives', function (): void {
    test('conditions are paired', function (): void {
        $blade = <<<'BLADE'
@if (count($records) === 1)
    I have one record!
@elseif (count($records) > 1)
    I have multiple records!
@else
    I don't have any records!
@endif
BLADE;

        $doc = $this->parse($blade);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $if = $nodes[0]->asDirectiveBlock();

        expect($if->startDirective())->not()->toBeNull()
            ->and($if->endDirective())->not()->toBeNull();

        $children = $if->getChildren();
        expect($children)->toHaveCount(4);

        $startIf = $children[0]->asDirective();
        expect($startIf)->toBeInstanceOf(DirectiveNode::class)
            ->and($startIf->nameText())->toBe('if')
            ->and($startIf->arguments())->toBe('(count($records) === 1)')
            ->and($startIf->getChildren())->toHaveCount(1)
            ->and($startIf->getChildren()[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $startIf->getChildren()[0]->getContent()))->toContain('I have one record!');

        $elseif = $children[1]->asDirective();
        expect($elseif)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseif->nameText())->toBe('elseif')
            ->and($elseif->arguments())->toBe('(count($records) > 1)')
            ->and($elseif->getChildren())->toHaveCount(1)
            ->and($elseif->getChildren()[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $elseif->getChildren()[0]->getContent()))->toContain('I have multiple records!');

        $else = $children[2]->asDirective();
        expect($else)->toBeInstanceOf(DirectiveNode::class)
            ->and($else->nameText())->toBe('else')
            ->and($else->arguments())->toBeNull()
            ->and($else->getChildren())->toHaveCount(1)
            ->and($else->getChildren()[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $else->getChildren()[0]->getContent()))->toContain("I don't have any records!");

        $endIf = $children[3]->asDirective();
        expect($endIf)->toBeInstanceOf(DirectiveNode::class)
            ->and($endIf->nameText())->toBe('endif')
            ->and($endIf->arguments())->toBeNull()
            ->and($endIf->getChildren())->toHaveCount(0);
    });

    test('custom conditions are parsed with acceptAll mode', function (): void {
        $template = <<<'BLADE'
@disk('local')
    <!-- The application is using the local disk... -->
@elsedisk('s3')
    <!-- The application is using the s3 disk... -->
@else
    <!-- The application is using some other disk... -->
@enddisk

@unlessdisk('local')
    <!-- The application is not using the local disk... -->
@enddisk
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->getChildren())->toHaveCount(4)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2])->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[2]->getChildren())->toHaveCount(2);

        $diskNodes = $nodes[0]->getChildren();

        expect($diskNodes[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($diskNodes[0]->asDirective()->nameText())->toBe('disk')
            ->and($diskNodes[0]->asDirective()->arguments())->toBe("('local')")
            ->and($diskNodes[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($diskNodes[1]->asDirective()->nameText())->toBe('elsedisk')
            ->and($diskNodes[1]->asDirective()->arguments())->toBe("('s3')")
            ->and($diskNodes[2]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($diskNodes[2]->asDirective()->nameText())->toBe('else')
            ->and($diskNodes[2]->asDirective()->arguments())->toBeNull()
            ->and($diskNodes[3]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($diskNodes[3]->asDirective()->nameText())->toBe('enddisk')
            ->and($diskNodes[3]->asDirective()->arguments())->toBeNull()
            ->and($diskNodes[3]->asDirective()->getChildren())->toHaveCount(0);

        $disk = $diskNodes[0]->getChildren();

        expect($disk[0])->toBeInstanceOf(TextNode::class)
            ->and($disk[1])->toBeInstanceOf(CommentNode::class);

        $elseDisk = $diskNodes[1]->getChildren();

        expect($elseDisk[0])->toBeInstanceOf(TextNode::class)
            ->and($elseDisk[1])->toBeInstanceOf(CommentNode::class);

        $else = $diskNodes[2]->getChildren();

        expect($else[0])->toBeInstanceOf(TextNode::class)
            ->and($else[1])->toBeInstanceOf(CommentNode::class);

        $unlessDiskNodes = $nodes[2]->getChildren();

        expect($unlessDiskNodes)->toHaveCount(2)
            ->and($unlessDiskNodes[0]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessDiskNodes[0]->asDirective()->nameText())->toBe('unlessdisk')
            ->and($unlessDiskNodes[0]->asDirective()->arguments())->toBe("('local')")
            ->and($unlessDiskNodes[1]->asDirective())->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessDiskNodes[1]->asDirective()->nameText())->toBe('enddisk')
            ->and($unlessDiskNodes[1]->asDirective()->arguments())->toBeNull();

        $unlessDiskNodes = $unlessDiskNodes[0]->getChildren();

        expect($unlessDiskNodes)->toHaveCount(3)
            ->and($unlessDiskNodes[0])->toBeInstanceOf(TextNode::class)
            ->and($unlessDiskNodes[1])->toBeInstanceOf(CommentNode::class)
            ->and($unlessDiskNodes[2])->toBeInstanceOf(TextNode::class);
    });

    test('conditions can be mixed', function (): void {
        $template = <<<'BLADE'
@if (count($records) === 1)
    I have one record!
@elsecan ('update', [$post])
    I have multiple records!
@else
    I don't have any records!
@endunless
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $if = $nodes[0]->asDirectiveBlock();

        expect($if->startDirective())->not()->toBeNull()
            ->and($if->endDirective())->not()->toBeNull();

        $children = $if->getChildren();
        expect($children)->toHaveCount(4);

        $startIf = $children[0]->asDirective();
        expect($startIf)->toBeInstanceOf(DirectiveNode::class)
            ->and($startIf->nameText())->toBe('if')
            ->and($startIf->arguments())->toBe('(count($records) === 1)')
            ->and($startIf->getChildren())->toHaveCount(1)
            ->and($startIf->getChildren()[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $startIf->getChildren()[0]->getContent()))->toContain('I have one record!');

        $elseCan = $children[1]->asDirective();
        expect($elseCan)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseCan->nameText())->toBe('elsecan')
            ->and($elseCan->arguments())->toBe("('update', [\$post])")
            ->and($elseCan->getChildren())->toHaveCount(1)
            ->and($elseCan->getChildren()[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $elseCan->getChildren()[0]->getContent()))->toContain('I have multiple records!');

        $else = $children[2]->asDirective();
        expect($else)->toBeInstanceOf(DirectiveNode::class)
            ->and($else->nameText())->toBe('else')
            ->and($else->arguments())->toBeNull()
            ->and($else->getChildren())->toHaveCount(1)
            ->and($else->getChildren()[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $else->getChildren()[0]->getContent()))->toContain("I don't have any records!");

        $endUnless = $children[3]->asDirective();
        expect($endUnless)->toBeInstanceOf(DirectiveNode::class)
            ->and($endUnless->nameText())->toBe('endunless')
            ->and($endUnless->arguments())->toBeNull()
            ->and($endUnless->getChildren())->toHaveCount(0);
    });
});
