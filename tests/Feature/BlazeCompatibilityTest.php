<?php

declare(strict_types=1);

use Forte\Tests\BlazeTestCase;

uses(BlazeTestCase::class);

describe('Blaze', function (): void {
    it('transforms #if on component tags before Blaze compiles them', function (): void {
        $compiler = app('blade.compiler');
        $compiler->setPath(resource_path('views/blaze-if.blade.php'));

        $template = '<x-button #if="$show" label="Conditional" />';
        $compiled = $compiler->compileString($template);

        expect($compiled)->toContain('<?php if($show): ?>')
            ->toContain('<?php endif; ?>')
            ->not->toContain('#if=')
            ->toContain('BlazeFolded');
    });

    it('transforms #foreach on component tags before Blaze compiles them', function (): void {
        $compiler = app('blade.compiler');
        $compiler->setPath(resource_path('views/blaze-foreach.blade.php'));

        $template = '<x-badge #foreach="$items as $item" :color="$item->color">{{ $item->name }}</x-badge>';
        $compiled = $compiler->compileString($template);

        expect($compiled)->toContain('$__currentLoopData = $items;')
            ->and($compiled)->not->toContain('#foreach=')
            ->toContain('_blaze');
    });

    it('transforms combined #foreach and #if on component tags', function (): void {
        $compiler = app('blade.compiler');
        $compiler->setPath(resource_path('views/blaze-combined.blade.php'));

        $template = '<x-badge #foreach="$users as $user" #if="$user->active">{{ $user->name }}</x-badge>';
        $compiled = $compiler->compileString($template);

        expect($compiled)
            ->toContain('$__currentLoopData')
            ->toContain('<?php if($user->active): ?>')
            ->toContain('echo e($user->name)');
    });
});
