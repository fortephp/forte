<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;
use Forte\Tests\Support\Extensions\ModifierManager;

require_once __DIR__.'/ModifierSyntaxExtensionTest.php';

class ModifierCompilerTransformer extends Visitor
{
    private ModifierManager $modifierManager;

    private array $stats = [
        'nodes_processed' => 0,
        'modifiers_applied' => 0,
    ];

    public function __construct(?ModifierManager $modifierManager = null)
    {
        $this->modifierManager = $modifierManager ?? ModifierManager::withDefaults();
    }

    public function enter(NodePath $path): void
    {
        if (! $path->isEcho()) {
            return;
        }

        $node = $path->asEcho();

        if (! $node->hasData('modifier_syntax')) {
            return;
        }

        $data = $node->getData('modifier_syntax');

        if (! is_array($data) || ! isset($data['expression'], $data['modifiers'])) {
            return;
        }

        $compiledExpression = $this->modifierManager->compile(
            $data['expression'],
            $data['modifiers']
        );

        if ($node->isRaw()) {
            $path->replaceWith(Builder::rawEcho($compiledExpression));
        } elseif ($node->isTriple()) {
            $path->replaceWith(Builder::tripleEcho($compiledExpression));
        } else {
            $path->replaceWith(Builder::echo($compiledExpression));
        }

        $this->stats['nodes_processed']++;
        $this->stats['modifiers_applied'] += count($data['modifiers']);

        $node->setData('modifier_compiled', [
            'original' => $data['expression'],
            'compiled' => $compiledExpression,
            'modifiers_count' => count($data['modifiers']),
        ]);
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function resetStats(): void
    {
        $this->stats = [
            'nodes_processed' => 0,
            'modifiers_applied' => 0,
        ];
    }

    public function getModifierManager(): ModifierManager
    {
        return $this->modifierManager;
    }

    public function setModifierManager(ModifierManager $manager): static
    {
        $this->modifierManager = $manager;

        return $this;
    }
}

function parseTemplate(string $template): Document
{
    $doc = Document::parse($template);
    $parser = new ModifierSyntaxParser;
    $parser->process($doc);

    return $doc;
}

function parseAndRewrite(string $template, ?ModifierManager $manager = null): Document
{
    $doc = parseTemplate($template);

    $transformer = new ModifierCompilerTransformer($manager);
    $rewriter = new Rewriter;
    $rewriter->addVisitor($transformer);

    return $rewriter->rewrite($doc);
}

describe('Modifier Compiler - ModifierManager', function (): void {
    it('registers and checks modifiers', function (): void {
        $manager = new ModifierManager;

        expect($manager->has('upper'))->toBeFalse();

        $manager->register('upper', 'strtoupper(?)');

        expect($manager->has('upper'))->toBeTrue()
            ->and($manager->all())->toContain('upper');
    });

    it('registers multiple modifiers at once', function (): void {
        $manager = new ModifierManager;

        $manager->registerMany([
            'upper' => 'strtoupper(?)',
            'lower' => 'strtolower(?)',
            'trim' => 'trim(?)',
        ]);

        expect($manager->all())->toHaveCount(3)
            ->and($manager->has('upper'))->toBeTrue()
            ->and($manager->has('lower'))->toBeTrue()
            ->and($manager->has('trim'))->toBeTrue();
    });

    it('compiles a simple modifier', function (): void {
        $manager = new ModifierManager;
        $manager->register('upper', 'strtoupper(?)');

        $result = $manager->compile('$name', [
            ['name' => 'upper', 'arguments' => []],
        ]);

        expect($result)->toBe('strtoupper($name)');
    });

    it('compiles chained modifiers', function (): void {
        $manager = new ModifierManager;
        $manager->register('upper', 'strtoupper(?)');
        $manager->register('trim', 'trim(?)');

        $result = $manager->compile('$name', [
            ['name' => 'trim', 'arguments' => []],
            ['name' => 'upper', 'arguments' => []],
        ]);

        expect($result)->toBe('strtoupper(trim($name))');
    });

    it('compiles modifiers with arguments', function (): void {
        $manager = new ModifierManager;
        $manager->register('default', '? ?? {0}');

        $result = $manager->compile('$name', [
            ['name' => 'default', 'arguments' => ['"Guest"']],
        ]);

        expect($result)->toBe('$name ?? "Guest"');
    });

    it('handles complex expressions with wrapping', function (): void {
        $manager = new ModifierManager;
        $manager->register('upper', 'strtoupper(?)');
        $manager->register('default', '? ?? {0}');

        $result = $manager->compile('$user->name ?? "Guest"', [
            ['name' => 'upper', 'arguments' => []],
        ]);

        expect($result)->toBe('strtoupper(($user->name ?? "Guest"))');
    });

    it('supports callback-based modifiers', function (): void {
        $manager = new ModifierManager;
        $manager->register('double', fn ($expr) => "({$expr} * 2)");

        $result = $manager->compile('$number', [
            ['name' => 'double', 'arguments' => []],
        ]);

        expect($result)->toBe('($number * 2)');
    });

    it('creates manager with default modifiers', function (): void {
        $manager = ModifierManager::withDefaults();

        expect($manager->has('upper'))->toBeTrue()
            ->and($manager->has('lower'))->toBeTrue()
            ->and($manager->has('default'))->toBeTrue()
            ->and($manager->has('escape'))->toBeTrue()
            ->and(count($manager->all()))->toBeGreaterThan(5);
    });
});

describe('Modifier Compiler - Rewriters', function (): void {
    it('compiles modifier syntax in echo nodes', function (): void {
        $doc = parseAndRewrite('{{ $name | upper }}');

        expect($doc->render())->toBe('{{ strtoupper($name) }}');
    });

    it('compiles chained modifiers', function (): void {
        $doc = parseAndRewrite('{{ $text | trim | upper }}');

        expect($doc->render())->toBe('{{ strtoupper(trim($text)) }}');
    });

    it('compiles modifiers with arguments', function (): void {
        $doc = parseAndRewrite('{{ $name | default("Guest") }}');

        expect($doc->render())->toBe('{{ $name ?? "Guest" }}');
    });

    it('handles complex modifier chains', function (): void {
        $doc = parseAndRewrite('{{ $text | default("") | trim | upper }}');

        expect($doc->render())->toBe('{{ strtoupper(trim(($text ?? ""))) }}');
    });

    it('tracks compilation statistics', function (): void {
        $doc = parseTemplate('{{ $a | upper }} {{ $b | lower }}');

        $transformer = new ModifierCompilerTransformer;
        $rewriter = new Rewriter;
        $rewriter->addVisitor($transformer);
        $rewriter->rewrite($doc);

        $stats = $transformer->getStats();
        expect($stats['nodes_processed'])->toBe(2)
            ->and($stats['modifiers_applied'])->toBe(2);
    });

    it('supports custom modifier managers', function (): void {
        $manager = new ModifierManager;
        $manager->register('custom', 'myCustomFunction(?)');

        $doc = parseAndRewrite('{{ $val | custom }}', $manager);

        expect($doc->render())->toBe('{{ myCustomFunction($val) }}');
    });

    it('does not modify echo nodes without modifier syntax', function (): void {
        $doc = parseAndRewrite('{{ $variable }}');

        expect($doc->render())->toBe('{{ $variable }}');
    });

    it('compiles and renders modifiers correctly', function (): void {
        $doc = parseAndRewrite('{{ $name | upper }}');

        expect($doc->render())->toBe('{{ strtoupper($name) }}');
    });

    it('executes default modifier', function (): void {
        $doc = parseAndRewrite('{{ $name | default("Guest") }}');

        expect($doc->render())->toBe('{{ $name ?? "Guest" }}');
    });

    it('executes chained modifiers correctly', function (): void {
        $doc = parseAndRewrite('{{ $text | trim | lower }}');

        expect($doc->render())->toBe('{{ strtolower(trim($text)) }}');
    });

    it('handles multiple echo nodes independently', function (): void {
        $doc = parseAndRewrite('{{ $first | upper }} and {{ $second | lower }}');

        expect($doc->render())->toBe('{{ strtoupper($first) }} and {{ strtolower($second) }}');
    });

    it('handles complex expressions with modifiers', function (): void {
        $doc = parseAndRewrite('{{ $user->name ?? "Guest" | upper }}');

        expect($doc->render())->toBe('{{ strtoupper(($user->name ?? "Guest")) }}');
    });

    it('skips unregistered modifiers gracefully', function (): void {
        $doc = parseAndRewrite('{{ $name | unknown | upper }}');

        // 'unknown' is skipped, 'upper' is applied
        expect($doc->render())->toBe('{{ strtoupper($name) }}');
    });

    it('preserves raw echo type during transformation', function (): void {
        $doc = $this->parse('{!! $html | upper !!}');
        $parser = new ModifierSyntaxParser;
        $parser->process($doc);

        $transformer = new ModifierCompilerTransformer;
        $rewriter = new Rewriter;
        $rewriter->addVisitor($transformer);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('{!! strtoupper($html) !!}');
    });

    it('works with text content alongside modifiers', function (): void {
        $doc = parseAndRewrite('Hello {{ $name | upper }}, welcome!');

        expect($doc->render())->toBe('Hello {{ strtoupper($name) }}, welcome!');
    });
});

describe('Modifier Compiler - Integration', function (): void {
    it('demonstrates full pipeline: parse -> extract -> rewrite', function (): void {
        $template = <<<'BLADE'
<div class="user-profile">
    <h1>{{ $user->name | upper }}</h1>
    <p>{{ $user->bio | default("No bio provided") | trim }}</p>
    <span>{{ $user->email }}</span>
</div>
BLADE;

        $doc = parseAndRewrite($template);
        $output = $doc->render();

        expect($output)->toContain('strtoupper($user->name)')
            ->and($output)->toContain('trim(($user->bio ?? "No bio provided"))')
            ->and($output)->toContain('{{ $user->email }}');
    });

    it('handles nested elements with modifiers', function (): void {
        $template = <<<'BLADE'
<ul>
    <li>{{ $item->name | upper | trim }}</li>
    <li>{{ $item->title | lower }}</li>
</ul>
BLADE;

        $doc = parseAndRewrite($template);

        expect($doc->render())->toContain('trim(strtoupper($item->name))')
            ->and($doc->render())->toContain('strtolower($item->title)');
    });

    it('preserves surrounding template structure', function (): void {
        $template = '<!DOCTYPE html><html><body>{{ $content | escape }}</body></html>';

        $doc = parseAndRewrite($template);

        expect($doc->render())->toBe('<!DOCTYPE html><html><body>{{ htmlspecialchars($content, ENT_QUOTES) }}</body></html>');
    });
});
