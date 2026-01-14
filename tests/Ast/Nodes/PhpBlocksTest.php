<?php

declare(strict_types=1);

use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;

describe('PHP Blocks (@php...@endphp)', function (): void {
    test('php blocks do not consume literal characters', function (): void {
        $template = "start @php\n \n \n@endphp end";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $text1 = $nodes[0]->asText();
        $phpBlock = $nodes[1]->asPhpBlock();
        $text2 = $nodes[2]->asText();

        expect($nodes)->toHaveCount(3)
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($text1->getContent())->toBe('start ')
            ->and($phpBlock)->toBeInstanceOf(PhpBlockNode::class)
            ->and($phpBlock->content())->toBe("\n \n \n")
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->getContent())->toBe(' end')
            ->and($doc->render())->toBe($template);
    });

    test('many opening php block directives', function (): void {
        $template = "@php @php @php \$counter++;\n@endphp";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $phpBlock = $nodes[0]->asPhpBlock();
        expect($nodes)->toHaveCount(1)
            ->and($phpBlock)->toBeInstanceOf(PhpBlockNode::class)
            ->and($phpBlock->content())->toBe(" @php @php \$counter++;\n")
            ->and($doc->render())->toBe($template);
    });

    test('neighboring php block directives', function (): void {
        $template = "@php\n    \$counter += 1;\n@endphp @php\n    \$counter += 2;\n@endphp";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $php1 = $nodes[0]->asPhpBlock();
        $text = $nodes[1]->asText();
        $php2 = $nodes[2]->asPhpBlock();

        expect($nodes)->toHaveCount(3)
            ->and($php1)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php1->content())->toBe("\n    \$counter += 1;\n")
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe(' ')
            ->and($php2)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php2->content())->toBe("\n    \$counter += 2;\n")
            ->and($doc->render())->toBe($template);
    });

    test('detached php block directives with valid php blocks', function (): void {
        $template = "@php @php\n\$counter += 1;\n@endphp @php\n\$counter += 2;\n@endphp @php @php @php @php \$counter += 3; @endphp";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $php1 = $nodes[0]->asPhpBlock();
        $text1 = $nodes[1]->asText();
        $php2 = $nodes[2]->asPhpBlock();
        $text2 = $nodes[3]->asText();
        $php3 = $nodes[4]->asPhpBlock();

        expect($nodes)->toHaveCount(5)
            ->and($php1)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php1->content())->toBe(" @php\n\$counter += 1;\n")
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($text1->getContent())->toBe(' ')
            ->and($php2)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php2->content())->toBe("\n\$counter += 2;\n")
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->getContent())->toBe(' ')
            ->and($php3)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php3->content())->toBe(' @php @php @php $counter += 3; ')
            ->and($doc->render())->toBe($template);
    });

    test('php blocks containing loops', function (): void {
        $template = "@php \$counter++;\nfor(\$i = 0; \$i++;$=) {}\n@endphp @php \$counter_two++;\nfor(\$i = 0; \$i++;$=two) {}\n@endphp";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $php1 = $nodes[0]->asPhpBlock();
        $text = $nodes[1]->asText();
        $php2 = $nodes[2]->asPhpBlock();

        expect($nodes)->toHaveCount(3)
            ->and($php1)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php1->content())->toBe(" \$counter++;\nfor(\$i = 0; \$i++;$=) {}\n")
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe(' ')
            ->and($php2)->toBeInstanceOf(PhpBlockNode::class)
            ->and($php2->content())->toBe(" \$counter_two++;\nfor(\$i = 0; \$i++;$=two) {}\n")
            ->and($doc->render())->toBe($template);
    });

    test('basic php tags', function (): void {
        $template = "<?php\n    \$variable = 'value';\n?>";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $phpTag = $nodes[0]->asPhpTag();
        expect($nodes)->toHaveCount(1)
            ->and($phpTag)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpTag->isPhpTag())->toBeTrue()
            ->and($phpTag->content())->toBe("\n    \$variable = 'value';\n")
            ->and($doc->render())->toBe($template);
    });

    test('php tags neighboring text nodes', function (): void {
        $template = "start<?php\n    \$variable = 'value';\n?>end";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $text1 = $nodes[0]->asText();
        $phpTag = $nodes[1]->asPhpTag();
        $text2 = $nodes[2]->asText();

        expect($nodes)->toHaveCount(3)
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($text1->getContent())->toBe('start')
            ->and($phpTag)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpTag->isPhpTag())->toBeTrue()
            ->and($phpTag->content())->toBe("\n    \$variable = 'value';\n")
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->getContent())->toBe('end')
            ->and($doc->render())->toBe($template);
    });

    test('echo php tag', function (): void {
        $template = 'start<?= $variable ?>end';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $text1 = $nodes[0]->asText();
        $phpTag = $nodes[1]->asPhpTag();
        $text2 = $nodes[2]->asText();

        expect($nodes)->toHaveCount(3)
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($text1->getContent())->toBe('start')
            ->and($phpTag)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpTag->isShortEcho())->toBeTrue()
            ->and($phpTag->content())->toBe(' $variable ')
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->getContent())->toBe('end')
            ->and($doc->render())->toBe($template);
    });

    test('mixed php tag types', function (): void {
        $template = "start<?php \$variable = 'value'; ?>inner<?= \$variable ?>end";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $text1 = $nodes[0]->asText();
        $phpTag1 = $nodes[1]->asPhpTag();
        $text2 = $nodes[2]->asText();
        $phpTag2 = $nodes[3]->asPhpTag();
        $text3 = $nodes[4]->asText();

        expect($nodes)->toHaveCount(5)
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($text1->getContent())->toBe('start')
            ->and($phpTag1)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpTag1->isPhpTag())->toBeTrue()
            ->and($phpTag1->content())->toBe(" \$variable = 'value'; ")
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->getContent())->toBe('inner')
            ->and($phpTag2)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpTag2->isShortEcho())->toBeTrue()
            ->and($phpTag2->content())->toBe(' $variable ')
            ->and($text3)->toBeInstanceOf(TextNode::class)
            ->and($text3->getContent())->toBe('end')
            ->and($doc->render())->toBe($template);
    });

    test('php tags do not consume literal characters', function (): void {
        $template = "start <?php\n \n \n?> end";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $text1 = $nodes[0]->asText();
        $phpTag = $nodes[1]->asPhpTag();
        $text2 = $nodes[2]->asText();

        expect($nodes)->toHaveCount(3)
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($text1->getContent())->toBe('start ')
            ->and($phpTag)->toBeInstanceOf(PhpTagNode::class)
            ->and($phpTag->isPhpTag())->toBeTrue()
            ->and($phpTag->content())->toBe("\n \n \n")
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->getContent())->toBe(' end')
            ->and($doc->render())->toBe($template);
    });

    test('php tag has close detection', function (): void {
        $fullClosed = $this->parse('<?php echo 1; ?>');
        $fullUnclosed = $this->parse('<?php echo 1; ');
        $echoClosed = $this->parse('<?= $var ?>');
        $echoUnclosed = $this->parse('<?= $var ');

        $closedNode = $fullClosed->firstChild()->asPhpTag();
        $unclosedNode = $fullUnclosed->firstChild()->asPhpTag();
        $echoClosedNode = $echoClosed->firstChild()->asPhpTag();
        $echoUnclosedNode = $echoUnclosed->firstChild()->asPhpTag();

        expect($closedNode)->toBeInstanceOf(PhpTagNode::class)
            ->and($closedNode->hasClose())->toBeTrue()
            ->and($unclosedNode)->toBeInstanceOf(PhpTagNode::class)
            ->and($unclosedNode->hasClose())->toBeFalse()
            ->and($echoClosedNode)->toBeInstanceOf(PhpTagNode::class)
            ->and($echoClosedNode->hasClose())->toBeTrue()
            ->and($echoUnclosedNode)->toBeInstanceOf(PhpTagNode::class)
            ->and($echoUnclosedNode->hasClose())->toBeFalse();
    });

    test('closed php block', function (): void {
        $template = '@php echo 1; @endphp';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $phpBlock = $nodes[0]->asPhpBlock();
        expect($nodes)->toHaveCount(1)
            ->and($phpBlock)->toBeInstanceOf(PhpBlockNode::class)
            ->and($phpBlock->hasClose())->toBeTrue();
    });

    test('unclosed php block', function (): void {
        $template = '@php echo 1;';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $phpBlock = $nodes[0]->asPhpBlock();
        expect($nodes)->toHaveCount(1)
            ->and($phpBlock)->toBeInstanceOf(PhpBlockNode::class)
            ->and($phpBlock->hasClose())->toBeFalse();
    });
});
