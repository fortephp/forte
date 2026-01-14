<?php

declare(strict_types=1);

use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Line and Column Numbers', function (): void {
    test('single line document returns line 1 for all nodes', function (): void {
        $doc = $this->parse('Hello {{ $name }} World');
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(1)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->startLine())->toBe(1)
            ->and($nodes[1]->endLine())->toBe(1)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->startLine())->toBe(1)
            ->and($nodes[2]->endLine())->toBe(1);
    });

    test('multiline document with LF newlines', function (): void {
        $template = "Line 1\nLine 2\nLine 3";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(3);
    });

    test('multiline document with CRLF newlines', function (): void {
        $template = "Line 1\r\nLine 2\r\nLine 3";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(3);
    });

    test('multiline document with CR newlines', function (): void {
        $template = "Line 1\rLine 2\rLine 3";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(3);
    });

    test('nodes on different lines', function (): void {
        $template = "{{ \$one }}\n{{ \$two }}\n{{ \$three }}";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(5)
            ->and($nodes[0])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(1)

            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->startLine())->toBe(1)
            ->and($nodes[1]->endLine())->toBe(1)

            ->and($nodes[2])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2]->startLine())->toBe(2)
            ->and($nodes[2]->endLine())->toBe(2)

            ->and($nodes[3])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3]->startLine())->toBe(2)
            ->and($nodes[3]->endLine())->toBe(2)

            ->and($nodes[4])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[4]->startLine())->toBe(3)
            ->and($nodes[4]->endLine())->toBe(3);
    });

    test('column numbers for single line', function (): void {
        $template = 'AB{{ $x }}CD';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startColumn())->toBe(1)
            ->and($nodes[0]->endColumn())->toBe(2)

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->startColumn())->toBe(3)
            ->and($nodes[1]->endColumn())->toBe(10)

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->startColumn())->toBe(11)
            ->and($nodes[2]->endColumn())->toBe(12);
    });

    test('column numbers with multiline', function (): void {
        $template = "Line1\n  {{ \$x }}";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->startColumn())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(2)
            ->and($nodes[0]->endColumn())->toBe(2)

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->startLine())->toBe(2)
            ->and($nodes[1]->startColumn())->toBe(3)
            ->and($nodes[1]->endLine())->toBe(2)
            ->and($nodes[1]->endColumn())->toBe(10);
    });

    test('element line and column numbers', function (): void {
        $template = "<div>\n  <span>Hi</span>\n</div>";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->startColumn())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(3)
            ->and($nodes[0]->endColumn())->toBe(6);

        $children = $nodes[0]->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[1])->toBeInstanceOf(ElementNode::class)
            ->and($children[1]->startLine())->toBe(2)
            ->and($children[1]->startColumn())->toBe(3)
            ->and($children[1]->endLine())->toBe(2)
            ->and($children[1]->endColumn())->toBe(17);
    });

    test('directive line and column numbers', function (): void {
        $template = "@if(\$x)\n  content\n@endif";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $block = $nodes[0]->asDirectiveBlock();
        $children = $block->getChildren();

        expect($children[0])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[0]->startLine())->toBe(1)
            ->and($children[0]->startColumn())->toBe(1);

        $endifIndex = count($children) - 1;
        expect($children[$endifIndex])->toBeInstanceOf(DirectiveNode::class)
            ->and($children[$endifIndex]->nameText())->toBe('endif')
            ->and($children[$endifIndex]->startLine())->toBe(3)
            ->and($children[$endifIndex]->startColumn())->toBe(1)
            ->and($children[$endifIndex]->endLine())->toBe(3)
            ->and($children[$endifIndex]->endColumn())->toBe(6);
    });

    test('mixed newline styles in same document', function (): void {
        $template = "A\nB\r\nC";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(3);
    });

    test('empty line handling', function (): void {
        $template = "A\n\n\nB";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(4);
    });

    test('node at end of line', function (): void {
        $template = "Text{{ \$x }}\nMore";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(1)
            ->and($nodes[0]->startColumn())->toBe(1)
            ->and($nodes[0]->endColumn())->toBe(4)

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->startLine())->toBe(1)
            ->and($nodes[1]->endLine())->toBe(1)
            ->and($nodes[1]->startColumn())->toBe(5)
            ->and($nodes[1]->endColumn())->toBe(12)

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->startLine())->toBe(1)
            ->and($nodes[2]->endLine())->toBe(2)
            ->and($nodes[2]->startColumn())->toBe(13)
            ->and($nodes[2]->endColumn())->toBe(4);
    });

    test('multiline echo statement', function (): void {
        $template = "A{{\n  \$x\n}}B";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->startLine())->toBe(1)
            ->and($nodes[0]->endLine())->toBe(1)

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->startLine())->toBe(1)
            ->and($nodes[1]->endLine())->toBe(3)
            ->and($nodes[1]->startColumn())->toBe(2)
            ->and($nodes[1]->endColumn())->toBe(2)

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->startLine())->toBe(3)
            ->and($nodes[2]->endLine())->toBe(3);
    });
});
