<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Enclaves\Enclave;
use Forte\Enclaves\EnclavesManager;
use Forte\Rewriting\AstRewriter;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Visitor;

class EnclaveTransformerA extends Visitor {}
class EnclaveTransformerB extends Visitor {}
class EnclaveTransformerC extends Visitor {}

class TestAstRewriter implements AstRewriter
{
    public function __construct(private readonly string $addClass) {}

    public function rewrite(Document $doc): Document
    {
        return $doc->rewrite(fn ($b) => $b->findAll('div')->addClass($this->addClass));
    }
}

describe('Enclave Transformers', function (): void {
    it('can add and remove transformers and order by priority', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(EnclaveTransformerA::class, 10)
            ->addRewriter(EnclaveTransformerB::class, 5)
            ->addRewriter(EnclaveTransformerC::class, 10);

        expect($enclave->getRewritersInPriorityOrder())
            ->toBe([EnclaveTransformerA::class, EnclaveTransformerC::class, EnclaveTransformerB::class]);

        $instances = $enclave->getRewriters();
        expect($instances[0])->toBeInstanceOf(EnclaveTransformerA::class)
            ->and($instances[1])->toBeInstanceOf(EnclaveTransformerC::class)
            ->and($instances[2])->toBeInstanceOf(EnclaveTransformerB::class);

        $enclave->removeRewriter(EnclaveTransformerC::class);
        expect($enclave->getRewritersInPriorityOrder())
            ->toBe([EnclaveTransformerA::class, EnclaveTransformerB::class]);
    });

    it('combines transformers from multiple enclaves for a matching path', function (): void {
        $registry = new EnclavesManager;

        $e1 = $registry->create('E1')
            ->include('**/src/**')
            ->exclude('**/src/**/Generated/**');

        $e2 = $registry->create('E2')
            ->include('**/src/Core/**');

        $e1->addRewriter(EnclaveTransformerA::class, 5)
            ->addRewriter(EnclaveTransformerC::class, 1);

        $e2->addRewriter(EnclaveTransformerB::class, 10)
            ->addRewriter(EnclaveTransformerC::class, 7);

        $path = 'C:/repo/src/Core/File.php';

        $classes = $registry->getRewriterClassesForPath($path);
        expect($classes)->toBe([EnclaveTransformerB::class, EnclaveTransformerC::class, EnclaveTransformerA::class]);

        $instances = $registry->getRewritersForPath($path);
        expect($instances[0])->toBeInstanceOf(EnclaveTransformerB::class)
            ->and($instances[1])->toBeInstanceOf(EnclaveTransformerC::class)
            ->and($instances[2])->toBeInstanceOf(EnclaveTransformerA::class);

        $path2 = 'C:/repo/src/Core/Generated/Thing.php';
        $classes2 = $registry->getRewriterClassesForPath($path2);

        expect($classes2)
            ->toBe([EnclaveTransformerB::class, EnclaveTransformerC::class]);
    });
});

describe('Enclave rewriteWith() method', function (): void {
    it('registers a callback transformer', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $called = false;
        $enclave->transform(function (NodePath $path) use (&$called): void {
            $called = true;
        });

        $doc = Document::parse('<div>content</div>');
        $enclave->transformDocument($doc);

        expect($called)->toBeTrue();
    });

    it('callback receives NodePath and can modify nodes', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $enclave->transform(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->addClass('transformed');
            }
        });

        $doc = Document::parse('<div>content</div>');
        $result = $enclave->transformDocument($doc);

        expect($result->render())->toBe('<div class="transformed">content</div>');
    });

    it('multiple rewriteWith() callbacks execute in registration order', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $order = [];

        $enclave->transform(function (NodePath $path) use (&$order): void {
            if ($path->isTag('div')) {
                $order[] = 'first';
            }
        });

        $enclave->transform(function (NodePath $path) use (&$order): void {
            if ($path->isTag('div')) {
                $order[] = 'second';
            }
        });

        $doc = Document::parse('<div>content</div>');
        $enclave->transformDocument($doc);

        expect($order)->toBe(['first', 'second']);
    });

    it('use() with visitor instance works correctly', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $visitor = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->setAttribute('data-visitor', 'yes');
                }
            }
        };

        $enclave->use($visitor);

        $items = $enclave->getOrderedTransformItems();
        expect($items)->toHaveCount(1)
            ->and($items[0])->toBeInstanceOf(Visitor::class);

        $doc = Document::parse('<div>content</div>');
        $result = $enclave->transformDocument($doc);

        expect($result->render())->toContain('data-visitor="yes"');
    });
});

