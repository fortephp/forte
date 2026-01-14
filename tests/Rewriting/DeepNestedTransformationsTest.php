<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\RewritePipeline;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Deeply Nested Transformations', function (): void {
    describe('directives in nested elements', function (): void {
        it('rewrites directives in triple-nested elements', function (): void {
            $blade = '<div><section><article>@json($data)</article></section></div>';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isDirective()) {
                        $directive = $path->asDirective();
                        if ($directive->nameText() === 'json') {
                            $path->replaceWith(Builder::raw('{!! json_encode($data) !!}'));
                        }
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)
                ->toBe('<div><section><article>{!! json_encode($data) !!}</article></section></div>');
        });

        it('rewrites multiple directives at different nesting levels', function (): void {
            $blade = '<div>@if($a)<span>@if($b)inner@endif</span>@endif</div>';
            $doc = $this->parse($blade);

            $transformed = [];
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($transformed) extends Visitor
            {
                public function __construct(private array &$transformed) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $block = $path->asDirectiveBlock();
                        if ($block->nameText() === 'if') {
                            $this->transformed[] = $block->arguments();
                        }
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($transformed)->toContain('($a)')
                ->and($transformed)->toContain('($b)');
        });

        it('handles transformations in directive blocks within elements', function (): void {
            $blade = '@if($show)<div class="container"><p>{{ $text }}</p></div>@endif';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->addClass('processed');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('class="container processed"')
                ->and($output)->toContain('@if($show)')
                ->and($output)->toContain('@endif');
        });
    });

    describe('nested element transformations', function (): void {
        it('rewrites all elements at arbitrary depth', function (): void {
            $blade = '<div><section><article><main><p>text</p></main></article></section></div>';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('visited');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect(substr_count($output, 'visited'))
                ->toBe(5);
        });

        it('preserves structure when modifying deeply nested element', function (): void {
            $blade = '<ul><li><a href="#">link</a></li></ul>';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('a')) {
                        $path->setAttribute('class', 'styled-link');
                        $path->setAttribute('target', '_blank');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('<ul>')
                ->and($output)->toContain('<li>')
                ->and($output)->toContain('class="styled-link"')
                ->and($output)->toContain('target="_blank"')
                ->and($output)->toContain('link')
                ->and($output)->toContain('</a>')
                ->and($output)->toContain('</li>')
                ->and($output)->toContain('</ul>');
        });

        it('handles sibling transformations at different levels', function (): void {
            $blade = '<div><span>a</span><span>b</span><span>c</span></div>';
            $doc = $this->parse($blade);

            $index = 0;
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($index) extends Visitor
            {
                public function __construct(private int &$index) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('span')) {
                        $path->setAttribute('data-index', (string) $this->index++);
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('data-index="0"')
                ->and($output)->toContain('data-index="1"')
                ->and($output)->toContain('data-index="2"');
        });
    });

    describe('complex transformation scenarios', function (): void {
        it('wraps content in nested structures', function (): void {
            $blade = '<div><p>Hello {{ $name }}</p></div>';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isEcho()) {
                        $path->wrapWith(Builder::element('strong'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('<strong>{{ $name }}</strong>')
                ->and($output)->toContain('<div>')
                ->and($output)->toContain('<p>');
        });

        it('unwraps elements in nested structures', function (): void {
            $blade = '<div><wrapper><p>content</p></wrapper></div>';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('wrapper')) {
                        $path->unwrap();
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)
                ->toBe('<div><p>content</p></div>');
        });

        it('handles insertBefore/insertAfter in nested context', function (): void {
            $blade = '<div><ul><li>item</li></ul></div>';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('li')) {
                        $path->insertBefore(Builder::text('<!-- before -->'));
                        $path->insertAfter(Builder::text('<!-- after -->'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain(
                '<!-- before --><li>',
                '</li><!-- after -->'
            );
        });
    });

    describe('directive block transformations', function (): void {
        it('rewrites content inside @if blocks', function (): void {
            $blade = '@if($condition)<div class="old">content</div>@endif';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->removeClass('old');
                        $path->addClass('new');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain('class="new"')
                ->and($output)->not->toContain('class="old"');
        });

        it('rewrites nested @foreach items', function (): void {
            $blade = '@foreach($items as $item)<div>{{ $item }}</div>@endforeach';
            $doc = $this->parse($blade);

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->addClass('list-item');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain(
                'class="list-item"',
                '@foreach',
                '@endforeach'
            );
        });

        it('handles deeply nested directive blocks', function (): void {
            $blade = '@if($a)@if($b)@if($c)deep@endif@endif@endif';
            $doc = $this->parse($blade);

            $depth = 0;
            $maxDepth = 0;
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($depth, $maxDepth) extends Visitor
            {
                public function __construct(private int &$depth, private int &$maxDepth) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $this->depth++;
                        $this->maxDepth = max($this->maxDepth, $this->depth);
                    }
                }

                public function leave(NodePath $path): void
                {
                    if ($path->isDirectiveBlock()) {
                        $this->depth--;
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($maxDepth)->toBe(3);
        });
    });

    describe('multi-pass transformations', function (): void {
        it('applies transformations in sequence across passes', function (): void {
            $blade = '<div class="container"><p>text</p></div>';
            $doc = $this->parse($blade);

            // First pass: add 'processed' class to all elements
            $pass1 = new Rewriter;
            $pass1->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $path->addClass('pass1');
                    }
                }
            });

            // Second pass: rename divs to sections
            $pass2 = new Rewriter;
            $pass2->addVisitor(new class extends Visitor
            {
                public function enter(NodePath $path): void
                {
                    if ($path->isElement() && $path->isTag('div')) {
                        $path->renameTag('section');
                    }
                }
            });

            $pipeline = new RewritePipeline($pass1, $pass2);
            $result = $pipeline->rewrite($doc);
            $output = $result->render();

            expect($output)->toContain(
                '<section',
                'pass1',
                'container'
            );
        });

        it('tracks ancestry correctly across nested structures', function (): void {
            $blade = '<div id="root"><section><article><p>deep</p></article></section></div>';
            $doc = $this->parse($blade);

            $ancestorCounts = [];
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($ancestorCounts) extends Visitor
            {
                public function __construct(private array &$ancestorCounts) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $element = $path->asElement();
                        $tagName = $element->tagNameText();
                        $this->ancestorCounts[$tagName] = count($path->ancestors());
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($ancestorCounts['div'])->toBe(0)
                ->and($ancestorCounts['section'])->toBe(1)
                ->and($ancestorCounts['article'])->toBe(2)
                ->and($ancestorCounts['p'])->toBe(3);
        });
    });
});

