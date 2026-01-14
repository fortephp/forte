<?php

declare(strict_types=1);

use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Script Tags', function (): void {
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
