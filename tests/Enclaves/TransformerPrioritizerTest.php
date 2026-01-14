<?php

declare(strict_types=1);

use Forte\Enclaves\Enclave;
use Forte\Enclaves\EnclavesManager;
use Forte\Enclaves\RewriterPrioritizer;
use Forte\Rewriting\Visitor;

class TransformerA extends Visitor {}
class TransformerB extends Visitor {}
class TransformerC extends Visitor {}
class TransformerD extends Visitor {}
class TransformerE extends Visitor {}
class TransformerF extends Visitor {}
class TransformerZ extends Visitor {}

describe('Enclave Transformer Priority', function (): void {
    it('handles empty array', function (): void {
        $result = RewriterPrioritizer::orderByPriority([]);

        expect($result)->toBe([]);
    });

    it('handles single transformer', function (): void {
        $priorities = [
            TransformerA::class => 10,
        ];

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toBe([TransformerA::class]);
    });

    it('sorts by priority descending', function (): void {
        $priorities = [
            TransformerA::class => 5,
            TransformerB::class => 10,
            TransformerC::class => 1,
        ];

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toBe([
            TransformerB::class, // 10
            TransformerA::class, // 5
            TransformerC::class, // 1
        ]);
    });

    it('sorts by class name ascending within same priority', function (): void {
        $priorities = [
            TransformerZ::class => 5,
            TransformerA::class => 5,
            TransformerC::class => 5,
            TransformerB::class => 5,
        ];

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toBe([
            TransformerA::class,
            TransformerB::class,
            TransformerC::class,
            TransformerZ::class,
        ]);
    });

    it('handles negative priorities', function (): void {
        $priorities = [
            TransformerA::class => -5,
            TransformerB::class => -10,
            TransformerC::class => -1,
        ];

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toBe([
            TransformerC::class, // -1
            TransformerA::class, // -5
            TransformerB::class, // -10
        ]);
    });

    it('handles mixed positive and negative priorities', function (): void {
        $priorities = [
            TransformerA::class => 10,
            TransformerB::class => -5,
            TransformerC::class => 0,
            TransformerD::class => 5,
        ];

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toBe([
            TransformerA::class, // 10
            TransformerD::class, // 5
            TransformerC::class, // 0
            TransformerB::class, // -5
        ]);
    });

    it('handles boundary values (PHP_INT_MAX, PHP_INT_MIN)', function (): void {
        $priorities = [
            TransformerA::class => 10,
            TransformerB::class => PHP_INT_MIN,
            TransformerC::class => 0,
            TransformerD::class => 10,
            TransformerE::class => PHP_INT_MAX,
            TransformerF::class => PHP_INT_MIN,
        ];

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toBe([
            TransformerE::class, // PHP_INT_MAX
            TransformerA::class, // 10 (before D alphabetically)
            TransformerD::class, // 10
            TransformerC::class, // 0
            TransformerB::class, // PHP_INT_MIN (before F alphabetically)
            TransformerF::class, // PHP_INT_MIN
        ]);
    });

    it('provides stable sorting (deterministic results)', function (): void {
        $priorities = [
            TransformerD::class => 5,
            TransformerA::class => 10,
            TransformerC::class => 5,
            TransformerB::class => 10,
        ];

        $result1 = RewriterPrioritizer::orderByPriority($priorities);
        $result2 = RewriterPrioritizer::orderByPriority($priorities);

        expect($result1)->toBe($result2);
    });

    it('handles large numbers of transformers', function (): void {
        $priorities = [];

        for ($i = 0; $i < 100; $i++) {
            $class = "Transformer{$i}";
            $priorities[$class] = random_int(-50, 50);
        }

        $result = RewriterPrioritizer::orderByPriority($priorities);

        expect($result)->toHaveCount(100);

        $isCorrectlyOrdered = collect($result)->sliding(2)->every(function ($pair) use ($priorities) {
            [$current, $next] = $pair->values()->all();
            $currentPriority = $priorities[$current];
            $nextPriority = $priorities[$next];

            return $currentPriority > $nextPriority
                || ($currentPriority === $nextPriority && $current < $next);
        });

        expect($isCorrectlyOrdered)->toBeTrue();
    });

    it('preserves instances when available', function (): void {
        $instanceA = new TransformerA;
        $instanceB = new TransformerB;

        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => $instanceA],
            TransformerB::class => ['priority' => 5, 'instance' => $instanceB],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toHaveCount(2)
            ->and($result[0])->toBe($instanceA)
            ->and($result[1])->toBe($instanceB);
    });

    it('returns class name when instance is null', function (): void {
        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => null],
            TransformerB::class => ['priority' => 5, 'instance' => null],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toBe([
            TransformerA::class,
            TransformerB::class,
        ]);
    });

    it('mixes instances and class names correctly', function (): void {
        $instanceB = new TransformerB;

        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => null],
            TransformerB::class => ['priority' => 8, 'instance' => $instanceB],
            TransformerC::class => ['priority' => 5, 'instance' => null],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toHaveCount(3)
            ->and($result[0])->toBe(TransformerA::class)
            ->and($result[1])->toBe($instanceB)
            ->and($result[2])->toBe(TransformerC::class);
    });

    it('sorts by priority descending then class name ascending', function (): void {
        $instanceA = new TransformerA;
        $instanceZ = new TransformerZ;

        $combinedData = [
            TransformerZ::class => ['priority' => 5, 'instance' => $instanceZ],
            TransformerA::class => ['priority' => 5, 'instance' => $instanceA],
            TransformerC::class => ['priority' => 10, 'instance' => null],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toHaveCount(3)
            ->and($result[0])->toBe(TransformerC::class) // Priority 10
            ->and($result[1])->toBe($instanceA) // Priority 5, 'A' before 'Z'
            ->and($result[2])->toBe($instanceZ); // Priority 5
    });

    it('handles all instances scenario', function (): void {
        $instanceA = new TransformerA;
        $instanceB = new TransformerB;
        $instanceC = new TransformerC;

        $combinedData = [
            TransformerB::class => ['priority' => 5, 'instance' => $instanceB],
            TransformerA::class => ['priority' => 10, 'instance' => $instanceA],
            TransformerC::class => ['priority' => 1, 'instance' => $instanceC],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toBe([
            $instanceA, // Priority 10
            $instanceB, // Priority 5
            $instanceC, // Priority 1
        ]);
    });

    it('handles complex mixed transformers with boundary priorities', function (): void {
        $instanceMax = new TransformerE;
        $instanceMid = new TransformerC;

        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => null],
            TransformerB::class => ['priority' => PHP_INT_MIN, 'instance' => null],
            TransformerC::class => ['priority' => 0, 'instance' => $instanceMid],
            TransformerE::class => ['priority' => PHP_INT_MAX, 'instance' => $instanceMax],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toHaveCount(4)
            ->and($result[0])->toBe($instanceMax) // PHP_INT_MAX
            ->and($result[1])->toBe(TransformerA::class) // 10
            ->and($result[2])->toBe($instanceMid) // 0
            ->and($result[3])->toBe(TransformerB::class); // PHP_INT_MIN
    });

    it('produces same class order with orderByPriority and orderWithInstances when no instances', function (): void {
        $priorities = [
            TransformerA::class => 10,
            TransformerB::class => 5,
            TransformerC::class => 10,
            TransformerD::class => 0,
        ];

        $combinedData = collect($priorities)
            ->map(fn ($priority) => ['priority' => $priority, 'instance' => null])
            ->all();

        $result1 = RewriterPrioritizer::orderByPriority($priorities);
        $result2 = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result1)->toBe($result2);
    });

    it('throws exception for non-string key in priorities', function (): void {
        $priorities = [
            123 => 10,
        ];

        expect(fn () => RewriterPrioritizer::orderByPriority($priorities))
            ->toThrow(InvalidArgumentException::class, 'class-string values');
    });

    it('throws exception for non-integer priority value', function (): void {
        $priorities = [
            TransformerA::class => '10',
        ];

        expect(fn () => RewriterPrioritizer::orderByPriority($priorities))
            ->toThrow(InvalidArgumentException::class, 'must be an integer');
    });

    it('throws exception for float priority value', function (): void {
        $priorities = [
            TransformerA::class => 10.5,
        ];

        expect(fn () => RewriterPrioritizer::orderByPriority($priorities))
            ->toThrow(InvalidArgumentException::class, 'must be an integer');
    });

    it('throws exception for non-string key in combined data', function (): void {
        $combinedData = [
            123 => ['priority' => 10, 'instance' => null],
        ];

        expect(fn () => RewriterPrioritizer::orderWithInstances($combinedData))
            ->toThrow(InvalidArgumentException::class, 'class-string values');
    });

    it('throws exception for non-array data value in combined data', function (): void {
        $combinedData = [
            TransformerA::class => 'not an array',
        ];

        expect(fn () => RewriterPrioritizer::orderWithInstances($combinedData))
            ->toThrow(InvalidArgumentException::class, 'must be an array');
    });

    it('throws exception for missing priority key in combined data', function (): void {
        $combinedData = [
            TransformerA::class => ['instance' => null],
        ];

        expect(fn () => RewriterPrioritizer::orderWithInstances($combinedData))
            ->toThrow(InvalidArgumentException::class, 'must have a \'priority\' key');
    });

    it('throws exception for missing instance key in combined data', function (): void {
        $combinedData = [
            TransformerA::class => ['priority' => 10], // Missing 'instance'
        ];

        expect(fn () => RewriterPrioritizer::orderWithInstances($combinedData))
            ->toThrow(InvalidArgumentException::class, 'must have an \'instance\' key');
    });

    it('throws exception for non-integer priority in combined data', function (): void {
        $combinedData = [
            TransformerA::class => ['priority' => '10', 'instance' => null],
        ];

        expect(fn () => RewriterPrioritizer::orderWithInstances($combinedData))
            ->toThrow(InvalidArgumentException::class, 'must be an integer');
    });

    it('throws exception for invalid instance type in combined data', function (): void {
        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => 'not a visitor'],
        ];

        expect(fn () => RewriterPrioritizer::orderWithInstances($combinedData))
            ->toThrow(InvalidArgumentException::class, 'must be null or an instance');
    });

    it('accepts null instance in combined data', function (): void {
        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => null],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toBe([TransformerA::class]);
    });

    it('accepts valid Visitor instance in combined data', function (): void {
        $instance = new TransformerA;
        $combinedData = [
            TransformerA::class => ['priority' => 10, 'instance' => $instance],
        ];

        $result = RewriterPrioritizer::orderWithInstances($combinedData);

        expect($result)->toBe([$instance]);
    });

    it('preserves the first instance when multiple enclaves provide instances at same priority', function (): void {
        $registry = new EnclavesManager;

        $instance1 = new TransformerA;
        $instance2 = new TransformerA;

        $e1 = $registry->create('E1')->include('**/test/**');
        $e2 = $registry->create('E2')->include('**/test/**');

        $e1->addRewriter($instance1, 5);
        $e2->addRewriter($instance2, 5);

        $path = 'C:/test/file.php';
        $transformers = $registry->getRewritersForPath($path);

        expect($transformers)->toHaveCount(1)
            ->and($transformers[0])->toBe($instance1);
    });

    it('replaces lower priority instance with higher priority instance', function (): void {
        $registry = new EnclavesManager;

        $lowPriorityInstance = new TransformerA;
        $highPriorityInstance = new TransformerA;

        $e1 = $registry->create('E1')->include('**/test/**');
        $e2 = $registry->create('E2')->include('**/test/**');

        $e1->addRewriter($lowPriorityInstance, 5);
        $e2->addRewriter($highPriorityInstance, 10);

        $path = 'C:/test/file.php';
        $transformers = $registry->getRewritersForPath($path);

        expect($transformers)->toHaveCount(1)
            ->and($transformers[0])->toBe($highPriorityInstance);
    });

    it('updates priority when replacing transformer class with instance', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class, 5);
        expect($enclave->getRewritersInPriorityOrder())->toBe([TransformerA::class]);

        $instance = new TransformerA;
        $enclave->addRewriter($instance, 10);

        $classes = $enclave->getRewritersInPriorityOrder();
        expect($classes)->toBe([TransformerA::class]);

        $transformers = $enclave->getRewriters();
        expect($transformers[0])->toBe($instance);
    });

    it('overwrites instance with new priority when adding class string after instance', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $instance = new TransformerA;
        $enclave->addRewriter($instance, 5);

        $transformers1 = $enclave->getRewriters();
        expect($transformers1[0])->toBe($instance);

        $enclave->addRewriter(TransformerA::class, 10);

        $data = $enclave->getRewriterPriorities();
        expect($data[TransformerA::class]['priority'])->toBe(10)
            ->and($data[TransformerA::class]['instance'])->toBeNull();
    });

    it('does not overwrite existing instance with null instance from second enclave at same priority', function (): void {
        $registry = new EnclavesManager;

        $instance = new TransformerA;

        $e1 = $registry->create('E1')->include('**/test/**');
        $e2 = $registry->create('E2')->include('**/test/**');

        $e1->addRewriter($instance, 5);
        $e2->addRewriter(TransformerA::class, 5);

        $path = 'C:/test/file.php';
        $transformers = $registry->getRewritersForPath($path);

        expect($transformers)->toHaveCount(1)
            ->and($transformers[0])->toBe($instance);
    });

    it('overwrites null instance from first enclave with instance from second at same priority', function (): void {
        $registry = new EnclavesManager;

        $instance = new TransformerA;

        $e1 = $registry->create('E1')->include('**/test/**');
        $e2 = $registry->create('E2')->include('**/test/**');

        $e1->addRewriter(TransformerA::class, 5);
        $e2->addRewriter($instance, 5);

        $path = 'C:/test/file.php';
        $transformers = $registry->getRewritersForPath($path);

        expect($transformers)->toHaveCount(1)
            ->and($transformers[0])->toBe($instance);
    });

    it('resolves three-way priority conflict with highest priority winning', function (): void {
        $registry = new EnclavesManager;

        $e1 = $registry->create('E1')->include('**/test/**');
        $e2 = $registry->create('E2')->include('**/test/**');
        $e3 = $registry->create('E3')->include('**/test/**');

        $e1->addRewriter(TransformerA::class, 5);
        $e2->addRewriter(TransformerA::class, 10);
        $e3->addRewriter(TransformerA::class, 7);

        $path = 'C:/test/file.php';
        $classes = $registry->getRewriterClassesForPath($path);

        expect($classes)->toBe([TransformerA::class]);

        $combinedData = $registry->get('E2')->getRewriterPriorities();
        expect($combinedData[TransformerA::class]['priority'])->toBe(10);
    });

    it('combines different transformers from multiple enclaves with complex priorities', function (): void {
        $registry = new EnclavesManager;

        $e1 = $registry->create('E1')->include('**/test/**');
        $e2 = $registry->create('E2')->include('**/test/**');
        $e3 = $registry->create('E3')->include('**/test/**');

        // E1: A(10), B(5)
        $e1->addRewriter(TransformerA::class, 10);
        $e1->addRewriter(TransformerB::class, 5);

        // E2: A(7), C(8)  - A's priority is lower, so E1's A wins
        $e2->addRewriter(TransformerA::class, 7);
        $e2->addRewriter(TransformerC::class, 8);

        // E3: B(12) - B's priority is higher, so E3's B wins
        $e3->addRewriter(TransformerB::class, 12);

        $path = 'C:/test/file.php';
        $classes = $registry->getRewriterClassesForPath($path);

        // Expected: B(12), A(10), C(8)
        expect($classes)->toBe([
            TransformerB::class,
            TransformerA::class,
            TransformerC::class,
        ]);
    });

    it('handles zero priority correctly', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class, 0);
        $enclave->addRewriter(TransformerB::class, 5);
        $enclave->addRewriter(TransformerC::class, -5);

        $classes = $enclave->getRewritersInPriorityOrder();

        expect($classes)->toBe([
            TransformerB::class,  // 5
            TransformerA::class,  // 0
            TransformerC::class,  // -5
        ]);
    });

    it('default priority is 0 when not specified', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class);

        $data = $enclave->getRewriterPriorities();
        expect($data[TransformerA::class]['priority'])->toBe(0);
    });

    it('handles path matching when no enclaves match', function (): void {
        $registry = new EnclavesManager;

        $e1 = $registry->create('E1')->include('**/src/**');
        $e1->addRewriter(TransformerA::class);

        $path = 'C:/other/file.php';
        $transformers = $registry->getRewritersForPath($path);

        expect($transformers)->toBeEmpty();
    });

    it('handles multiple enclaves where only some match the path', function (): void {
        $registry = new EnclavesManager;

        $e1 = $registry->create('E1')->include('**/src/**');
        $e2 = $registry->create('E2')->include('**/test/**');
        $e3 = $registry->create('E3')->include('**/src/**');

        $e1->addRewriter(TransformerA::class, 10);
        $e2->addRewriter(TransformerB::class, 10);
        $e3->addRewriter(TransformerC::class, 5);

        $path = 'C:/src/file.php'; // Matches E1 and E3, not E2

        $classes = $registry->getRewriterClassesForPath($path);

        expect($classes)->toBe([
            TransformerA::class, // From E1, priority 10
            TransformerC::class, // From E3, priority 5
        ]);
    });

    it('returns empty when enclave has transformers but no matching path', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');
        $enclave->addRewriter(TransformerA::class);

        expect($enclave->matches('C:/other/file.php'))->toBeFalse()
            ->and($enclave->hasRewriters())->toBeTrue();
    });

    it('removes all transformers when clearing transformers', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class, 10);
        $enclave->addRewriter(TransformerB::class, 5);

        expect($enclave->rewriterCount())->toBe(2);

        $enclave->clearRewriters();

        expect($enclave->rewriterCount())->toBe(0)
            ->and($enclave->hasRewriters())->toBeFalse()
            ->and($enclave->getRewritersInPriorityOrder())->toBeEmpty();
    });

    it('does not error when removing non-existent transformer', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class);

        $enclave->removeRewriter(TransformerB::class);

        expect($enclave->rewriterCount())->toBe(1)
            ->and($enclave->hasRewriter(TransformerA::class))->toBeTrue();
    });

    it('removes transformer instance by class-string', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $instance = new TransformerA;
        $enclave->addRewriter($instance);

        expect($enclave->hasRewriter($instance))->toBeTrue()
            ->and($enclave->hasRewriter(TransformerA::class))->toBeTrue();

        $enclave->removeRewriter(TransformerA::class);

        expect($enclave->hasRewriter($instance))->toBeFalse()
            ->and($enclave->hasRewriter(TransformerA::class))->toBeFalse();
    });

    it('uses custom instantiator when provided', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class);

        $customInstance = new TransformerA;
        $instantiator = fn (string $class) => $customInstance;

        $transformers = $enclave->getRewriters($instantiator);

        expect($transformers[0])->toBe($customInstance);
    });

    it('uses custom instantiator for class strings but not preserved instances', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $preservedInstance = new TransformerA;
        $enclave->addRewriter($preservedInstance);
        $enclave->addRewriter(TransformerB::class);

        $customBInstance = new TransformerB;
        $instantiator = fn (string $class) => $class === TransformerB::class
            ? $customBInstance
            : new TransformerA;

        $transformers = $enclave->getRewriters($instantiator);

        expect($transformers[0])->toBe($preservedInstance)
            ->and($transformers[1])->toBe($customBInstance);
    });

    it('throws exception when custom instantiator does not return Visitor', function (): void {
        $enclave = new Enclave;
        $enclave->include('**/src/**');

        $enclave->addRewriter(TransformerA::class);

        $badInstantiator = fn (string $class) => 'not a visitor';

        expect(fn () => $enclave->getRewriters($badInstantiator))
            ->toThrow(InvalidArgumentException::class);
    });

    it('supports bulk transformer registration with useMany', function (): void {
        $enclave = new Enclave;

        $enclave->useMany([
            TransformerA::class,
            TransformerB::class,
            TransformerC::class,
        ], 10);

        expect($enclave->rewriterCount())->toBe(3);

        $classes = $enclave->getRewritersInPriorityOrder();
        expect($classes)->toContain(
            TransformerA::class,
            TransformerB::class,
            TransformerC::class,
        );
    });

    it('can check if enclave has transformers', function (): void {
        $enclave = new Enclave;

        expect($enclave->hasRewriters())->toBeFalse()
            ->and($enclave->rewriterCount())->toBe(0);

        $enclave->addRewriter(TransformerA::class);

        expect($enclave->hasRewriters())->toBeTrue()
            ->and($enclave->rewriterCount())->toBe(1);
    });

    it('can check if specific transformer is registered', function (): void {
        $enclave = new Enclave;

        $enclave->addRewriter(TransformerA::class);

        expect($enclave->hasRewriter(TransformerA::class))->toBeTrue()
            ->and($enclave->hasRewriter(TransformerB::class))->toBeFalse();

        $instance = new TransformerB;
        $enclave->addRewriter($instance);

        expect($enclave->hasRewriter(TransformerB::class))->toBeTrue()
            ->and($enclave->hasRewriter($instance))->toBeTrue();
    });

    it('can get include and exclude patterns', function (): void {
        $enclave = new Enclave;

        expect($enclave->getIncludes())->toBeEmpty()
            ->and($enclave->getExcludes())->toBeEmpty();

        $enclave->include('**/src/**', '**/app/**');
        $enclave->exclude('**/vendor/**');

        expect($enclave->getIncludes())->toBe(['**/src/**', '**/app/**'])
            ->and($enclave->getExcludes())->toBe(['**/vendor/**']);
    });

    it('checks for enclave existence with registry has method', function (): void {
        $registry = new EnclavesManager;

        expect($registry->has('{app}'))->toBeTrue()
            ->and($registry->has('non-existent'))->toBeFalse();

        $registry->create('test-enclave');

        expect($registry->has('test-enclave'))->toBeTrue();
    });

    it('returns all enclave names with registry names method', function (): void {
        $registry = new EnclavesManager;

        $names = $registry->names();
        expect($names)->toContain('{app}');

        $registry->create('enclave1');
        $registry->create('enclave2');

        $names = $registry->names();
        expect($names)->toContain('{app}')
            ->and($names)->toContain('enclave1')
            ->and($names)->toContain('enclave2')
            ->and(count($names))->toBe(3);
    });

    it('returns number of enclaves with registry count method', function (): void {
        $registry = new EnclavesManager;

        expect($registry->count())->toBe(1); // {app} enclave

        $registry->create('enclave1');
        expect($registry->count())->toBe(2);

        $registry->create('enclave2');
        expect($registry->count())->toBe(3);
    });

    it('checks if any transformers apply with registry hasRewritersForPath', function (): void {
        $registry = new EnclavesManager;

        $appView = resource_path('views/welcome.blade.php');
        expect($registry->hasRewritersForPath($appView))->toBeTrue(); // App enclave matches

        $vendorView = resource_path('views/vendor/package/view.blade.php');
        expect($registry->hasRewritersForPath($vendorView))->toBeFalse();

        $registry->create('vendor:package')
            ->include(resource_path('views/vendor/package/**'))
            ->addRewriter(TransformerA::class);

        expect($registry->hasRewritersForPath($vendorView))->toBeTrue();
    });

    it('returns enclaves that match the path with getMatchingEnclaves', function (): void {
        $registry = new EnclavesManager;

        $registry->create('E1')->include('**/test/**');
        $registry->create('E2')->include('**/test/**');
        $registry->create('E3')->include('**/other/**');

        $path = 'C:/project/test/file.php';

        $matching = $registry->getMatchingEnclaves($path);
        expect($matching)->toHaveCount(2);

        $registry->create('E4')->include('**/test/**');

        $matching2 = $registry->getMatchingEnclaves($path);
        expect($matching2)->toHaveCount(3);
    });
});
