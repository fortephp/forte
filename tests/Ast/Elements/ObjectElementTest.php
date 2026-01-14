<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;

describe('Object Element Parsing', function (): void {
    it('parses basic object element', function (): void {
        $html = '<object data="movie.mp4" type="video/mp4"></object>';

        $doc = $this->parse($html);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class)
            ->and($children[0]->asElement()->tagNameText())->toBe('object');
    });

    it('parses object with param children', function (): void {
        $html = <<<'HTML'
<object data="flash.swf" type="application/x-shockwave-flash">
  <param name="quality" value="high">
  <param name="wmode" value="transparent">
</object>
HTML;

        $doc = $this->parse($html);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $object = $children[0]->asElement();
        expect($object)->toBeInstanceOf(ElementNode::class)
            ->and($object->tagNameText())->toBe('object')
            ->and($object->nodes()->whereElementIs('param')->all())->toHaveCount(2);
    });

    it('parses object with nested content', function (): void {
        $html = <<<'HTML'
<object data="image.svg" type="image/svg+xml">
  <img src="fallback.png" alt="Fallback image">
</object>
HTML;

        $doc = $this->parse($html);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $object = $children[0]->asElement();
        expect($object)->toBeInstanceOf(ElementNode::class)
            ->and($object->tagNameText())->toBe('object')
            ->and($object->nodes()->whereElementIs('img')->all())->toHaveCount(1);
    });

    it('parses object with all common attributes', function (): void {
        $html = '<object id="player" class="video-player" data="video.mp4" type="video/mp4" width="640" height="360"></object>';

        $doc = $this->parse($html);

        $object = $doc->firstChild()->asElement();
        expect($object)->toBeInstanceOf(ElementNode::class)
            ->and($object->tagNameText())->toBe('object')
            ->and($object->attributes()->all())->not->toBeEmpty();
    });

    it('parses object with form attribute', function (): void {
        $html = '<object data="file.pdf" type="application/pdf" form="myform"></object>';

        $doc = $this->parse($html);
        $object = $doc->firstChild()->asElement();

        expect($object)->toBeInstanceOf(ElementNode::class)
            ->and($object->tagNameText())->toBe('object');
    });

    it('parses nested objects', function (): void {
        $html = <<<'HTML'
<object data="outer.svg" type="image/svg+xml">
  <object data="inner.svg" type="image/svg+xml">
    <p>Fallback text</p>
  </object>
</object>
HTML;

        $doc = $this->parse($html);

        $outerObject = $doc->firstChild()->asElement();
        expect($outerObject)->toBeInstanceOf(ElementNode::class)
            ->and($outerObject->tagNameText())->toBe('object')
            ->and($outerObject->nodes()->whereElementIs('object')->all())->toHaveCount(1);
    });

    it('parses object with mixed content', function (): void {
        $html = <<<'HTML'
<object data="document.pdf" type="application/pdf">
  <param name="view" value="FitH">
  <p>Your browser does not support PDF viewing.</p>
  <a href="document.pdf">Download the PDF</a>
</object>
HTML;

        $doc = $this->parse($html);
        $object = $doc->firstChild()->asElement();

        expect($object)->toBeInstanceOf(ElementNode::class)
            ->and($object->tagNameText())->toBe('object')
            ->and($object->nodes()->elements()->all())->toHaveCount(3);
    });

    it('parses self-closing object tag', function (): void {
        $html = '<object data="file.pdf" type="application/pdf" />';

        expect($this->parse($html))->not->toBeNull();
    });
});
