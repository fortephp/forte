<?php

declare(strict_types=1);

use Forte\Lexer\Lexer;
use Forte\Parser\TreeBuilder;

describe('Stack Depth Limits', function (): void {
    describe('Element depth limits', function (): void {
        test('parser handles normal nesting within limits', function (): void {
            $html = str_repeat('<div>', 10).'content'.str_repeat('</div>', 10);

            $lexer = new Lexer($html);
            $result = $lexer->tokenize();
            $builder = new TreeBuilder($result->tokens, $html);

            $parsed = $builder->build();

            expect($parsed['nodes'])->not->toBeEmpty();
        });

        test('parser throws exception when element depth limit exceeded', function (): void {
            $depth = 600;
            $html = str_repeat('<div>', $depth).'content'.str_repeat('</div>', $depth);

            $lexer = new Lexer($html);
            $result = $lexer->tokenize();
            $builder = new TreeBuilder($result->tokens, $html);

            expect(fn () => $builder->build())
                ->toThrow(RuntimeException::class, 'Maximum element nesting depth');
        });

        test('custom depth limit can be configured', function (): void {
            $html = str_repeat('<div>', 20).'content'.str_repeat('</div>', 20);

            $lexer = new Lexer($html);
            $result = $lexer->tokenize();
            $builder = new TreeBuilder($result->tokens, $html);
            $builder->setDepthLimits(elements: 10);

            expect(fn () => $builder->build())
                ->toThrow(RuntimeException::class, 'Maximum element nesting depth');
        });

        test('high custom limit allows deep nesting', function (): void {
            $depth = 600;
            $html = str_repeat('<div>', $depth).'content'.str_repeat('</div>', $depth);

            $lexer = new Lexer($html);
            $result = $lexer->tokenize();
            $builder = new TreeBuilder($result->tokens, $html);
            $builder->setDepthLimits(elements: 1000);

            $parsed = $builder->build();
            expect($parsed['nodes'])->not->toBeEmpty();
        });
    });

    describe('Directive depth limits', function (): void {
        test('parser handles normal directive nesting', function (): void {
            $blade = '@foreach($items as $item)'.
                     '@foreach($item->children as $child)'.
                     '{{ $child }}'.
                     '@endforeach'.
                     '@endforeach';

            $doc = $this->parse($blade);
            expect($doc->getChildren())->not->toBeEmpty();
        });

        test('parser throws exception when directive depth limit exceeded', function (): void {
            $depth = 300;
            $blade = str_repeat('@foreach($items as $item)', $depth).
                     '{{ $value }}'.
                     str_repeat('@endforeach', $depth);

            $lexer = new Lexer($blade);
            $result = $lexer->tokenize();
            $builder = new TreeBuilder($result->tokens, $blade);

            expect(fn () => $builder->build())
                ->toThrow(RuntimeException::class, 'depth');
        });
    });

    describe('Condition depth limits', function (): void {
        test('parser handles normal condition nesting', function (): void {
            $blade = '@if($a) @if ($b) @if($c) content @endif @endif @endif';

            $doc = $this->parse($blade);
            expect($doc->getChildren())->not->toBeEmpty()
                ->and($doc->render())->toBe($blade);
        });

        test('parser throws exception when condition depth limit exceeded', function (): void {
            $depth = 300;
            $blade = str_repeat('@if($x)', $depth).
                     'content'.
                     str_repeat('@endif', $depth);

            $lexer = new Lexer($blade);
            $result = $lexer->tokenize();
            $builder = new TreeBuilder($result->tokens, $blade);

            expect(fn () => $builder->build())
                ->toThrow(RuntimeException::class, 'depth');
        });
    });
});
