<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Parser\ParserOptions;

describe('Directives In Attributes', function (): void {
    test('simple directive as attribute', function (): void {
        $blade = '<div @csrf([])></div>';

        $doc = $this->parse($blade);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $element = $children[0]->asElement();
        $attributes = $element->attributes();

        $directives = $attributes->directives()->getBladeConstructs()->all();
        $blockDirectives = $attributes->blockDirectives()->getBladeConstructs()->all();

        expect($directives)->toHaveCount(1)
            ->and($blockDirectives)->toHaveCount(0);

        $directive = $directives[0]->asDirective();
        expect($directive->nameText())->toBe('csrf')
            ->and($directive->arguments())->toBe('([])')
            ->and($doc->render())->toBe($blade);
    });

    test('non-directive text content does not get lost', function (): void {
        $blade = '<div @not-a-directive([])></div>';

        $doc = $this->parse($blade);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class)
            ->and($doc->render())->toBe($blade);
    });

    test('block directive as attribute', function (): void {
        $blade = <<<'BLADE'
<div @if($show)class="visible"@endif></div>
BLADE;
        $doc = Document::parse($blade, ParserOptions::make()->acceptAllDirectives());
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $element = $children[0]->asElement();
        $attributes = $element->attributes();

        $blockDirectives = $attributes->blockDirectives()->getBladeConstructs()->all();
        $directives = $attributes->directives()->getBladeConstructs()->all();

        expect($blockDirectives)->toHaveCount(1)
            ->and($directives)->toHaveCount(0);

        $ifBlock = $blockDirectives[0];
        expect($ifBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($ifBlock->nameText())->toBe('if')
            ->and($doc->render())->toBe($blade);
    });

    test('switch directive in attribute with nested cases', function (): void {
        $blade = <<<'BLADE'
<input
@switch($type)
    @case('text')
        type="text"
        @break
    @case('email')
        type="email"
        @break
    @default
        type="text"
@endswitch
>
BLADE;
        $doc = Document::parse($blade, ParserOptions::make()->acceptAllDirectives());
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $element = $children[0]->asElement();
        $attributes = $element->attributes();

        $blockDirectives = $attributes->blockDirectives()->getBladeConstructs()->all();
        $directives = $attributes->directives()->getBladeConstructs()->all();

        expect($blockDirectives)->toHaveCount(1)
            ->and($directives)->toHaveCount(0);

        $switchBlock = $blockDirectives[0]->asDirectiveBlock();
        expect($switchBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($switchBlock->nameText())->toBe('switch')
            ->and($doc->render())->toBe($blade);
    });
});
