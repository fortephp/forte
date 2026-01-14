<?php

declare(strict_types=1);

use Forte\Diagnostics\Diagnostic;
use Forte\Diagnostics\DiagnosticBag;
use Forte\Diagnostics\DiagnosticSeverity;

describe('DiagnosticSeverity', function (): void {
    it('has correct severity values', function (): void {
        expect(DiagnosticSeverity::Error->value)->toBe(1)
            ->and(DiagnosticSeverity::Warning->value)->toBe(2)
            ->and(DiagnosticSeverity::Info->value)->toBe(3)
            ->and(DiagnosticSeverity::Hint->value)->toBe(4);
    });

    it('provides human-readable labels', function (): void {
        expect(DiagnosticSeverity::Error->label())->toBe('error')
            ->and(DiagnosticSeverity::Warning->label())->toBe('warning')
            ->and(DiagnosticSeverity::Info->label())->toBe('info')
            ->and(DiagnosticSeverity::Hint->label())->toBe('hint');
    });

    it('compares severity levels', function (): void {
        expect(DiagnosticSeverity::Error->isAtLeast(DiagnosticSeverity::Warning))->toBeTrue()
            ->and(DiagnosticSeverity::Warning->isAtLeast(DiagnosticSeverity::Error))->toBeFalse()
            ->and(DiagnosticSeverity::Warning->isAtLeast(DiagnosticSeverity::Warning))->toBeTrue();
    });
});

describe('Diagnostic', function (): void {
    it('creates diagnostic with all properties', function (): void {
        $diag = new Diagnostic(
            DiagnosticSeverity::Error,
            'Something went wrong',
            10,
            20,
            'test-extension',
            'E001'
        );

        expect($diag->severity)->toBe(DiagnosticSeverity::Error)
            ->and($diag->message)->toBe('Something went wrong')
            ->and($diag->start)->toBe(10)
            ->and($diag->end)->toBe(20)
            ->and($diag->source)->toBe('test-extension')
            ->and($diag->code)->toBe('E001');
    });

    it('checks severity type', function (): void {
        $error = Diagnostic::error('err', 0, 1, 'src');
        $warning = Diagnostic::warning('warn', 0, 1, 'src');
        $info = Diagnostic::info('info', 0, 1, 'src');
        $hint = Diagnostic::hint('hint', 0, 1, 'src');

        expect($error->isError())->toBeTrue()
            ->and($error->isWarning())->toBeFalse()
            ->and($warning->isWarning())->toBeTrue()
            ->and($info->isInfo())->toBeTrue()
            ->and($hint->isHint())->toBeTrue();
    });

    it('calculates length', function (): void {
        $diag = Diagnostic::error('msg', 5, 15, 'src');

        expect($diag->length())->toBe(10);
    });

    it('formats as string', function (): void {
        $diag = new Diagnostic(
            DiagnosticSeverity::Warning,
            'Deprecated syntax',
            10,
            25,
            'my-ext',
            'W001'
        );

        $str = (string) $diag;

        expect($str)->toContain('warning')
            ->and($str)->toContain('10-25')
            ->and($str)->toContain('[W001]')
            ->and($str)->toContain('Deprecated syntax')
            ->and($str)->toContain('[my-ext]');
    });

    it('formats without code', function (): void {
        $diag = Diagnostic::error('No code', 0, 5, 'src');

        $str = (string) $diag;

        expect($str)->toContain('[src]')
            ->and($str)->not->toMatch('/\[\w+\d+\]/');
    });

    it('creates via static factories', function (): void {
        $error = Diagnostic::error('err', 0, 1, 'src', 'E1');
        $warning = Diagnostic::warning('warn', 2, 3, 'src', 'W1');
        $info = Diagnostic::info('info', 4, 5, 'src');
        $hint = Diagnostic::hint('hint', 6, 7, 'src');

        expect($error->severity)->toBe(DiagnosticSeverity::Error)
            ->and($error->code)->toBe('E1')
            ->and($warning->severity)->toBe(DiagnosticSeverity::Warning)
            ->and($info->severity)->toBe(DiagnosticSeverity::Info)
            ->and($hint->severity)->toBe(DiagnosticSeverity::Hint);
    });
});