describe('Enclave apply() method', function (): void {
    it('registers an AstRewriter', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $enclave->apply(new TestAstRewriter('applied'));

        $doc = Document::parse('<div>content</div>');
        $result = $enclave->transformDocument($doc);

        expect($result->render())->toBe('<div class="applied">content</div>');
    });

    it('multiple apply() rewriters execute in registration order', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $first = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                return $doc->rewrite(fn ($b) => $b->findAll('div')->setAttribute('data-first', 'yes'));
            }
        };

        $second = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                return $doc->rewrite(fn ($b) => $b->findAll('div')->setAttribute('data-second', 'yes'));
            }
        };

        $enclave->apply($first, $second);

        $doc = Document::parse('<div>content</div>');
        $result = $enclave->transformDocument($doc);

        expect($result->render())
            ->toContain('data-first="yes"')
            ->toContain('data-second="yes"');
    });

    it('can chain rewriteWith() and apply() in any order', function (): void {
        $enclave = new Enclave;
        $enclave->include('**');

        $enclave
            ->transform(function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->setAttribute('data-first', 'yes');
                }
            })
            ->apply(new TestAstRewriter('applied'))
            ->transform(function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->setAttribute('data-last', 'yes');
                }
            });

        $doc = Document::parse('<div>content</div>');
        $result = $enclave->transformDocument($doc);

        expect($result->render())
            ->toContain('data-first="yes"')
            ->toContain('class="applied"')
            ->toContain('data-last="yes"');
    });

    it('applies AstRewriters from matching enclave via transformDocument', function (): void {
        $manager = new EnclavesManager;

        $enclave = $manager->create('test')
            ->include('**/views/**');

        $enclave->apply(new TestAstRewriter('manager-applied'));

        $doc = Document::parse('<div>content</div>');
        $result = $manager->transformDocument($doc, '/app/views/test.blade.php');

        expect($result->render())
            ->toBe('<div class="manager-applied">content</div>');
    });

    it('applies AstRewriters from multiple matching enclaves', function (): void {
        $manager = new EnclavesManager;

        $e1 = $manager->create('E1')->include('**/views/**');
        $e2 = $manager->create('E2')->include('**/views/admin/**');

        $e1->apply(new TestAstRewriter('from-e1'));

        $rewriter2 = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                return $doc->rewrite(fn ($b) => $b->findAll('div')->setAttribute('data-e2', 'yes'));
            }
        };
        $e2->apply($rewriter2);

        $doc = Document::parse('<div>content</div>');
        $result = $manager->transformDocument($doc, '/app/views/admin/dashboard.blade.php');

        expect($result->render())
            ->toContain('class="from-e1"')
            ->toContain('data-e2="yes"');
    });

    test('getRewriteItemsForPath includes AstRewriters', function (): void {
        $manager = new EnclavesManager;

        $enclave = $manager->create('test')
            ->include('**/views/**');

        $rewriter = new TestAstRewriter('test');
        $enclave->apply($rewriter);

        $items = $manager->getRewriteItemsForPath('/app/views/test.blade.php');

        expect($items)->toContain($rewriter);
    });

    test('getRewriteItemsForPath includes callbacks', function (): void {
        $manager = new EnclavesManager;

        $enclave = $manager->create('test')
            ->include('**/views/**');

        $callbackExecuted = false;
        $enclave->transform(function (NodePath $path) use (&$callbackExecuted): void {
            $callbackExecuted = true;
        });

        $items = $manager->getRewriteItemsForPath('/app/views/test.blade.php');

        expect($items)->toHaveCount(1)
            ->and(is_callable($items[0]))->toBeTrue();
    });

    it('combines visitors, callbacks, and AstRewriters from multiple enclaves', function (): void {
        $manager = new EnclavesManager;

        $e1 = $manager->create('E1')->include('**/views/**');
        $e2 = $manager->create('E2')->include('**/views/**');

        $e1->use(EnclaveTransformerA::class);
        $e1->apply(new TestAstRewriter('from-e1'));

        $e2->transform(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->setAttribute('data-callback', 'yes');
            }
        });

        $e2Rewriter = new class implements AstRewriter
        {
            public function rewrite(Document $doc): Document
            {
                return $doc->rewrite(fn ($b) => $b->findAll('div')->setAttribute('data-e2-rewriter', 'yes'));
            }
        };
        $e2->apply($e2Rewriter);

        $items = $manager->getRewriteItemsForPath('/app/views/test.blade.php');

        expect($items)->toHaveCount(4);
    });

    test('transformDocument applies all transformer types in correct order', function (): void {
        $manager = new EnclavesManager;

        $enclave = $manager->create('test')
            ->include('**/views/**');

        $visitor = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->setAttribute('data-visitor', 'yes');
                }
            }
        };
        $enclave->use($visitor, priority: 100);

        $enclave->transform(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->setAttribute('data-callback', 'yes');
            }
        });

        $enclave->apply(new TestAstRewriter('ast-rewriter'));

        $doc = Document::parse('<div>content</div>');
        $result = $manager->transformDocument($doc, '/app/views/test.blade.php');

        expect($result->render())
            ->toContain('data-visitor="yes"')
            ->toContain('data-callback="yes"')
            ->toContain('class="ast-rewriter"');
    });
});
