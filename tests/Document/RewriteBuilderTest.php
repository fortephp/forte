<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Rewriting\RewriteBuilder;

describe('Document::rewrite()', function (): void {
    describe('basic usage', function (): void {
        it('returns a new document instance', function (): void {
            $original = $this->parse('<div>content</div>');

            $new = $original->rewrite(fn (RewriteBuilder $b) => $b->find('div')->addClass('test'));

            expect($new)->toBeInstanceOf(Document::class)
                ->and($new)->not->toBe($original);
        });

        it('does not modify the original document', function (): void {
            $original = $this->parse('<div>content</div>');

            $original->rewrite(fn (RewriteBuilder $b) => $b->find('div')->addClass('test'));

            expect($original->render())->toBe('<div>content</div>');
        });

        it('returns same document when no operations', function (): void {
            $doc = $this->parse('<div>content</div>');

            $result = $doc->rewrite(fn (RewriteBuilder $b) => null);

            expect($result)->toBe($doc);
        });
    });

    describe('find() selection', function (): void {
        it('finds first element by tag name', function (): void {
            $result = $this->parse('<div>first</div><div>second</div>')
                ->rewrite(fn ($b) => $b->find('div')->addClass('found'))
                ->render();

            expect($result)->toBe('<div class="found">first</div><div>second</div>');
        });

        it('finds components by tag name', function (): void {
            $result = $this->parse('<x-alert>message</x-alert>')
                ->rewrite(fn ($b) => $b->find('x-alert')->addClass('component'))
                ->render();

            expect($result)->toBe('<x-alert class="component">message</x-alert>');
        });

        it('handles non-existent elements gracefully', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('span')->addClass('found'))
                ->render();

            expect($result)->toBe('<div>content</div>');
        });
    });

    describe('findAll() selection', function (): void {
        it('finds all elements by tag name', function (): void {
            $result = $this->parse('<div>first</div><div>second</div>')
                ->rewrite(fn ($b) => $b->findAll('div')->addClass('found'))
                ->render();

            expect($result)->toBe('<div class="found">first</div><div class="found">second</div>');
        });

        it('includes both elements and components', function (): void {
            $result = $this->parse('<div>elem</div><x-alert>comp</x-alert>')
                ->rewrite(fn ($b) => $b->findAll('x-alert')->addClass('styled'))
                ->render();

            expect($result)->toBe('<div>elem</div><x-alert class="styled">comp</x-alert>');
        });
    });

    describe('xpath() selection', function (): void {
        it('finds first node by XPath', function (): void {
            $result = $this->parse('<div class="container"><span>text</span></div>')
                ->rewrite(fn ($b) => $b->xpath('//div[@class="container"]')->addClass('found'))
                ->render();

            expect($result)->toBe('<div class="container found"><span>text</span></div>');
        });

        it('finds directive nodes by XPath with forte namespace', function (): void {
            $result = $this->parse('@section("main")<div>content</div>@endsection')
                ->rewrite(fn ($b) => $b->xpath('//forte:section')->insertBefore('<!-- found -->'))
                ->render();

            expect($result)->toContain('<!-- found -->')
                ->and($result)->toContain('@section("main")');
        });
    });

    describe('xpathAll() selection', function (): void {
        it('finds all nodes by XPath', function (): void {
            $result = $this->parse('<input type="text"><input type="password">')
                ->rewrite(fn ($b) => $b->xpathAll('//input')->addClass('form-control'))
                ->render();

            expect($result)->toContain('class="form-control"')
                ->and($result)->toContain('type="text"')
                ->and($result)->toContain('type="password"');
        });
    });
});