describe('DiagnosticBag', function (): void {
    it('adds and retrieves diagnostics', function (): void {
        $bag = new DiagnosticBag;
        $bag->add(Diagnostic::error('error1', 0, 5, 'src'));
        $bag->add(Diagnostic::warning('warn1', 10, 15, 'src'));

        expect($bag->count())->toBe(2)
            ->and($bag->all())->toHaveCount(2);
    });

    it('adds many diagnostics at once', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'src'),
            Diagnostic::error('e2', 2, 3, 'src'),
            Diagnostic::warning('w1', 4, 5, 'src'),
        ]);

        expect($bag->count())->toBe(3);
    });

    it('checks empty state', function (): void {
        $bag = new DiagnosticBag;

        expect($bag->isEmpty())->toBeTrue();

        $bag->add(Diagnostic::info('msg', 0, 1, 'src'));

        expect($bag->isEmpty())->toBeFalse();
    });

    it('checks for errors and warnings', function (): void {
        $bag = new DiagnosticBag;
        $bag->add(Diagnostic::info('info', 0, 1, 'src'));

        expect($bag->hasErrors())->toBeFalse()
            ->and($bag->hasWarnings())->toBeFalse();

        $bag->add(Diagnostic::warning('warn', 0, 1, 'src'));

        expect($bag->hasErrors())->toBeFalse()
            ->and($bag->hasWarnings())->toBeTrue();

        $bag->add(Diagnostic::error('error', 0, 1, 'src'));

        expect($bag->hasErrors())->toBeTrue();
    });

    it('filters by severity type', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'src'),
            Diagnostic::error('e2', 2, 3, 'src'),
            Diagnostic::warning('w1', 4, 5, 'src'),
            Diagnostic::info('i1', 6, 7, 'src'),
            Diagnostic::hint('h1', 8, 9, 'src'),
        ]);

        expect($bag->errors())->toHaveCount(2)
            ->and($bag->warnings())->toHaveCount(1)
            ->and($bag->info())->toHaveCount(1)
            ->and($bag->hints())->toHaveCount(1);
    });

    it('filters by minimum severity', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'src'),
            Diagnostic::warning('w1', 2, 3, 'src'),
            Diagnostic::info('i1', 4, 5, 'src'),
            Diagnostic::hint('h1', 6, 7, 'src'),
        ]);

        $atLeastWarning = $bag->atLeast(DiagnosticSeverity::Warning);
        $atLeastInfo = $bag->atLeast(DiagnosticSeverity::Info);

        expect($atLeastWarning)->toHaveCount(2)
            ->and($atLeastInfo)->toHaveCount(3);
    });

    it('filters by source extension', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'ext-a'),
            Diagnostic::error('e2', 2, 3, 'ext-a'),
            Diagnostic::error('e3', 4, 5, 'ext-b'),
        ]);

        expect($bag->fromSource('ext-a'))->toHaveCount(2)
            ->and($bag->fromSource('ext-b'))->toHaveCount(1)
            ->and($bag->fromSource('ext-c'))->toHaveCount(0);
    });

    it('filters by position range', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 5, 10, 'src'),
            Diagnostic::error('e2', 15, 20, 'src'),
            Diagnostic::error('e3', 25, 30, 'src'),
        ]);

        $inRange = $bag->inRange(0, 25);

        expect($inRange)->toHaveCount(2);
    });

    it('sorts by position', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e3', 30, 35, 'src'),
            Diagnostic::error('e1', 5, 10, 'src'),
            Diagnostic::error('e2', 15, 20, 'src'),
        ]);

        $bag->sortByPosition();
        $all = $bag->all();

        expect($all[0]->message)->toBe('e1')
            ->and($all[1]->message)->toBe('e2')
            ->and($all[2]->message)->toBe('e3');
    });

    it('sorts by severity', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::info('info', 0, 1, 'src'),
            Diagnostic::error('error', 0, 1, 'src'),
            Diagnostic::warning('warning', 0, 1, 'src'),
        ]);

        $bag->sortBySeverity();
        $all = $bag->all();

        expect($all[0]->isError())->toBeTrue()
            ->and($all[1]->isWarning())->toBeTrue()
            ->and($all[2]->isInfo())->toBeTrue();
    });

    it('groups by source', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'ext-a'),
            Diagnostic::error('e2', 2, 3, 'ext-b'),
            Diagnostic::error('e3', 4, 5, 'ext-a'),
        ]);

        $grouped = $bag->groupBySource();

        expect($grouped)->toHaveCount(2)
            ->and($grouped['ext-a'])->toHaveCount(2)
            ->and($grouped['ext-b'])->toHaveCount(1);
    });

    it('clears all diagnostics', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'src'),
            Diagnostic::error('e2', 2, 3, 'src'),
        ]);

        expect($bag->count())->toBe(2);

        $bag->clear();

        expect($bag->count())->toBe(0)
            ->and($bag->isEmpty())->toBeTrue();
    });

    it('is iterable', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('e1', 0, 1, 'src'),
            Diagnostic::error('e2', 2, 3, 'src'),
        ]);

        $all = iterator_to_array($bag);
        expect($all)->toHaveCount(2)
            ->and($all[0])->toBeInstanceOf(Diagnostic::class)
            ->and($all[1])->toBeInstanceOf(Diagnostic::class);
    });

    it('formats report', function (): void {
        $bag = new DiagnosticBag;
        $bag->addMany([
            Diagnostic::error('Error message', 0, 5, 'my-ext'),
            Diagnostic::warning('Warning message', 10, 15, 'my-ext'),
        ]);

        $report = $bag->format();

        expect($report)->toContain('error')
            ->and($report)->toContain('Error message')
            ->and($report)->toContain('warning')
            ->and($report)->toContain('Warning message');
    });

    it('formats report with source for line numbers', function (): void {
        $source = "line1\nline2\nline3";
        $bag = new DiagnosticBag;
        $bag->add(Diagnostic::error('Error on line 2', 6, 11, 'src'));

        $report = $bag->format($source);

        expect($report)->toContain('2:1');
    });
});
