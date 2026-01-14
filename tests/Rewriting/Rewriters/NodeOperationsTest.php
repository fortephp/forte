<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('NodePath Sibling Access', function (): void {
    it('nextSibling returns correct node', function (): void {
        $doc = $this->parse('<div>a</div><span>b</span>');
        $nextTag = null;

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($nextTag) extends Visitor
        {
            public function __construct(private ?string &$nextTag) {}

            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $next = $path->nextSibling();
                    if ($next instanceof ElementNode) {
                        $this->nextTag = $next->tagNameText();
                    }
                }
            }
        });

        $rewriter->rewrite($doc);

        expect($nextTag)->toBe('span');
    });

    it('previousSibling returns correct node', function (): void {
        $doc = $this->parse('<div>a</div><span>b</span>');
        $prevTag = null;

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($prevTag) extends Visitor
        {
            public function __construct(private ?string &$prevTag) {}

            public function enter(NodePath $path): void
            {
                if ($path->isTag('span')) {
                    $prev = $path->previousSibling();
                    if ($prev instanceof ElementNode) {
                        $this->prevTag = $prev->tagNameText();
                    }
                }
            }
        });

        $rewriter->rewrite($doc);

        expect($prevTag)->toBe('div');
    });

    it('nextSibling returns null for last sibling', function (): void {
        $doc = $this->parse('<div>a</div><span>b</span>');
        $nextExists = true;

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class($nextExists) extends Visitor
        {
            public function __construct(private bool &$nextExists) {}

            public function enter(NodePath $path): void
            {
                if ($path->isTag('span')) {
                    $this->nextExists = $path->nextSibling() !== null;
                }
            }
        });

        $rewriter->rewrite($doc);

        expect($nextExists)->toBe(false);
    });
});

describe('Wrap Operations', function (): void {
    it('wraps element preserving content', function (): void {
        $doc = $this->parse('<span>content</span>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('span')) {
                    $path->wrapWith(Builder::element('div')->attr('class', 'wrapper'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div class="wrapper"><span>content</span></div>');
    });

    it('wraps text node in element', function (): void {
        $doc = $this->parse('plain text');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isText() && trim($path->node()->getDocumentContent()) !== '') {
                    $path->wrapWith(Builder::element('p'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<p>plain text</p>');
    });

    it('wraps nested elements correctly', function (): void {
        $doc = $this->parse('<div><span>inner</span></div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('span')) {
                    $path->wrapWith(Builder::element('strong'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div><strong><span>inner</span></strong></div>');
    });
});

describe('Unwrap Operations', function (): void {
    it('unwraps element exposing children', function (): void {
        $doc = $this->parse('<div><span>content</span></div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->unwrap();
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<span>content</span>');
    });

    it('unwraps exposing multiple children', function (): void {
        $doc = $this->parse('<div><span>a</span><p>b</p></div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->unwrap();
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<span>a</span><p>b</p>');
    });
});

describe('ReplaceChildren Operation', function (): void {
    it('replaces all children of element', function (): void {
        $doc = $this->parse('<div><span>old</span><p>content</p></div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceChildren('new content');
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>new content</div>');
    });

    it('replaces children with multiple specs', function (): void {
        $doc = $this->parse('<div>old</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceChildren([
                        Builder::element('span')->text('first'),
                        Builder::text(' '),
                        Builder::element('span')->text('second'),
                    ]);
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div><span>first</span> <span>second</span></div>');
    });
});

describe('Prepend/Append Children', function (): void {
    it('prepends children to element', function (): void {
        $doc = $this->parse('<div>existing</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->prependChildren(Builder::element('span')->text('prepended'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div><span>prepended</span>existing</div>');
    });

    it('appends children to element', function (): void {
        $doc = $this->parse('<div>existing</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->appendChild(Builder::element('span')->text('appended'));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>existing<span>appended</span></div>');
    });

    it('prepends and appends together', function (): void {
        $doc = $this->parse('<div>middle</div>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->prependChildren('first ');
                    $path->appendChild(' last');
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>first middle last</div>');
    });

    it('transforms Blade @php directive to PHP tag', function (): void {
        $doc = $this->parse('@php($x = 1)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $dir = $path->asDirective();
                if ($dir && $dir->nameText() === 'php' && $dir->hasArguments()) {
                    $args = trim((string) $dir->arguments(), '() ');

                    if (! str_ends_with($args, ';')) {
                        $args .= ';';
                    }

                    $path->replaceWith(Builder::phpTag(' '.$args));
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<?php $x = 1; ?>');
    });

    it('can hoist directive arguments', function (): void {
        $doc = $this->parse('@json($data->items, JSON_PRETTY_PRINT)');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $dir = $path->asDirective();
                if ($dir && $dir->nameText() === 'json') {
                    $args = trim((string) $dir->arguments(), '()');
                    $parts = array_map(trim(...), explode(',', $args, 2));
                    $expr = $parts[0];
                    $flags = $parts[1] ?? '0';
                    $tmpVar = '$__tmp';

                    $path->surroundWith(
                        Builder::phpTag(' '.$tmpVar.' = '.$expr.';'),
                        Builder::directive('json', '('.$tmpVar.', '.$flags.')'),
                        Builder::phpTag(' unset('.$tmpVar.');')
                    );
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toContain('<?php $__tmp = $data->items; ?>')
            ->toContain('@json($__tmp, JSON_PRETTY_PRINT)')
            ->toContain('<?php unset($__tmp); ?>');
    });

    it('can convert x-component to include directive', function (): void {
        $doc = $this->parse('<x-button type="submit">Click</x-button>');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                $elem = $path->asElement();
                if ($elem && str_starts_with($elem->tagNameText(), 'x-')) {
                    $componentName = substr($elem->tagNameText(), 2);

                    // Get slot content (simplified)
                    $slotContent = '';
                    foreach ($elem->children() as $child) {
                        $slotContent .= $child->getDocumentContent();
                    }

                    // Build attribute array
                    $attrs = [];
                    /** @var \Forte\Ast\Elements\Attribute $attr */
                    foreach ($elem->attributes() as $attr) {
                        $attrs[] = "'{$attr->nameText()}' => '{$attr->valueText()}'";
                    }
                    $attrStr = '['.implode(', ', $attrs).']';

                    $path->replaceWith([
                        Builder::directive('include', "('components.{$componentName}', {$attrStr})"),
                    ]);
                }
            }
        });

        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toContain("@include('components.button'");
    });
});
