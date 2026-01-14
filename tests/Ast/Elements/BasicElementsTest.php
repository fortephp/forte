<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DoctypeNode;
use Forte\Ast\Elements\Attribute;
use Forte\Ast\Elements\CdataNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\PhpTagNode;
use Forte\Ast\TextNode;
use Forte\Parser\ParserOptions;

describe('Basic Elements - Parameterized Nesting', function (): void {
    it('parses nested HTML elements correctly', function ($open, $close): void {
        $input = <<<HTML
<{$open} class="class-one class-two">
<{$open} class="class-three class-four">
<{$open} class="class-one class-two">Content</{$close}>
</{$close}>
</{$close}>
HTML;

        $doc = $this->parse($input);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $firstElement = $children[0]->asElement();
        expect($firstElement)->toBeInstanceOf(ElementNode::class)
            ->and($firstElement->isPaired())->toBeTrue()
            ->and($firstElement->isSelfClosing())->toBeFalse()
            ->and($firstElement->tagNameText())->toBe($open)
            ->and((string) $firstElement->closingTag()->name())->toBe($close)
            ->and($firstElement->attributes()->all())->toHaveCount(1);

        $firstAttribute = $firstElement->attributes()->all()[0];
        expect($firstAttribute)->toBeInstanceOf(Attribute::class)
            ->and($firstAttribute->nameText())->toBe('class')
            ->and($firstAttribute->valueText())->toBe('class-one class-two');

        $firstChildren = $firstElement->getChildren();
        $text0 = $firstChildren[0]->asText();
        $secondElement = $firstChildren[1]->asElement();
        $text2 = $firstChildren[2]->asText();

        expect($firstChildren)->toHaveCount(3)
            ->and($text0)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text0->getContent()))->toBe('')
            ->and($secondElement)->toBeInstanceOf(ElementNode::class)
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text2->getContent()))->toBe('')
            ->and($secondElement->tagNameText())->toBe($open)
            ->and((string) $secondElement->closingTag()->name())->toBe($close)
            ->and($secondElement->attributes()->all())->toHaveCount(1);

        $secondAttribute = $secondElement->attributes()->all()[0];
        expect($secondAttribute)->toBeInstanceOf(Attribute::class)
            ->and($secondAttribute->nameText())->toBe('class')
            ->and($secondAttribute->valueText())->toBe('class-three class-four')
            ->and($secondElement->isPaired())->toBeTrue()
            ->and($secondElement->isSelfClosing())->toBeFalse();

        $innerChildren = $secondElement->getChildren();
        $innerElement = $innerChildren[1]->asElement();
        $innerElChildren = $innerElement->getChildren();
        $innerText = $innerElChildren[0]->asText();

        expect($innerElement->isPaired())->toBeTrue()
            ->and($innerElement->isSelfClosing())->toBeFalse()
            ->and($innerElement->tagNameText())->toBe($open)
            ->and((string) $innerElement->closingTag()->name())->toBe($close)
            ->and($innerElement->attributes()->all())->toHaveCount(1);

        $innerAttribute = $innerElement->attributes()->all()[0];
        expect($innerAttribute)->toBeInstanceOf(Attribute::class)
            ->and($innerAttribute->nameText())->toBe('class')
            ->and($innerAttribute->valueText())->toBe('class-one class-two')
            ->and($innerElChildren)->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $innerText->getContent()))->toBe('Content');
    })->with('basic elements');

    it('parses HTML void elements correctly', function ($tag): void {
        $input = <<<HTML
<{$tag} class="void-class" disabled>
HTML;

        $element = $this->parseElement($input);

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isPaired())->toBeFalse()
            ->and($element->isSelfClosing())->toBeFalse()
            ->and($element->tagNameText())->toBe($tag);

        $attributes = $element->attributes()->all();
        expect($attributes)->toHaveCount(2)
            ->and($attributes[0])->toBeInstanceOf(Attribute::class)
            ->and($attributes[0]->nameText())->toBe('class')
            ->and($attributes[0]->valueText())->toBe('void-class')
            ->and($attributes[1])->toBeInstanceOf(Attribute::class)
            ->and($attributes[1]->nameText())->toBe('disabled')
            ->and($element->getChildren())->toHaveCount(0);
    })->with('void elements');

    it('handles ambiguous void element pairings', function (): void {
        $input = '<Br></Br>';

        $element = $this->parseElement($input);

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isPaired())->toBeTrue()
            ->and((string) $element->closingTag()->name())->toBe('Br');
    });

    it('handles whitespace before > in closing tags', function (): void {
        $input = <<<'HTML'
<span
>Hello</span
>
HTML;

        $element = $this->parseElement($input);

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isPaired())->toBeTrue()
            ->and($element->render())->toBe($input);
    });

    it('handles whitespace before > in self-closing tags', function (): void {
        $input = '<img src="test.jpg" />';

        $element = $this->parseElement($input);

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeTrue()
            ->and($element->render())->toBe($input);
    });

    it('handles multiple spaces and tabs before closing >', function (): void {
        $input = '<div  	>Content</div  	>';

        $doc = $this->parse($input);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $element = $nodes[0]->asElement();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isPaired())->toBeTrue()
            ->and($element->render())->toBe($input);
    });

    it('handles nested elements with whitespace before >', function (): void {
        $input = <<<'HTML'
<div
>
  <span
  >Hello</span
  >
</div
>
HTML;

        $element = $this->parseElement($input);

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isPaired())->toBeTrue()
            ->and($element->render())->toBe($input);
    });
});