describe('Node Movement and Reordering', function (): void {
    it('removes nodes from nested structure', function (): void {
        $blade = '<div><span>keep</span><span class="remove">remove</span><span>keep</span></div>';
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement() && $path->isTag('span')) {
                    $element = $path->asElement();
                    if ($element->getAttribute('class') === 'remove') {
                        $path->remove();
                    }
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect(substr_count($output, '<span>'))->toBe(2)
            ->and($output)->not->toContain('remove');
    });

    it('replaces nodes while preserving siblings', function (): void {
        $blade = '<ul><li>first</li><li class="replace">second</li><li>third</li></ul>';
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement() && $path->isTag('li')) {
                    $element = $path->asElement();
                    if ($element->getAttribute('class') === 'replace') {
                        $path->replaceWith(Builder::element('li')->text('REPLACED'));
                    }
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toContain(
            'first',
            'REPLACED',
            'third',
        )
            ->and($output)->not->toContain('second');
    });

    it('replaces with multiple nodes', function (): void {
        $blade = '<div><placeholder /></div>';
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement() && $path->isTag('placeholder')) {
                    $path->replaceWith([
                        Builder::element('span')->text('one'),
                        Builder::element('span')->text('two'),
                        Builder::element('span')->text('three'),
                    ]);
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)
            ->toContain(
                '<span>one</span>',
                '<span>two</span>',
                '<span>three</span>',
            )
            ->and($output)->not->toContain('placeholder');
    });

    it('moves content via remove and insert', function (): void {
        $blade = '<div><header>title</header><main>content</main></div>';
        $doc = $this->parse($blade);

        $headerContent = null;
        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($headerContent) extends Visitor
        {
            public function __construct(private ?string &$headerContent) {}

            public function enter(NodePath $path): void
            {
                if ($path->isElement() && $path->isTag('header')) {
                    $element = $path->asElement();
                    $children = [];
                    foreach ($element->children() as $child) {
                        $children[] = $child->render();
                    }
                    $this->headerContent = implode('', $children);
                    $path->remove();
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->not->toContain('<header>')
            ->and($output)->toContain('<main>content</main>')
            ->and($headerContent)->toBe('title');
    });

    it('reorders children via replaceChildren', function (): void {
        $blade = '<div><first /><second /><third /></div>';
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement() && $path->isTag('div')) {
                    $path->replaceChildren([
                        Builder::element('third')->selfClosing(),
                        Builder::element('first')->selfClosing(),
                        Builder::element('second')->selfClosing(),
                    ]);
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        $thirdPos = strpos($output, '<third');
        $firstPos = strpos($output, '<first');
        $secondPos = strpos($output, '<second');

        expect($thirdPos)->toBeLessThan($firstPos)
            ->and($firstPos)->toBeLessThan($secondPos);
    });
});

describe('Edge Cases', function (): void {
    it('handles empty elements', function (): void {
        $blade = '<div></div>';
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement()) {
                    $path->addClass('empty');
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toContain('class="empty"');
    });

    it('handles self-closing elements', function (): void {
        $blade = '<input type="text" />';
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement()) {
                    $path->setAttribute('class', 'styled');
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toContain('class="styled"', 'type="text"');
    });

    it('handles void elements', function (): void {
        $blade = '<br><hr>';
        $doc = $this->parse($blade);

        $elements = [];
        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($elements) extends Visitor
        {
            public function __construct(private array &$elements) {}

            public function enter(NodePath $path): void
            {
                if ($path->isElement()) {
                    $this->elements[] = $path->asElement()->tagNameText();
                }
            }
        });

        $rewriter->rewrite($doc);

        expect($elements)->toBe(['br', 'hr']);
    });

    it('handles mixed content correctly', function (): void {
        $blade = '<p>Text <strong>bold</strong> and {{ $var }} more</p>';
        $doc = $this->parse($blade);

        $nodeTypes = [];
        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($nodeTypes) extends Visitor
        {
            public function __construct(private array &$nodeTypes) {}

            public function enter(NodePath $path): void
            {
                $node = $path->node();
                $type = basename(str_replace('\\', '/', $node::class));
                $this->nodeTypes[] = $type;
            }
        });

        $rewriter->rewrite($doc);

        expect($nodeTypes)
            ->toContain(
                'ElementNode',
                'TextNode',
                'EchoNode',
            );
    });

    it('preserves whitespace in nested structures', function (): void {
        $blade = "<div>\n    <p>\n        text\n    </p>\n</div>";
        $doc = $this->parse($blade);

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isElement() && $path->isTag('p')) {
                    $path->addClass('styled');
                }
            }
        });

        $result = $rewriter->rewrite($doc);
        $output = $result->render();

        expect($output)->toContain("\n", 'class="styled"');
    });
});
