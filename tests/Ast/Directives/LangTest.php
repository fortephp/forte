<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\TextNode;

describe('Lang Directive', function (): void {
    it('handles unpaired lang directive with array argument without infinite loop', function (): void {
        $input = "@lang(['something'])";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        $langDirective = $children[0]->asDirective();
        expect($children)->toHaveCount(1)
            ->and($langDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($langDirective->nameText())->toBe('lang')
            ->and($langDirective->arguments())->toBe("(['something'])")
            ->and($doc->render())->toBe($input);
    });

    it('handles properly paired lang directive', function (): void {
        $input = "@lang(['key']) content here @endlang";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        expect($block->nameText())->toBe('lang')
            ->and($block->endDirective())->not->toBeNull()
            ->and($block->endDirective()->nameText())->toBe('endlang')
            ->and(count($block->getChildren()))->toBeGreaterThan(1)
            ->and($doc->render())->toBe($input);
    });

    it('handles unpaired lang directive with simple string argument', function (): void {
        $input = "@lang('messages.welcome')";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        $langDirective = $children[0]->asDirective();
        expect($children)->toHaveCount(1)
            ->and($langDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($langDirective->nameText())->toBe('lang')
            ->and($langDirective->arguments())->toBe("('messages.welcome')")
            ->and($doc->render())->toBe($input);
    });

    it('handles lang directive without arguments', function (): void {
        $input = '@lang';

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        $langDirective = $children[0]->asDirective();
        expect($children)->toHaveCount(1)
            ->and($langDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($langDirective->nameText())->toBe('lang')
            ->and($langDirective->hasArguments())->toBeFalse()
            ->and($doc->render())->toBe($input);
    });

    test('nested content in paired lang directive', function (): void {
        $input = "@lang(['key']) Hello {{ \$name }} @endlang";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $children[0]->asDirectiveBlock();
        $startDirective = $block->startDirective();

        expect($startDirective->getChildren())->toHaveCount(3)
            ->and($doc->render())->toBe($input);
    });

    it('handles multiple unpaired lang directives without infinite loop', function (): void {
        $input = "@lang(['key1']) @lang(['key2'])";

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        $langDirective1 = $children[0]->asDirective();
        $langDirective2 = $children[2]->asDirective();

        expect($children)->toHaveCount(3)
            ->and($langDirective1)->toBeInstanceOf(DirectiveNode::class)
            ->and($langDirective1->nameText())->toBe('lang')
            ->and($children[1])->toBeInstanceOf(TextNode::class)
            ->and($langDirective2)->toBeInstanceOf(DirectiveNode::class)
            ->and($langDirective2->nameText())->toBe('lang')
            ->and($doc->render())->toBe($input);
    });
});
