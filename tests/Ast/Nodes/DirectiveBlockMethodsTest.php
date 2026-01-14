<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;

describe('DirectiveBlockNode Methods', function (): void {
    describe('isDirectiveNamed()', function (): void {
        it('matches directive name case-insensitively', function (): void {
            $doc = $this->parse('@if(true) content @endif');
            $block = $doc->getBlockDirectives()->first();

            expect($block)->toBeInstanceOf(DirectiveBlockNode::class)
                ->and($block->isDirectiveNamed('if'))->toBeTrue()
                ->and($block->isDirectiveNamed('IF'))->toBeTrue()
                ->and($block->isDirectiveNamed('foreach'))->toBeFalse();
        });
    });

    describe('isFor()', function (): void {
        it('returns true for @for blocks', function (): void {
            $doc = $this->parse('@for($i = 0; $i < 10; $i++) content @endfor');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isFor())->toBeTrue()
                ->and($block->isForeach())->toBeFalse();
        });
    });

    describe('isWhile()', function (): void {
        it('returns true for @while blocks', function (): void {
            $doc = $this->parse('@while($condition) content @endwhile');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isWhile())->toBeTrue();
        });
    });

    describe('isUnless()', function (): void {
        it('returns true for @unless blocks', function (): void {
            $doc = $this->parse('@unless($condition) content @endunless');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isUnless())->toBeTrue();
        });
    });

    describe('isSwitch()', function (): void {
        it('returns true for @switch blocks', function (): void {
            $doc = $this->parse('@switch($value) @case(1) one @break @default default @endswitch');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isSwitch())->toBeTrue();
        });
    });

    describe('existing methods still work', function (): void {
        it('isIf() returns true for @if blocks', function (): void {
            $doc = $this->parse('@if($condition) content @endif');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isIf())->toBeTrue();
        });

        it('isForeach() returns true for @foreach blocks', function (): void {
            $doc = $this->parse('@foreach($items as $item) {{ $item }} @endforeach');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isForeach())->toBeTrue();
        });

        it('isForelse() returns true for @forelse blocks', function (): void {
            $doc = $this->parse('@forelse($items as $item) {{ $item }} @empty No items @endforelse');
            $block = $doc->getBlockDirectives()->first();

            expect($block->isForelse())->toBeTrue();
        });
    });
});