describe('Basic Elements - Mixed Content Types', function (): void {
    it('parses HTML with mixed content types and syntax', function (): void {
        $html = <<<'HTML'
<!DOCTYPE html>
<div id="root" :class="{ open: isOpen }" @click.prevent="fn()" data-{{ $dyn }}="x" {{ $node }}={{ $other }}>
  <!-- html comment -->
  {{-- blade comment --}}
  <script type="text/javascript">
    if (a < b) { console.log(">>"); }
    {!! $raw !!}
    {{ $safe }}
  </script>
  <Map<Record<string, Array<Foo>>>> data="ok"></Map>
  @if(true)
    <span>ok</span>
  @else
    </p>
  @endif
  <?php echo "<b>php</b>"; ?>
  <![CDATA[ some <cdata> & weird ]]>
</div>
HTML;

        $scriptContent = <<<'SCRIPT'
<script type="text/javascript">
    if (a < b) { console.log(">>"); }
    {!! $raw !!}
    {{ $safe }}
  </script>
SCRIPT;

        $doc = $this->parse($html, ParserOptions::defaults());
        $nodes = $doc->getChildren();

        $div = $nodes[2]->asElement();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(DoctypeNode::class)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div)->not->toBeNull()
            ->and($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        /** @var Attribute[] $attributes */
        $attributes = $div->attributes()->all();

        expect($attributes)->toHaveCount(5)
            ->and($attributes[0])->toBeInstanceOf(Attribute::class)
            ->and($attributes[1])->toBeInstanceOf(Attribute::class);

        $a0 = $attributes[0];
        expect($a0)->toBeInstanceOf(Attribute::class)
            ->and($a0->type())->toBe('static')
            ->and($a0->nameText())->toBe('id')
            ->and($a0->valueText())->toBe('root');

        $a1 = $attributes[1];
        expect($a1)->toBeInstanceOf(Attribute::class)
            ->and($a1->type())->toBe('bound')
            ->and($a1->nameText())->toBe('class')
            ->and($a1->valueText())->toBe('{ open: isOpen }');

        $a2 = $attributes[2];
        expect($a2)->toBeInstanceOf(Attribute::class)
            ->and($a2->render())->toBe('@click.prevent="fn()"');

        $a3 = $attributes[3];
        expect($a3)->toBeInstanceOf(Attribute::class)
            ->and($a3->render())->toContain('data-')
            ->and($a3->render())->toContain('{{ $dyn }}')
            ->and($a3->render())->toContain('="x"');

        $a4 = $attributes[4];
        expect($a4)->toBeInstanceOf(Attribute::class)
            ->and($a4->render())->toContain('{{ $node }}')
            ->and($a4->render())->toContain('{{ $other }}');

        $children = $div->getChildren();

        $htmlComment = $children[1]->asComment();
        $bladeComment = $children[3]->asBladeComment();
        $scriptElement = $children[5]->asElement();
        $mapElement = $children[7]->asElement();
        $ifBlock = $children[9]->asDirectiveBlock();
        $phpTag = $children[11]->asPhpTag();
        $cdataNode = $children[13]->asCdata();

        expect($children)->toHaveCount(15)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($htmlComment)->toBeInstanceOf(CommentNode::class)
            ->and($htmlComment->render())->toBe('<!-- html comment -->')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($bladeComment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($bladeComment->render())->toBe('{{-- blade comment --}}')
            ->and($children[4])->toBeInstanceOf(TextNode::class)
            ->and($scriptElement)->toBeInstanceOf(ElementNode::class)
            ->and($scriptElement->tagNameText())->toBe('script')
            ->and($scriptElement->render())->toBe($scriptContent)
            ->and($children[6])->toBeInstanceOf(TextNode::class)
            ->and($mapElement)->toBeInstanceOf(ElementNode::class)
            ->and($mapElement->tagNameText())->toBe('Map')
            ->and($children[8])->toBeInstanceOf(TextNode::class)
            ->and($ifBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($children[10])->toBeInstanceOf(TextNode::class)
            ->and($phpTag)->toBeInstanceOf(PhpTagNode::class)
            ->and($children[12])->toBeInstanceOf(TextNode::class)
            ->and($cdataNode)->toBeInstanceOf(CdataNode::class)
            ->and($children[14])->toBeInstanceOf(TextNode::class);
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

        $element = $this->parseElement($template);

        expect($element->tagNameText())->toBe('script')
            ->and($element->isPaired())->toBeTrue();

        $elChildren = $element->getChildren();
        $innerText = $elChildren[0]->asText();
        expect($elChildren)->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and($innerText->render())->toBe($innerContent);
    });

    it('parses style tags that contain html-like strings', function (): void {
        $template = '<style>.icon::before { content: "<div>"; }</style>';

        $styleElement = $this->parseElement($template);

        expect($styleElement)->toBeInstanceOf(ElementNode::class)
            ->and($styleElement->tagNameText())->toBe('style')
            ->and($styleElement->isPaired())->toBeTrue();

        $elChildren = $styleElement->getChildren();
        $innerText = $elChildren[0]->asText();
        expect($elChildren)->toHaveCount(1)
            ->and($innerText)->toBeInstanceOf(TextNode::class)
            ->and($innerText->render())->toBe('.icon::before { content: "<div>"; }');
    });

    it('preserves style content with closing tag strings', function (): void {
        $template = '<style>/* </style> comment trick */ .class { color: red; }</style>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    it('can recover from missing greater than token on closing tag', function (): void {
        $template = <<<'HTML'
<script src="script.js"></script
HTML;

        $element = $this->parseElement($template);

        expect($element->attributes()->all())->toHaveCount(1)
            ->and($element->tagNameText())->toBe('script')
            ->and((string) $element->closingTag()->name())->toBe('script');
    });

    it('can parse attributes without quotes', function (): void {
        $template = <<<'HTML'
<script src=assets/js.js></script>
HTML;

        $element = $this->parseElement($template);

        expect($element->attributes()->all())->toHaveCount(1)
            ->and($element->tagNameText())->toBe('script')
            ->and((string) $element->closingTag()->name())->toBe('script');

        $att1 = $element->attributes()->all()[0];

        expect($att1->type())->toBe('static')
            ->and($att1->valueText())->toBe('assets/js.js');
    });

    test('uppercase void elements are recognized', function (): void {
        $template = '<BR>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('mixed case void elements are recognized', function (): void {
        $template = '<Br><Hr><Img src="test.jpg">';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('uppercase INPUT is void element', function (): void {
        $template = '<INPUT type="text" value="test">';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('deeply nested elements do not cause stack overflow', function (): void {
        $open = str_repeat('<div>', 100);
        $close = str_repeat('</div>', 100);
        $template = $open.'{{ $deep }}'.$close;

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('many attributes parse efficiently', function (): void {
        $attrs = [];
        for ($i = 0; $i < 100; $i++) {
            $attrs[] = "data-attr-$i=\"value-$i\"";
        }
        $template = '<div '.implode(' ', $attrs).'>content</div>';

        $start = microtime(true);
        $doc = $this->parse($template);
        $result = $doc->render();
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.05)
            ->and($result)->toBe($template);
    });

    test('multibyte characters in element names', function (): void {
        $template = '<div class="日本語">{{ $中文 }}</div>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('null bytes in input are handled', function (): void {
        $template = "<div>before\x00after</div>";

        $doc = $this->parse($template);
        expect($doc->render())->toBeString();
    });
});
