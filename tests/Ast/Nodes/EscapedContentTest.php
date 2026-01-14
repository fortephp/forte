<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\EscapeNode;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\TextNode;

describe('Escaped Blade Content', function (): void {
    test('blade escaped content renders correctly', function (): void {
        $template = <<<'EOT'
@{{ $variable }}
@{!! $variable !!}
@@directive
@{{ $var
@{{{ $variable }}}
EOT;

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('escaped echo produces EscapeNode and TextNode', function (): void {
        $template = '@{{ $variable }}';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $escape = $nodes[0]->asEscape();
        $text = $nodes[1]->asText();

        expect($nodes)->toHaveCount(2)
            ->and($escape)->toBeInstanceOf(EscapeNode::class)
            ->and($escape->content())->toBe('@')
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe('{{ $variable }}')
            ->and($doc->render())->toBe($template);
    });

    test('escaped directive produces EscapeNode and TextNode', function (): void {
        $template = '@@directive';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $escape = $nodes[0]->asEscape();
        $text = $nodes[1]->asText();

        expect($nodes)->toHaveCount(2)
            ->and($escape)->toBeInstanceOf(EscapeNode::class)
            ->and($escape->content())->toBe('@')
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe('@directive')
            ->and($doc->render())->toBe($template);
    });

    test('nested escaped content', function (): void {
        $template = <<<'EOT'
@php
    $arrayOne = [];
    $arrayTwo = [];
@endphp

@foreach($arrayOne as $val)
    @if($val == 'something')
        <div></div>
    @elseif($val == 'somethingElse')
        @@foreach($arrayTwo as $aDifferentValue)
            <div></div>
        @@endforeach
    @else
        <div></div>
    @endif
@endforeach
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $phpBlock = $nodes[0]->asPhpBlock();
        $text = $nodes[1]->asText();
        $foreachBlock = $nodes[2]->asDirectiveBlock();

        expect($nodes)->toHaveCount(3)
            ->and($phpBlock)->toBeInstanceOf(PhpBlockNode::class)
            ->and($phpBlock->content())->toBe("\n    \$arrayOne = [];\n    \$arrayTwo = [];\n")
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text->getContent()))->toBeEmpty()
            ->and($foreachBlock)->toBeInstanceOf(DirectiveBlockNode::class);

        $blockNodes = $foreachBlock->getChildren();
        $foreachDirective = $blockNodes[0]->asDirective();
        $endforeachDirective = $blockNodes[1]->asDirective();

        expect($blockNodes)->toHaveCount(2)
            ->and($foreachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($foreachDirective->nameText())->toBe('foreach')
            ->and($foreachDirective->arguments())->toBe('($arrayOne as $val)')
            ->and($endforeachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endforeachDirective->nameText())->toBe('endforeach');

        $foreachNodes = $foreachDirective->getChildren();
        $ifBlock = $foreachNodes[1]->asDirectiveBlock();

        expect($foreachNodes)->toHaveCount(3)
            ->and($foreachNodes[0])->toBeInstanceOf(TextNode::class)
            ->and($ifBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($foreachNodes[2])->toBeInstanceOf(TextNode::class);

        $ifNodes = $ifBlock->getChildren();
        $ifDirective = $ifNodes[0]->asDirective();
        $elseifDirective = $ifNodes[1]->asDirective();
        $elseDirective = $ifNodes[2]->asDirective();
        $endifDirective = $ifNodes[3]->asDirective();

        expect($ifNodes)->toHaveCount(4)
            ->and($ifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($ifDirective->nameText())->toBe('if')
            ->and($ifDirective->arguments())->toBe("(\$val == 'something')")
            ->and($elseifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseifDirective->nameText())->toBe('elseif')
            ->and($elseifDirective->arguments())->toBe("(\$val == 'somethingElse')")
            ->and($elseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseDirective->nameText())->toBe('else')
            ->and($endifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endifDirective->nameText())->toBe('endif');

        $nodes = collect($elseifDirective->getChildren());

        expect($nodes->contains(fn ($n) => $n instanceof ElementNode && $n->tagNameText() === 'div'))->toBeTrue('elseif branch should contain a div element')
            ->and($nodes->contains(fn ($n) => $n instanceof EscapeNode))->toBeTrue('elseif branch should contain escaped content')
            ->and($doc->render())->toBe($template);
    });

    test('basic directives escaped output', function ($name): void {
        $template = '@@'.$name;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $escape = $nodes[0]->asEscape();
        $text = $nodes[1]->asText();

        expect($nodes)->toHaveCount(2)
            ->and($escape)->toBeInstanceOf(EscapeNode::class)
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe('@'.$name)
            ->and($doc->render())->toBe($template);
    })->with('simple directives');
});
