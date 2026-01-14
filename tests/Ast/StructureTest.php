<?php

declare(strict_types=1);

use Forte\Ast\DoctypeNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\NodeKind;

describe('General AST Structure', function (): void {
    it('parses complete HTML structure with precise assertions', function (): void {
        $html = <<<'HTML'
<!doctype html>
<html>
    <head>
        <title> Some Text </title>
    </head>
</html>
HTML;

        $doc = $this->parse($html);
        $rootChildren = $doc->getChildren();

        expect($rootChildren)->toHaveCount(3);

        $doctype = $rootChildren[0]->asDoctype();
        expect($doctype)->toBeInstanceOf(DoctypeNode::class)
            ->and($doctype->getDocumentContent())->toBe('<!doctype html>');

        $whitespace1 = $rootChildren[1];
        expect($whitespace1)->toBeInstanceOf(TextNode::class)
            ->and($whitespace1->getDocumentContent())->toBe("\n");

        $html = $rootChildren[2]->asElement();
        expect($html)->toBeInstanceOf(ElementNode::class)
            ->and($html->tagNameText())->toBe('html')
            ->and($html->isSelfClosing())->toBeFalse()
            ->and($html->isPaired())->toBeTrue()
            ->and((string) $html->closingTag())->toBe('</html>');

        $htmlChildren = $html->getChildren();

        expect($htmlChildren)->toHaveCount(3);

        $whitespace2 = $htmlChildren[0];
        expect($whitespace2)->toBeInstanceOf(TextNode::class)
            ->and($whitespace2->getDocumentContent())->toBe("\n    ");

        $head = $htmlChildren[1]->asElement();
        expect($head)->toBeInstanceOf(ElementNode::class)
            ->and($head->tagNameText())->toBe('head')
            ->and($head->isPaired())->toBeTrue()
            ->and((string) $head->closingTag())->toBe('</head>');

        $whitespace3 = $htmlChildren[2];
        expect($whitespace3)->toBeInstanceOf(TextNode::class)
            ->and($whitespace3->getDocumentContent())->toBe("\n");

        $headChildren = $head->getChildren();

        expect($headChildren)->toHaveCount(3);

        $whitespace4 = $headChildren[0];
        expect($whitespace4)->toBeInstanceOf(TextNode::class)
            ->and($whitespace4->getDocumentContent())->toBe("\n        ");

        $title = $headChildren[1]->asElement();
        expect($title)->toBeInstanceOf(ElementNode::class)
            ->and($title->tagNameText())->toBe('title')
            ->and($title->isPaired())->toBeTrue()
            ->and((string) $title->closingTag())->toBe('</title>');

        $whitespace5 = $headChildren[2];
        expect($whitespace5)->toBeInstanceOf(TextNode::class)
            ->and($whitespace5->getDocumentContent())->toBe("\n    ");

        $titleChildren = $title->getChildren();

        expect($titleChildren)->toHaveCount(1);

        $titleText = $titleChildren[0];
        expect($titleText)->toBeInstanceOf(TextNode::class)
            ->and($titleText->getDocumentContent())->toBe(' Some Text ')
            ->and($doctype->startOffset())->toBe(0)
            ->and($doctype->endOffset())->toBe(15);

        $htmlStart = $html->startOffset();
        $htmlEnd = $html->endOffset();
        expect(substr((string) $doc->source(), $htmlStart, 6))->toBe('<html>')
            ->and(substr((string) $doc->source(), $htmlEnd - 7, 7))->toBe('</html>');

        $closingTag = $html->closingTag();
        expect($closingTag->startOffset())->toBeLessThan($htmlEnd)
            ->and($closingTag->endOffset())->toBe($htmlEnd)
            ->and($closingTag->length())->toBe(7);

        $children = collect($doc->children());
        expect($children->every(fn ($child) => $child->getFlatNode()['parent'] === 0))->toBeTrue();

        $children->filter(fn ($child) => $child->isElement())->each(function ($child): void {
            $parentIndex = $child->index();
            expect(collect($child->asElement()->getChildren())->every(
                fn ($grandchild) => $grandchild->getFlatNode()['parent'] === $parentIndex
            ))->toBeTrue();
        });
    });

    it('preserves exact whitespace in text nodes', function (): void {
        $html = "<div>\n\t\tindented\n</div>";
        $doc = $this->parse($html);

        $children = $doc->getChildren();
        $div = $children[0]->asElement();
        $divChildren = $div->getChildren();

        expect($divChildren)->toHaveCount(1)
            ->and($divChildren[0]->getDocumentContent())->toBe("\n\t\tindented\n");
    });

    it('handles elements with attributes in structure', function (): void {
        $html = '<div class="foo" id="bar">content</div>';
        $doc = $this->parse($html);

        $children = $doc->getChildren();
        expect($children)->toHaveCount(1);

        $div = $children[0]->asElement();
        expect($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        $attrs = $div->attributes();
        expect($attrs->count())->toBe(2);

        $classAttr = $attrs->get('class');
        expect($classAttr)->not->toBeNull()
            ->and($classAttr->nameText())->toBe('class')
            ->and($classAttr->valueText())->toBe('foo');

        $idAttr = $attrs->get('id');
        expect($idAttr)->not->toBeNull()
            ->and($idAttr->nameText())->toBe('id')
            ->and($idAttr->valueText())->toBe('bar');

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1)
            ->and($divChildren[0]->getDocumentContent())->toBe('content');
    });

    it('handles self-closing elements correctly', function (): void {
        $html = '<div><br /><img src="test.jpg" /></div>';
        $doc = $this->parse($html);

        $children = $doc->getChildren();
        $div = $children[0]->asElement();
        $divChildren = $div->getChildren();

        expect($divChildren)->toHaveCount(2);

        $br = $divChildren[0];
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->asElement()->tagNameText())->toBe('br')
            ->and($br->asElement()->isSelfClosing())->toBeTrue()
            ->and($br->asElement()->isPaired())->toBeFalse()
            ->and($br->asElement()->closingTag())->toBeNull();

        $img = $divChildren[1];
        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->asElement()->tagNameText())->toBe('img')
            ->and($img->asElement()->isSelfClosing())->toBeTrue()
            ->and($img->asElement()->isPaired())->toBeFalse()
            ->and($img->asElement()->getAttribute('src'))->toBe('test.jpg');
    });

    it('handles void elements without explicit self-close', function (): void {
        $html = '<div><br><hr><input type="text"></div>';
        $doc = $this->parse($html);

        $children = $doc->getChildren();
        $div = $children[0]->asElement();
        $divChildren = $div->getChildren();

        expect($divChildren)->toHaveCount(3);

        $br = $divChildren[0]->asElement();
        expect($br->tagNameText())->toBe('br')
            ->and($br->isSelfClosing())->toBeFalse()
            ->and($br->isVoid())->toBeTrue()
            ->and($br->isPaired())->toBeFalse();

        $hr = $divChildren[1]->asElement();
        expect($hr->tagNameText())->toBe('hr')
            ->and($hr->isVoid())->toBeTrue();

        $input = $divChildren[2]->asElement();
        expect($input->tagNameText())->toBe('input')
            ->and($input->isVoid())->toBeTrue()
            ->and($input->getAttribute('type'))->toBe('text');
    });

    it('correctly indexes sibling relationships', function (): void {
        $html = '<ul><li>1</li><li>2</li><li>3</li></ul>';
        $doc = $this->parse($html);

        $children = $doc->getChildren();
        $ul = $children[0]->asElement();
        $items = $ul->getChildren();

        expect($items)->toHaveCount(3);

        $item1Flat = $items[0]->getFlatNode();
        $item2Flat = $items[1]->getFlatNode();
        $item3Flat = $items[2]->getFlatNode();

        expect($item1Flat['nextSibling'])->toBe($items[1]->index())
            ->and($item2Flat['nextSibling'])->toBe($items[2]->index());

        $lastSiblingIdx = $item3Flat['nextSibling'];
        if ($lastSiblingIdx !== -1) {
            $lastSiblingFlat = $doc->getFlatNode($lastSiblingIdx);
            expect($lastSiblingFlat['kind'])->toBe(NodeKind::ClosingElementName);
        }

        expect($items[0]->asElement()->getChildren()[0]->getDocumentContent())->toBe('1')
            ->and($items[1]->asElement()->getChildren()[0]->getDocumentContent())->toBe('2')
            ->and($items[2]->asElement()->getChildren()[0]->getDocumentContent())->toBe('3');
    });

    it('handles deeply nested structures', function (): void {
        $html = '<div><span><a><strong>deep</strong></a></span></div>';
        $doc = $this->parse($html);

        $div = $doc->getChildren()[0]->asElement();
        expect($div->tagNameText())->toBe('div');

        $span = $div->getChildren()[0]->asElement();
        expect($span->tagNameText())->toBe('span');

        $a = $span->getChildren()[0]->asElement();
        expect($a->tagNameText())->toBe('a');

        $strong = $a->getChildren()[0]->asElement();
        expect($strong->tagNameText())->toBe('strong');

        $text = $strong->getChildren()[0];
        expect($text->getDocumentContent())->toBe('deep')
            ->and($strong->getFlatNode()['parent'])->toBe($a->index())
            ->and($a->getFlatNode()['parent'])->toBe($span->index())
            ->and($span->getFlatNode()['parent'])->toBe($div->index())
            ->and($div->getFlatNode()['parent'])->toBe(0);
    });

    it('renders document back to exact source', function (): void {
        $html = <<<'HTML'
<!doctype html>
<html>
    <head>
        <title> Some Text </title>
    </head>
</html>
HTML;

        $doc = $this->parse($html);
        expect($doc->render())->toBe($html);
    });

    it('validates token ranges are contiguous and non-overlapping', function (): void {
        $html = '<div class="foo">bar</div>';
        $doc = $this->parse($html);

        $div = $doc->getChildren()[0]->asElement();

        expect($div->startOffset())->toBe(0)
            ->and($div->endOffset())->toBe(strlen($html));

        $closingTag = $div->closingTag();
        expect($closingTag->endOffset())->toBe(strlen($html))
            ->and((string) $closingTag)->toBe('</div>');
    });
});
