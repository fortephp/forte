<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Attribute Removal', function (): void {
    test('addClass after removeAttribute works correctly', function (): void {
        $html = '<div class="old-class">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->addClass('new-class');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toContain('class="new-class"')
            ->and($result)->not->toContain('old-class');
    });

    test('removeAttribute removes the attribute from output', function (): void {
        $html = '<div class="test" id="myid">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->not->toContain('class=')
            ->and($result)->toContain('id="myid"');
    });

    test('setAttribute modifies attribute in output', function (): void {
        $html = '<div class="old">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->setAttribute('class', 'new');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toContain('class="new"')
            ->and($result)->not->toContain('class="old"');
    });

    test('multiple attribute operations work together', function (): void {
        $html = '<div class="a b" id="test" data-x="1">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->addClass('c')
                        ->removeClass('a')
                        ->removeAttribute('data-x')
                        ->setAttribute('data-y', '2');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toContain('b')
            ->and($result)->toContain('c')
            ->and($result)->not->toContain('class="a')
            ->and($result)->not->toContain('data-x')
            ->and($result)->toContain('data-y="2"')
            ->and($result)->toContain('id="test"');
    });
});
