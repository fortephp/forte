<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Script Tags', function (): void {
    it('keeps tag-looking content opaque after a final boolean attribute', function (string $template, string $tag, string $content): void {
        $document = $this->parse($template);
        $element = $document->firstElement($tag);

        expect($document->render())->toBe($template)
            ->and($document->queryElements('img'))->toBeEmpty()
            ->and($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->getChildren())->toHaveCount(1)
            ->and($element->firstText())->toBeInstanceOf(TextNode::class)
            ->and($element->firstText()->getContent())->toBe($content);
    })->with([
        'script boolean attribute' => ['<script defer>const image = "<img>";</script>', 'script', 'const image = "<img>";'],
        'script boolean attribute with trailing whitespace' => ['<script defer >const image = "<img>";</script>', 'script', 'const image = "<img>";'],
        'style boolean attribute' => ['<style scoped>.icon::before { content: "<img>"; }</style>', 'style', '.icon::before { content: "<img>"; }'],
    ]);

    it('treats raw-text and RCDATA element contents as text', function (string $tag): void {
        $template = sprintf('<%1$s>A&amp;B <span>not an element</span></%1$s>', $tag);
        $document = $this->parse($template);
        $element = $document->firstElement($tag);

        expect($document->render())->toBe($template)
            ->and($document->queryElements('span'))->toBeEmpty()
            ->and($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->getChildren())->toHaveCount(1)
            ->and($element->firstText())->toBeInstanceOf(TextNode::class)
            ->and($element->firstText()->getContent())->toBe('A&amp;B <span>not an element</span>');
    })->with([
        'iframe',
        'noembed',
        'noframes',
        'textarea',
        'title',
        'xmp',
    ]);

    it('does not treat a namespaced script element as HTML raw text', function (): void {
        $template = '<native:script><img></native:script>';
        $document = $this->parse($template);

        expect($document->render())->toBe($template)
            ->and($document->queryElements('img'))->toHaveCount(1);
    });

    it('preserves script content with nested closing tags', function (): void {
        $template = '<script>document.write("</span>");</script>';

        expect($this->parse($template)->render())->toBe($template);
    });

    test('script with multiple fake closing tags', function (): void {
        $template = '<script>var html = "</div></span></p>";</script>';

        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles script tag with complex JavaScript and nested strings', function (): void {
        $template = <<<'HTML_WRAP'
        <script type="text/javascript">
        const template = `<div class="test">Hello ${'world'}</div>`;
        const regex = /<script.*?<\/script>/gi;
        document.innerHTML = '<span>Test</span>';
        </script>
        HTML_WRAP;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $script = $nodes[0]->asElement();

        expect($script)->toBeInstanceOf(ElementNode::class)
            ->and($script->tagNameText())->toBe('script')
            ->and($script->isPaired())->toBeTrue();
    });

    it('parses script tags that contain html-like strings', function (): void {
        $template = <<<'HTML'
<script>
func(`<script> (async () => {` + `</scr` + `ipt>'");``);
</script>
HTML;

        $innerContent = <<<'TEXT'

func(`<script> (async () => {` + `</scr` + `ipt>'");``);

TEXT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class);

        $element = $nodes[0]->asElement();

        expect($element->tagNameText())->toBe('script')
            ->and($element->isPaired())->toBeTrue();

        $children = $element->getChildren();
        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getContent())->toBe($innerContent);
    });

    it('can recover from missing greater than token on closing tag', function (): void {
        $template = <<<'HTML'
<script src="script.js"></script
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class);

        $element = $nodes[0]->asElement();

        expect($element->attributes()->all())->toHaveCount(1)
            ->and($element->tagNameText())->toBe('script')
            ->and((string) $element->closingTag()->name())->toBe('script');
    });

    it('can parse attributes without quotes', function (): void {
        $template = <<<'HTML'
<script src=assets/js.js></script>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class);

        $element = $nodes[0]->asElement();

        expect($element->attributes()->all())->toHaveCount(1)
            ->and($element->tagNameText())->toBe('script')
            ->and((string) $element->closingTag()->name())->toBe('script');

        $att1 = $element->attributes()->all()[0];

        expect($att1->type())->toBe('static')
            ->and($att1->valueText())->toBe('assets/js.js');
    });
});
