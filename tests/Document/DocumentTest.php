<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\Document\NodeCollection;
use Forte\Ast\EchoNode;
use Forte\Ast\Node;
use Forte\Diagnostics\DiagnosticBag;
use Forte\Diagnostics\DiagnosticSeverity;

describe('Documents', function (): void {
    it('can create document from text', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        expect($doc)->toBeInstanceOf(Document::class)
            ->and($doc->source())
            ->toBe('Hello {{ $world }}');
    });

    it('can set and get file path', function (): void {
        $doc = $this->parse('test');

        $doc->setFilePath('/path/to/file.blade.php');

        expect($doc->getFilePath())
            ->toBe('/path/to/file.blade.php');
    });

    it('can get root nodes', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $rootNodes = $doc->getRootNodes();

        expect($rootNodes)
            ->toBeInstanceOf(NodeCollection::class)
            ->and($rootNodes)->toHaveCount(2);
    });

    it('can render document', function (): void {
        $template = 'Hello {{ $world }}';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template)
            ->and((string) $doc)->toBe($template);
    });

    it('can get text by offset range', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $text = $doc->getText(0, 5);

        expect($text)->toBe('Hello');
    });

    it('can get line count', function (): void {
        $doc = $this->parse("Line 1\nLine 2\nLine 3");

        expect($doc->getLineCount())->toBe(3);
    });

    it('can get specific line', function (): void {
        $doc = $this->parse("Line 1\nLine 2\nLine 3");

        expect($doc->getLine(2))->toBe('Line 2');
    });

    it('can get all lines', function (): void {
        $doc = $this->parse("Line 1\nLine 2\nLine 3");

        $lines = $doc->getLines();

        expect($lines)->toBe(['Line 1', 'Line 2', 'Line 3']);
    });

    it('can get line excerpt with context', function (): void {
        $doc = $this->parse("Line 1\nLine 2\nLine 3\nLine 4\nLine 5");

        $excerpt = $doc->getLineExcerpt(3, 1);

        expect($excerpt)->toBe([
            2 => 'Line 2',
            3 => 'Line 3',
            4 => 'Line 4',
        ]);
    });

    it('handles Windows line endings (CRLF) for getLineCount', function (): void {
        $doc = $this->parse("Line 1\r\nLine 2\r\nLine 3");

        expect($doc->getLineCount())->toBe(3);
    });

    it('handles old Mac line endings (CR) for getLineCount', function (): void {
        $doc = $this->parse("Line 1\rLine 2\rLine 3");

        expect($doc->getLineCount())->toBe(3);
    });

    it('handles Windows line endings (CRLF) for getLine', function (): void {
        $doc = $this->parse("Line 1\r\nLine 2\r\nLine 3");

        expect($doc->getLine(1))->toBe('Line 1')
            ->and($doc->getLine(2))->toBe('Line 2')
            ->and($doc->getLine(3))->toBe('Line 3');
    });

    it('handles old Mac line endings (CR) for getLine', function (): void {
        $doc = $this->parse("Line 1\rLine 2\rLine 3");

        expect($doc->getLine(1))->toBe('Line 1')
            ->and($doc->getLine(2))->toBe('Line 2')
            ->and($doc->getLine(3))->toBe('Line 3');
    });

    it('handles Windows line endings (CRLF) for getLines', function (): void {
        $doc = $this->parse("Line 1\r\nLine 2\r\nLine 3");

        expect($doc->getLines())->toBe(['Line 1', 'Line 2', 'Line 3']);
    });

    it('handles old Mac line endings (CR) for getLines', function (): void {
        $doc = $this->parse("Line 1\rLine 2\rLine 3");

        expect($doc->getLines())->toBe(['Line 1', 'Line 2', 'Line 3']);
    });

    it('handles mixed line endings consistently', function (): void {
        $doc = $this->parse("Line 1\nLine 2\r\nLine 3\rLine 4");

        expect($doc->getLineCount())->toBe(4)
            ->and($doc->getLines())->toBe(['Line 1', 'Line 2', 'Line 3', 'Line 4'])
            ->and($doc->getLine(1))->toBe('Line 1')
            ->and($doc->getLine(2))->toBe('Line 2')
            ->and($doc->getLine(3))->toBe('Line 3')
            ->and($doc->getLine(4))->toBe('Line 4');
    });

    it('handles single line without line ending', function (): void {
        $doc = $this->parse('Single line');

        expect($doc->getLineCount())->toBe(1)
            ->and($doc->getLines())->toBe(['Single line'])
            ->and($doc->getLine(1))->toBe('Single line');
    });

    it('handles empty document', function (): void {
        $doc = $this->parse('');

        expect($doc->getLineCount())->toBe(1)
            ->and($doc->getLines())->toBe([''])
            ->and($doc->getLine(1))->toBe('');
    });

    it('handles trailing line ending', function (): void {
        $doc = $this->parse("Line 1\nLine 2\n");

        expect($doc->getLineCount())->toBe(3)
            ->and($doc->getLines())->toBe(['Line 1', 'Line 2', '']);
    });

    it('handles Windows trailing line ending', function (): void {
        $doc = $this->parse("Line 1\r\nLine 2\r\n");

        expect($doc->getLineCount())->toBe(3)
            ->and($doc->getLines())->toBe(['Line 1', 'Line 2', '']);
    });

    it('can find node at offset', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        $node = $doc->findNodeAtOffset(6);

        expect($node)->toBeInstanceOf(EchoNode::class);
    });

    it('can get text between nodes', function (): void {
        $doc = $this->parse('{{ $first }} middle {{ $second }}');

        $echoes = $doc->getEchoes();
        $first = $echoes->first();
        $second = $echoes->last();

        $between = $doc->getTextBetween($first, $second);

        expect($between)->toBe(' middle ');
    });

    it('can find node at position', function (): void {
        $doc = $this->parse("Line 1\n{{ \$test }}\nLine 3");

        $node = $doc->findNodeAtPosition(2, 1);

        expect($node)->toBeInstanceOf(EchoNode::class);
    });

    it('supports when helper for conditional operations', function (): void {
        $doc = $this->parse('{{ $test }}');
        $flag = false;

        $result = $doc->when(true, function ($d) use (&$flag): void {
            $flag = true;
        });

        expect($result)->toBe($doc)
            ->and($flag)->toBeTrue();

        $flag2 = false;
        $flag3 = false;

        $doc->when(false, function () use (&$flag2): void {
            $flag2 = true;
        }, function () use (&$flag3): void {
            $flag3 = true;
        });

        expect($flag2)->toBeFalse()
            ->and($flag3)->toBeTrue();
    });

    it('supports unless helper for inverse conditional operations', function (): void {
        $doc = $this->parse('{{ $test }}');
        $flag = false;

        $result = $doc->unless(false, function ($d) use (&$flag): void {
            $flag = true;
        });

        expect($result)->toBe($doc)
            ->and($flag)->toBeTrue();

        $flag2 = false;
        $flag3 = false;

        $doc->unless(true, function () use (&$flag2): void {
            $flag2 = true;
        }, function () use (&$flag3): void {
            $flag3 = true;
        });

        expect($flag2)->toBeFalse()
            ->and($flag3)->toBeTrue();
    });

    it('supports tap helper for side effects', function (): void {
        $doc = $this->parse('{{ $test }}');
        $inspected = null;

        $result = $doc->tap(function (Document $d) use (&$inspected): void {
            $inspected = $d->source();
        });

        expect($result)->toBe($doc)
            ->and($inspected)->toBe('{{ $test }}');
    });

    it('can check if document is filled or blank', function (): void {
        $filled = $this->parse('Hello world');
        $blank = $this->parse('   ');
        $empty = $this->parse('');

        expect($filled->filled())->toBeTrue()
            ->and($filled->blank())->toBeFalse()
            ->and($blank->filled())->toBeFalse()
            ->and($blank->blank())->toBeTrue()
            ->and($empty->filled())->toBeFalse()
            ->and($empty->blank())->toBeTrue();
    });

    it('can find all comments using comments()', function (): void {
        $doc = $this->parse('{{-- one --}} {{-- two --}}');
        $comments = $doc->getComments();

        expect($comments)->toHaveCount(2)
            ->and($comments)->toBeInstanceOf(NodeCollection::class);
    });

    it('can find all getEchoes using getEchoes()', function (): void {
        $doc = $this->parse('{{ $one }} {{ $two }}');
        $echoes = $doc->getEchoes();

        expect($echoes)->toHaveCount(2)
            ->and($echoes)->toBeInstanceOf(NodeCollection::class);
    });

    it('can walk the document tree', function (): void {
        $doc = $this->parse('Hello {{ $world }}');
        $count = 0;

        $doc->walk(function (Node $node) use (&$count): void {
            $count++;
        });

        expect($count)->toBeGreaterThan(0);
    });

    it('is countable', function (): void {
        $doc = $this->parse('Hello {{ $world }}');

        expect(count($doc))->toBe(2);
    });

    it('is iterable', function (): void {
        $doc = $this->parse('Hello {{ $world }}');
        $nodes = iterator_to_array($doc);

        expect($nodes)->toHaveCount(2);
    });

    it('can find first node matching predicate', function (): void {
        $doc = $this->parse('Hello {{ $world }} there');

        $echo = $doc->find(fn ($n) => $n instanceof EchoNode);

        expect($echo)->toBeInstanceOf(EchoNode::class);
    });

    it('can find all nodes matching predicate', function (): void {
        $doc = $this->parse('{{ $one }} text {{ $two }}');

        $echoes = $doc->findAll(fn ($n) => $n instanceof EchoNode);

        expect($echoes)->toHaveCount(2);
    });
});