describe('Selection mutations', function (): void {
    describe('class operations', function (): void {
        it('adds a class', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')->addClass('active'))
                ->render();

            expect($result)->toBe('<div class="active">content</div>');
        });

        it('adds class to existing classes', function (): void {
            $result = $this->parse('<div class="existing">content</div>')
                ->rewrite(fn ($b) => $b->find('div')->addClass('new'))
                ->render();

            expect($result)->toBe('<div class="existing new">content</div>');
        });

        it('removes a class', function (): void {
            $result = $this->parse('<div class="remove keep">content</div>')
                ->rewrite(fn ($b) => $b->find('div')->removeClass('remove'))
                ->render();

            expect($result)->toBe('<div class="keep">content</div>');
        });

        it('chains add and remove class', function (): void {
            $result = $this->parse('<div class="old">content</div>')
                ->rewrite(fn ($b) => $b->find('div')->removeClass('old')->addClass('new'))
                ->render();

            expect($result)->toBe('<div class="new">content</div>');
        });
    });

    describe('attribute operations', function (): void {
        it('sets an attribute', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')->setAttribute('data-id', '123'))
                ->render();

            expect($result)->toBe('<div data-id="123">content</div>');
        });

        it('overwrites existing attribute', function (): void {
            $result = $this->parse('<div data-id="old">content</div>')
                ->rewrite(fn ($b) => $b->find('div')->setAttribute('data-id', 'new'))
                ->render();

            expect($result)->toBe('<div data-id="new">content</div>');
        });

        it('removes an attribute', function (): void {
            $result = $this->parse('<div data-remove="yes" data-keep="yes">content</div>')
                ->rewrite(fn ($b) => $b->find('div')->removeAttribute('data-remove'))
                ->render();

            expect($result)->toBe('<div data-keep="yes">content</div>');
        });

        it('chains multiple attribute operations', function (): void {
            $result = $this->parse('<div data-old="value">content</div>')
                ->rewrite(fn ($b) => $b->find('div')
                    ->removeAttribute('data-old')
                    ->setAttribute('data-new', 'value'))
                ->render();

            expect($result)->toBe('<div data-new="value">content</div>');
        });
    });

    describe('structural operations', function (): void {
        it('removes a node', function (): void {
            $result = $this->parse('<div>remove</div><span>keep</span>')
                ->rewrite(fn ($b) => $b->find('div')->remove())
                ->render();

            expect($result)->toBe('<span>keep</span>');
        });

        it('replaces a node with content', function (): void {
            $result = $this->parse('<div>old</div>')
                ->rewrite(fn ($b) => $b->find('div')->replaceWith('<span>new</span>'))
                ->render();

            expect($result)->toBe('<span>new</span>');
        });

        it('wraps a node with element', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')->wrapWith('section'))
                ->render();

            expect($result)->toBe('<section><div>content</div></section>');
        });

        it('wraps with attributes', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')->wrapWith('section', ['class' => 'wrapper']))
                ->render();

            expect($result)->toBe('<section class="wrapper"><div>content</div></section>');
        });

        it('inserts content before node', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')->insertBefore('<!-- before -->'))
                ->render();

            expect($result)->toBe('<!-- before --><div>content</div>');
        });

        it('inserts content after node', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')->insertAfter('<!-- after -->'))
                ->render();

            expect($result)->toBe('<div>content</div><!-- after -->');
        });

        it('chains insert before and after', function (): void {
            $result = $this->parse('<div>content</div>')
                ->rewrite(fn ($b) => $b->find('div')
                    ->insertBefore('<!-- before -->')
                    ->insertAfter('<!-- after -->'))
                ->render();

            expect($result)->toBe('<!-- before --><div>content</div><!-- after -->');
        });
    });
});

describe('Selection helpers', function (): void {
    it('iterates with each()', function (): void {
        $count = 0;

        $this->parse('<div>a</div><div>b</div><div>c</div>')
            ->rewrite(function ($b) use (&$count): void {
                $b->findAll('div')->each(function ($node) use (&$count): void {
                    $count++;
                });
            });

        expect($count)->toBe(3);
    });

    it('filters selection', function (): void {
        $result = $this->parse('<div class="a">1</div><div>2</div><div class="b">3</div>')
            ->rewrite(fn ($b) => $b->findAll('div')
                ->filter(fn ($node) => $node->hasAttribute('class'))
                ->addClass('has-class'))
            ->render();

        expect($result)->toBe('<div class="a has-class">1</div><div>2</div><div class="b has-class">3</div>');
    });

    it('gets first node', function (): void {
        $first = null;

        $this->parse('<div>a</div><div>b</div><div>c</div>')
            ->rewrite(function ($b) use (&$first): void {
                $first = $b->findAll('div')->first();
            });

        expect($first?->getDocumentContent())->toBe('<div>a</div>');
    });

    it('counts matched nodes', function (): void {
        $count = 0;

        $this->parse('<div>a</div><div>b</div><div>c</div>')
            ->rewrite(function ($b) use (&$count): void {
                $count = $b->findAll('div')->count();
            });

        expect($count)->toBe(3);
    });
});

describe('multiple operations', function (): void {
    it('chains multiple find calls using separate statements', function (): void {
        $result = $this->parse('<div>content</div><span>more</span>')
            ->rewrite(function ($b): void {
                $b->find('div')->addClass('div-class');
                $b->find('span')->addClass('span-class');
            })
            ->render();

        expect($result)->toBe('<div class="div-class">content</div><span class="span-class">more</span>');
    });

    it('applies operations to multiple nodes', function (): void {
        $result = $this->parse('<div>1</div><div>2</div><div>3</div>')
            ->rewrite(fn ($b) => $b->findAll('div')
                ->addClass('item')
                ->setAttribute('data-type', 'card'))
            ->render();

        expect($result)->toContain('class="item"')
            ->and($result)->toContain('data-type="card"')
            ->and(substr_count($result, 'class="item"'))->toBe(3);
    });

    it('handles complex nested operations', function (): void {
        $result = $this->parse('<div class="container"><p>text</p><span>more</span></div>')
            ->rewrite(function ($b): void {
                $b->find('div')->addClass('processed');
                $b->findAll('p')->addClass('paragraph');
                $b->findAll('span')->wrapWith('strong');
            })
            ->render();

        expect($result)->toContain('class="container processed"')
            ->and($result)->toContain('class="paragraph"')
            ->and($result)->toContain('<strong><span>more</span></strong>');
    });
});

describe('select() and selectAll()', function (): void {
    it('selects a specific node', function (): void {
        $doc = $this->parse('<div>first</div><div>second</div>');
        $elements = $doc->getChildren();

        $result = $doc->rewrite(fn ($b) => $b->select($elements[1])->addClass('selected'))
            ->render();

        expect($result)->toBe('<div>first</div><div class="selected">second</div>');
    });

    it('selects multiple specific nodes', function (): void {
        $doc = $this->parse('<div>a</div><span>b</span><div>c</div>');
        $divs = $doc->findElementsByName('div')->all();

        $result = $doc->rewrite(fn ($b) => $b->selectAll($divs)->addClass('selected'))
            ->render();

        expect($result)->toBe('<div class="selected">a</div><span>b</span><div class="selected">c</div>');
    });
});
