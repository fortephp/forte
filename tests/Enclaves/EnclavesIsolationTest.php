<?php

declare(strict_types=1);

use Forte\Enclaves\Enclave;
use Forte\Enclaves\EnclavesManager;
use Forte\Rewriting\Visitor;

class IsolationTransformerA extends Visitor {}
class IsolationTransformerB extends Visitor {}
class IsolationTransformerC extends Visitor {}
class IsolationTransformerShared extends Visitor {}

describe('Enclave Isolation', function (): void {
    it('does not leak transformers between enclaves', function (): void {
        $e1 = new Enclave;
        $e2 = new Enclave;

        $e1->include('proj/A/**');
        $e2->include('proj/B/**');

        $e1->addRewriter(IsolationTransformerA::class, 5);
        $e2->addRewriter(IsolationTransformerB::class, 7);

        expect($e1->matches('proj/A/file.blade.php'))->toBeTrue()
            ->and($e2->matches('proj/A/file.blade.php'))->toBeFalse();

        $reg = new EnclavesManager;
        $reg->add('A', $e1);
        $reg->add('B', $e2);

        $classesA = $reg->getRewriterClassesForPath('proj/A/file.blade.php');
        expect($classesA)->toBe([IsolationTransformerA::class]);

        $classesB = $reg->getRewriterClassesForPath('proj/B/file.blade.php');
        expect($classesB)->toBe([IsolationTransformerB::class]);
    });

    it('resolves duplicate transformer class across enclaves by priority and preserved instance on ties', function (): void {
        $reg = new EnclavesManager;

        $e1 = $reg->create('E1')->include('**/pkg/**');
        $e2 = $reg->create('E2')->include('**/pkg/**');

        $e1->addRewriter(IsolationTransformerShared::class, 3);
        $e2->addRewriter(IsolationTransformerShared::class, 10);

        $path = 'C:/work/pkg/one.php';
        $classes = $reg->getRewriterClassesForPath($path);
        expect($classes)->toBe([IsolationTransformerShared::class]);

        $e1->clearRewriters();
        $e2->clearRewriters();

        $preserved = new IsolationTransformerShared;
        $e1->addRewriter($preserved, 5);
        $e2->addRewriter(IsolationTransformerShared::class, 5);

        $items = $reg->getRewriterClassesForPath($path);

        expect($items)->toHaveCount(1)
            ->and($items[0])->toBe($preserved);

        $instances = $reg->getRewritersForPath($path);
        expect($instances)->toHaveCount(1)
            ->and($instances[0])->toBe($preserved);
    });

    it('orders different classes at same priority by class name across enclaves', function (): void {
        $reg = new EnclavesManager;

        $e1 = $reg->create('E1')->include('**/x/**');
        $e2 = $reg->create('E2')->include('**/x/**');

        $e1->addRewriter(IsolationTransformerC::class, 7);
        $e2->addRewriter(IsolationTransformerB::class, 7);
        $e1->addRewriter(IsolationTransformerA::class, 7);

        $path = 'C:/x/file.php';

        $classes = $reg->getRewriterClassesForPath($path);
        expect($classes)->toBe([IsolationTransformerA::class, IsolationTransformerB::class, IsolationTransformerC::class]);
    });

    it('app enclave does not impact named package and directory enclaves', function (): void {
        $reg = new EnclavesManager;

        $pkg = $reg->create('vendor:acme/tooling')->include('C:/vendor/acme/tooling/**');

        $dir = $reg->create('dir:templates')->include('D:/templates/**');

        $pkg->addRewriter(IsolationTransformerA::class, 1);
        $dir->addRewriter(IsolationTransformerB::class, 1);

        $classesPkg = $reg->getRewriterClassesForPath('C:/vendor/acme/tooling/res/view.php');
        expect($classesPkg)->toBe([IsolationTransformerA::class]);

        $classesDir = $reg->getRewriterClassesForPath('D:/templates/page.php');
        expect($classesDir)->toBe([IsolationTransformerB::class]);
    });

    it('guards against invalid enclave names starting with {', function (): void {
        $reg = new EnclavesManager;

        expect(fn () => $reg->add('{bad}', new Enclave))->toThrow(InvalidArgumentException::class);
    });
});
