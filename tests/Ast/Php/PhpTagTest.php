<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\PhpTagNode;

describe('PHP Tag Parsing', function (): void {
    test('basic PHP tag with content', function (): void {
        $source = <<<'SOURCE'
<?php
$var = 'value';
?>
SOURCE;

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpTagNode::class)
            ->and($children[0]->asPhpTag()->content())->toBe("\n\$var = 'value';\n")
            ->and($children[0]->asPhpTag()->phpType())->toBe('php')
            ->and($children[0]->asPhpTag()->hasClose())->toBeTrue()
            ->and($children[0]->render())->toBe($source);
    });

    test('short echo PHP tag', function (): void {
        $source = '<?= $var ?>';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpTagNode::class)
            ->and($children[0]->asPhpTag()->content())->toBe(' $var ')
            ->and($children[0]->asPhpTag()->phpType())->toBe('echo')
            ->and($children[0]->asPhpTag()->hasClose())->toBeTrue()
            ->and($children[0]->render())->toBe($source);
    });

    test('PHP tag without closing tag', function (): void {
        $source = "<?php\necho 'test';";

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpTagNode::class)
            ->and($children[0]->asPhpTag()->content())->toBe("\necho 'test';")
            ->and($children[0]->asPhpTag()->phpType())->toBe('php')
            ->and($children[0]->asPhpTag()->hasClose())->toBeFalse()
            ->and($children[0]->render())->toBe($source);
    });

    test('empty PHP tag', function (): void {
        $source = '<?php?>';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(PhpTagNode::class)
            ->and($children[0]->asPhpTag()->content())->toBe('')
            ->and($children[0]->asPhpTag()->phpType())->toBe('php')
            ->and($children[0]->asPhpTag()->hasClose())->toBeTrue()
            ->and($children[0]->render())->toBe($source);
    });

    test('PHP tag mixed with HTML', function (): void {
        $source = '<div><?php echo $var; ?></div>';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class);

        $div = $children[0];
        $divChildren = $div->getChildren();

        expect($divChildren)->toHaveCount(1)
            ->and($divChildren[0])->toBeInstanceOf(PhpTagNode::class)
            ->and($divChildren[0]->asPhpTag()->content())->toBe(' echo $var; ')
            ->and($divChildren[0]->asPhpTag()->hasClose())->toBeTrue();
    });

    test('multiple PHP tags', function (): void {
        $source = '<?php $a = 1; ?> <?= $a ?>';

        $doc = Document::parse($source);
        $phpTags = $doc->getChildrenOfType(PhpTagNode::class);

        expect($phpTags)->toHaveCount(2)
            ->and($phpTags[0]->phpType())->toBe('php')
            ->and($phpTags[1]->phpType())->toBe('echo');
    });
});
