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

    test('removeAttribute preserves bound sibling attributes', function (): void {
        $html = '<div data-x="1" :class="$cls">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('data-x');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->not->toContain('data-x')
            ->and($result)->toContain(':class="$cls"');
    });

    test('removeAttribute preserves escaped sibling attributes', function (): void {
        $html = '<div data-x="1" ::class="raw">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('data-x');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->not->toContain('data-x')
            ->and($result)->toContain('::class="raw"');
    });

    test('setAttribute preserves bound sibling attributes', function (): void {
        $html = '<div id="old" :class="$cls">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->setAttribute('id', 'new');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toContain('id="new"')
            ->and($result)->toContain(':class="$cls"');
    });

    test('setAttribute can add a bound attribute', function (): void {
        $html = '<div>content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->setAttribute(':class', '$expr');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div :class="$expr">content</div>');
    });

    test('setAttribute can add an escaped attribute', function (): void {
        $html = '<div>content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->setAttribute('::class', 'raw');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div ::class="raw">content</div>');
    });

    it('can change attribute from bound to escaped', function (): void {
        $html = '<div :class="$expr">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->setAttribute('::class', 'raw');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div ::class="raw">content</div>');
    });

    it('can change attribute from escaped to bound', function (): void {
        $html = '<div ::class="raw">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->setAttribute(':class', '$expr');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div :class="$expr">content</div>');
    });

    it('can change attribute from static to bound', function (): void {
        $html = '<div class="foo">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->setAttribute(':class', '$expr');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div :class="$expr">content</div>');
    });

    test('setAttribute on bound attribute uses the setAttribute key as output name', function (): void {
        $html = '<div :class="$expr">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->setAttribute('class', 'foo');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div class="foo">content</div>');
    });

    it('can rename a bound attribute', function (): void {
        $html = '<div :class="$expr">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->setAttribute(':style', '$styles');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div :style="$styles">content</div>');
    });

    test('can rename an escaped attribute', function (): void {
        $html = '<div ::class="raw">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('class')
                        ->setAttribute('::style', 'raw');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toBe('<div ::style="raw">content</div>');
    });

    test('renaming static attribute preserves bound sibling', function (): void {
        $html = '<div id="old" :class="$cls">content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->removeAttribute('id')
                        ->setAttribute('data-id', 'new');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toContain('data-id="new"')
            ->and($result)->toContain(':class="$cls"')
            ->and($result)->not->toContain('id="old"');
    });

    test('renameTag preserves all prefix types', function (): void {
        $html = '<div :class="$cls" ::style="raw" disabled>content</div>';
        $doc = Document::parse($html);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $node = $path->node();
                if ($node instanceof ElementNode && $node->tagNameText() === 'div') {
                    $path->renameTag('section');
                }
            }
        });

        $result = $rewriter->rewrite($doc)->render();

        expect($result)->toContain('<section')
            ->and($result)->toContain(':class="$cls"')
            ->and($result)->toContain('::style="raw"')
            ->and($result)->toContain('disabled')
            ->and($result)->toContain('</section>');
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