describe('Document diagnostics', function (): void {
    it('returns empty diagnostics for valid template', function (): void {
        $doc = $this->parse('<div>{{ $name }}</div>');

        $diagnostics = $doc->diagnostics();

        expect($diagnostics)->toBeInstanceOf(DiagnosticBag::class)
            ->and($diagnostics->isEmpty())->toBeTrue()
            ->and($doc->hasErrors())->toBeFalse();
    });

    it('captures lexer errors for unclosed echo', function (): void {
        $doc = $this->parse('{{ $unclosed');

        $diagnostics = $doc->diagnostics();

        expect($diagnostics->hasErrors())->toBeTrue()
            ->and($doc->hasErrors())->toBeTrue();

        $errors = $diagnostics->errors();
        expect($errors)->not->toBeEmpty();

        $error = $errors->first();
        expect($error->source)->toBe('lexer')
            ->and($error->severity)->toBe(DiagnosticSeverity::Error);
    });

    it('captures lexer errors for unclosed blade comment', function (): void {
        $doc = $this->parse('{{-- unclosed comment');

        $diagnostics = $doc->diagnostics();

        expect($diagnostics->hasErrors())->toBeTrue()
            ->and($doc->hasErrors())->toBeTrue();

        $errors = $diagnostics->errors();
        expect($errors)->not->toBeEmpty();

        $error = $errors->first();
        expect($error->source)->toBe('lexer');
    });

    it('tolerates unclosed elements without crashing', function (): void {
        $doc = $this->parse('<div><span>unclosed');

        expect($doc->render())->toBe('<div><span>unclosed')
            ->and($doc->diagnostics())->toBeInstanceOf(DiagnosticBag::class);
    });

    it('tolerates unclosed directive blocks without crashing', function (): void {
        $doc = $this->parse('@if($condition) content without endif');

        expect($doc->render())->toBe('@if($condition) content without endif')
            ->and($doc->diagnostics())->toBeInstanceOf(DiagnosticBag::class);
    });

    it('captures multiple errors from different sources', function (): void {
        $doc = $this->parse('<div>@if($x) {{ $unclosed');

        $diagnostics = $doc->diagnostics();

        expect($diagnostics->hasErrors())->toBeTrue()
            ->and(count($diagnostics->errors()))->toBeGreaterThanOrEqual(1);
    });

    it('can filter diagnostics by source', function (): void {
        $doc = $this->parse('<div>unclosed');

        $diagnostics = $doc->diagnostics();
        $parserDiagnostics = $diagnostics->fromSource('parser');

        expect(collect($parserDiagnostics)->every(fn ($d) => $d->source === 'parser'))->toBeTrue();
    });

    it('can sort diagnostics by position', function (): void {
        $doc = $this->parse('<div><span>');

        $diagnostics = $doc->diagnostics();
        $diagnostics->sortByPosition();

        $all = collect($diagnostics->all());

        expect($all->sliding(2)->every(fn ($pair) => $pair->last()->start >= $pair->first()->start))->toBeTrue();
    });

    it('provides diagnostic code from error kind', function (): void {
        $doc = $this->parse('<div>');

        $diagnostics = $doc->diagnostics();
        $errors = $diagnostics->errors();

        if ($errors->isNotEmpty()) {
            $error = $errors->first();
            expect($error->code)->toBeString();
        }
    });

    it('provides diagnostic position information', function (): void {
        $doc = $this->parse('text <div> more');

        $diagnostics = $doc->diagnostics();
        $errors = $diagnostics->errors();

        if ($errors->isNotEmpty()) {
            $error = $errors->first();
            expect($error->start)->toBeGreaterThanOrEqual(0)
                ->and($error->end)->toBeGreaterThan($error->start);
        }
    });

    it('can format diagnostics as report', function (): void {
        $doc = $this->parse('<div>');
        $source = $doc->source();

        $diagnostics = $doc->diagnostics();

        if ($diagnostics->hasErrors()) {
            $report = $diagnostics->format($source);
            expect($report)->toBeString()
                ->and($report)->toContain('error');
        }
    });
});
