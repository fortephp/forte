<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\TextNode;

describe('Void Element Behavior', function (): void {
    it('treats uppercase BR as void element', function (): void {
        $template = '<BR>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $br = $nodes[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('BR')
            ->and($br->isPaired())->toBeFalse()
            ->and($br->getChildren())->toHaveCount(0);
    });

    it('treats uppercase HR as void element', function (): void {
        $template = '<HR>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $hr = $nodes[0]->asElement();
        expect($hr)->toBeInstanceOf(ElementNode::class)
            ->and($hr->tagNameText())->toBe('HR')
            ->and($hr->isPaired())->toBeFalse()
            ->and($hr->getChildren())->toHaveCount(0);
    });

    it('treats mixed case IMG as void element', function (): void {
        $template = '<Img src="test.jpg">';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $img = $nodes[0]->asElement();
        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->tagNameText())->toBe('Img')
            ->and($img->isPaired())->toBeFalse()
            ->and($img->getChildren())->toHaveCount(0);
    });

    it('treats all standard void elements as void regardless of case', function (string $tag): void {
        $template = "<{$tag}>";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $element = $nodes[0]->asElement();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe($tag)
            ->and($element->isPaired())->toBeFalse()
            ->and($element->getChildren())->toHaveCount(0);
    })->with([
        'BR',
        'Hr',
        'IMG',
        'Input',
        'META',
        'Link',
        'AREA',
        'Base',
        'COL',
        'Embed',
        'PARAM',
        'Source',
        'TRACK',
        'WBR',
    ]);

    it('allows closing tag for lowercase void elements', function (): void {
        $template = '<br></br>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $br = $nodes[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('br')
            ->and($br->isPaired())->toBeTrue()
            ->and($br->getChildren())->toHaveCount(0);
    });

    it('allows JSX-style closing for uppercase void elements', function (): void {
        $template = '<BR></BR>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $br = $nodes[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('BR')
            ->and($br->isPaired())->toBeTrue()
            ->and($br->getChildren())->toHaveCount(0);
    });

    it('allows JSX-style closing for mixed case void elements', function (): void {
        $template = '<Br></Br>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $br = $nodes[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('Br')
            ->and($br->isPaired())->toBeTrue()
            ->and($br->getChildren())->toHaveCount(0);
    });

    it('allows closing tag for lowercase void elements with attributes', function (): void {
        $template = '<img src="test.jpg"></img>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $img = $nodes[0]->asElement();
        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->tagNameText())->toBe('img')
            ->and($img->isPaired())->toBeTrue()
            ->and($img->getChildren())->toHaveCount(0);

        $attributes = $img->attributes()->all();
        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->nameText())->toBe('src');
    });

    it('allows JSX-style closing for uppercase void elements with attributes', function (): void {
        $template = '<IMG src="test.jpg"></IMG>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $img = $nodes[0]->asElement();
        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->tagNameText())->toBe('IMG')
            ->and($img->isPaired())->toBeTrue()
            ->and($img->getChildren())->toHaveCount(0);
    });

    it('treats uppercase void element without closing as void', function (): void {
        $template = '<div><BR>text</div>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(2);

        $br = $divChildren[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('BR')
            ->and($br->isPaired())->toBeFalse()
            ->and($br->getChildren())->toHaveCount(0);

        $text = $divChildren[1]->asText();
        expect($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe('text');
    });

    it('does not treat uppercase void element children as children when no closing tag', function (): void {
        $template = '<BR>should not be child';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $br = $nodes[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('BR')
            ->and($br->isPaired())->toBeFalse()
            ->and($br->getChildren())->toHaveCount(0);

        $text = $nodes[1]->asText();
        expect($text)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text->getContent()))->toBe('should not be child');
    });

    it('handles multiple uppercase void elements in sequence', function (): void {
        $template = '<BR><HR><IMG src="test.jpg">';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class)
            ->and($nodes[0]->isPaired())->toBeFalse()
            ->and($nodes[0]->getChildren())->toHaveCount(0)
            ->and($nodes[1])->toBeInstanceOf(ElementNode::class)
            ->and($nodes[1]->isPaired())->toBeFalse()
            ->and($nodes[1]->getChildren())->toHaveCount(0)
            ->and($nodes[2])->toBeInstanceOf(ElementNode::class)
            ->and($nodes[2]->isPaired())->toBeFalse()
            ->and($nodes[2]->getChildren())->toHaveCount(0);
    });

    it('handles mixed case void elements correctly', function (): void {
        $template = '<div><br><BR><Br></div>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(3)
            ->and($divChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[0]->isPaired())->toBeFalse()
            ->and($divChildren[0]->getChildren())->toHaveCount(0)
            ->and($divChildren[1])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[1]->isPaired())->toBeFalse()
            ->and($divChildren[1]->getChildren())->toHaveCount(0)
            ->and($divChildren[2])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[2]->isPaired())->toBeFalse()
            ->and($divChildren[2]->getChildren())->toHaveCount(0);
    });

    it('handles uppercase void element with mismatched closing tag', function (): void {
        $template = '<div><BR></span></div>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        $divChildren = $div->getChildren();

        expect($divChildren)->toHaveCount(2);

        $br = $divChildren[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('BR')
            ->and($br->isPaired())->toBeFalse()
            ->and($divChildren[1])->toBeInstanceOf(StrayClosingTagNode::class);
    });

    it('handles uppercase void element in complex nested structures', function (): void {
        $template = '<div><p>Text <BR> more text</p></div>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $p = $divChildren[0]->asElement();
        $pChildren = $p->getChildren();
        expect($pChildren)->toHaveCount(3)
            ->and($pChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($pChildren[1])->toBeInstanceOf(ElementNode::class)
            ->and($pChildren[1]->tagNameText())->toBe('BR')
            ->and($pChildren[1]->isPaired())->toBeFalse()
            ->and($pChildren[2])->toBeInstanceOf(TextNode::class);
    });
});
