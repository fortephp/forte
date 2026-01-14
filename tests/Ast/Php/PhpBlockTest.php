<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\TextNode;

describe('PHP Block Parsing', function (): void {

    test('parses basic PHP block with content', function (): void {
        $source = '@php $var = 1; @endphp';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpBlockNode::class)
            ->and($children[0]->asPhpBlock()->code())->toBe('$var = 1;')
            ->and($children[0]->asPhpBlock()->isEmpty())->toBeFalse()
            ->and($children[0]->render())->toBe($source);
    });

    test('parses PHP block without closing tag', function (): void {
        $source = '@php $var = 1;';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpBlockNode::class)
            ->and($children[0]->render())->toBe($source);
    });

    test('parses empty PHP block', function (): void {
        $source = '@php@endphp';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpBlockNode::class)
            ->and($children[0]->asPhpBlock()->isEmpty())->toBeTrue()
            ->and($children[0]->render())->toBe($source);
    });

    test('parses PHP block with multiline content', function (): void {
        $source = "@php\n    \$var = 1;\n    echo \$var;\n@endphp";
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpBlockNode::class)
            ->and($children[0]->asPhpBlock()->isEmpty())->toBeFalse()
            ->and($children[0]->render())->toBe($source);
    });

    test('parses PHP block mixed with text', function (): void {
        $source = 'Before @php $var = 1; @endphp After';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('Before ')
            ->and($children[1])->toBeInstanceOf(PhpBlockNode::class)
            ->and($children[1]->asPhpBlock()->code())->toBe('$var = 1;')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[2]->getDocumentContent())->toBe(' After')
            ->and($doc->render())->toBe($source);
    });

    test('ignores blade-like things inside php block', function (): void {
        $source = "@php echo 'I am PHP {{ not Blade }}' @endphp";
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpBlockNode::class)
            ->and($children[0]->asPhpBlock()->code())->toBe("echo 'I am PHP {{ not Blade }}'")
            ->and($children[0]->render())->toBe($source);
    });
});
