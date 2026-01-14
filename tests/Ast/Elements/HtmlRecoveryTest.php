<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\TextNode;
use Forte\Lexer\Lexer;
use Forte\Lexer\Tokens\TokenType;

describe('Unpaired HTML Element Recovery', function (): void {
    it('handles complex malformed HTML with mixed attribute styles', function (): void {
        $template = <<<'HTML'
<script src="the/file.js"></script>
<script src=/common/subset-tests.js></script
<script src=/common/subset-tests.js></script>
<div data-x=foo/bar?baz=1&qux=2 checked disabled></div>
<input    type =   "text"    aria-label = 'x'   required   >
<div><span>Teehee</div < p>this is fine.
<script>
func(`<script> (async () => {` + `</scr` + `ipt>'");``);
</script>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(13);

        $script1 = $nodes[0]->asElement();
        expect($script1)->toBeInstanceOf(ElementNode::class)
            ->and($script1->tagNameText())->toBe('script')
            ->and($script1->isPaired())->toBeTrue();

        $script1Attrs = $script1->attributes()->all();
        expect($script1Attrs)->toHaveCount(1)
            ->and($script1Attrs[0]->nameText())->toBe('src')
            ->and($script1Attrs[0]->valueText())->toBe('the/file.js')
            ->and($nodes[1])->toBeInstanceOf(TextNode::class);

        $script2 = $nodes[2]->asElement();
        expect($script2)->toBeInstanceOf(ElementNode::class)
            ->and($script2->tagNameText())->toBe('script')
            ->and($script2->isPaired())->toBeTrue();

        $script2Attrs = $script2->attributes()->all();
        expect($script2Attrs)->toHaveCount(1)
            ->and($script2Attrs[0]->nameText())->toBe('src')
            ->and($script2Attrs[0]->valueText())->toBe('/common/subset-tests.js')
            ->and($nodes[3])->toBeInstanceOf(TextNode::class);

        $script3 = $nodes[4]->asElement();
        expect($script3)->toBeInstanceOf(ElementNode::class)
            ->and($script3->tagNameText())->toBe('script')
            ->and($script3->isPaired())->toBeTrue()
            ->and($nodes[5])->toBeInstanceOf(TextNode::class);

        $div1 = $nodes[6]->asElement();
        expect($div1)->toBeInstanceOf(ElementNode::class)
            ->and($div1->tagNameText())->toBe('div')
            ->and($div1->isPaired())->toBeTrue();

        $div1Attrs = $div1->attributes()->all();
        expect($div1Attrs)->toHaveCount(3)
            ->and($div1Attrs[0]->nameText())->toBe('data-x')
            ->and($div1Attrs[0]->valueText())->toBe('foo/bar?baz=1&qux=2')
            ->and($div1Attrs[1]->nameText())->toBe('checked')
            ->and($div1Attrs[2]->nameText())->toBe('disabled')
            ->and($nodes[7])->toBeInstanceOf(TextNode::class);

        $input = $nodes[8]->asElement();
        expect($input)->toBeInstanceOf(ElementNode::class)
            ->and($input->tagNameText())->toBe('input');

        $inputAttrs = $input->attributes()->all();
        expect($inputAttrs)->toHaveCount(3)
            ->and($inputAttrs[0]->nameText())->toBe('type')
            ->and($inputAttrs[0]->valueText())->toBe('text')
            ->and($inputAttrs[1]->nameText())->toBe('aria-label')
            ->and($inputAttrs[1]->valueText())->toBe('x')
            ->and($inputAttrs[2]->nameText())->toBe('required')
            ->and($nodes[9])->toBeInstanceOf(TextNode::class);

        $div2 = $nodes[10]->asElement();
        expect($div2)->toBeInstanceOf(ElementNode::class)
            ->and($div2->tagNameText())->toBe('div')
            ->and($div2->isPaired())->toBeTrue();

        $div2Children = $div2->getChildren();
        expect($div2Children)->toHaveCount(1);

        $span = $div2Children[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeFalse();

        $textNode = $nodes[11]->asText();
        expect($textNode)->toBeInstanceOf(TextNode::class)
            ->and($textNode->getContent())->toBe(" < p>this is fine.\n");

        $script4 = $nodes[12]->asElement();
        expect($script4)->toBeInstanceOf(ElementNode::class)
            ->and($script4->tagNameText())->toBe('script')
            ->and($script4->isPaired())->toBeTrue();

        $scriptContent = $script4->getChildren();
        expect($scriptContent)->toHaveCount(1)
            ->and($scriptContent[0])->toBeInstanceOf(TextNode::class);
    });

    it('emits synthetic token for closing tag at end of file', function (): void {
        $template = '<div>content</div';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull()
            ->and(substr($template, $syntheticToken['start'], $syntheticToken['end'] - $syntheticToken['start']))->toBe('');
    });

    it('emits synthetic token for closing tag followed by whitespace', function (): void {
        $template = '<div>content</div ';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('emits synthetic token for closing tag followed by text', function (): void {
        $template = '<div>content</div hello';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('emits synthetic token for closing tag followed by newline', function (): void {
        $template = "<div>content</div\n";
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('emits synthetic token for closing tag with characters directly after', function (): void {
        $template = '<div>content</divhi';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by new opening tag', function (): void {
        $template = '<div>content</div<p>more</p>';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by self-closing tag', function (): void {
        $template = '<div>content</div<br/>';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles nested malformed tags', function (): void {
        $template = '<div><p>content</p</div>';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticTokens = collect($tokens)
            ->where('type', TokenType::SyntheticClose);

        expect($syntheticTokens)->toHaveCount(1);
    });

    it('handles closing tag followed by directive', function (): void {
        $template = '<div>content</div.@if($condition)';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by echo', function (): void {
        $template = '<div>content</div{{ $variable }}';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by raw echo', function (): void {
        $template = '<div>content</div{!! $variable !!}';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by blade comment', function (): void {
        $template = '<div>content</div{{-- comment --}}';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('parses malformed closing tag and continues parsing', function (): void {
        $template = '<div>content</div extra text';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $element = $nodes[0]->asElement();
        $elementChildren = $element->getChildren();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe('div')
            ->and($element->isPaired())->toBeTrue()
            ->and($elementChildren)->toHaveCount(1)
            ->and($elementChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($elementChildren[0]->getContent())->toBe('content');

        $textNode = $nodes[1]->asText();
        expect($textNode)->toBeInstanceOf(TextNode::class)
            ->and($textNode->getContent())->toBe(' extra text');
    });

    it('parses malformed closing tag followed by new element', function (): void {
        $template = '<div>first</div<p>second</p>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $firstElement = $nodes[0]->asElement();

        expect($firstElement)->toBeInstanceOf(ElementNode::class)
            ->and($firstElement->tagNameText())->toBe('div')
            ->and($firstElement->isPaired())->toBeTrue();

        $secondElement = $nodes[1]->asElement();

        expect($secondElement)->toBeInstanceOf(ElementNode::class)
            ->and($secondElement->tagNameText())->toBe('p')
            ->and($secondElement->isPaired())->toBeTrue();
    });

    it('preserves element structure with malformed closing tag', function (): void {
        $template = '<div class="test">content</div';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $element = $nodes[0]->asElement();
        $elementChildren = $element->getChildren();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe('div')
            ->and($element->attributes()->all())->toHaveCount(1)
            ->and($element->isPaired())->toBeTrue()
            ->and($elementChildren)->toHaveCount(1);
    });

    it('handles multiple malformed closing tags', function (): void {
        $template = '<div>one</div<p>two</p<span>three</span>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $tagNames = collect($nodes)
            ->map(fn ($node) => $node->tagNameText())
            ->toArray();

        expect($tagNames)->toBe(['div', 'p', 'span']);
    });

    it('handles mix of correct and malformed tags', function (): void {
        $template = '<div>correct</div><p>malformed</p<span>correct</span>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and(collect($nodes)->every(fn ($node) => $node instanceof ElementNode && $node->isPaired()))->toBeTrue();
    });

    it('handles malformed tag in nested structure', function (): void {
        $template = <<<'HTML'
<div class="container">
    <h1>Title</h1>
    <p>Some content</p
    <footer>End</footer>
</div>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $container = $nodes[0]->asElement();
        $containerChildren = $container->getChildren();
        expect($container->tagNameText())->toBe('div')
            ->and($containerChildren)->toHaveCount(7)
            ->and($container->isPaired())->toBeTrue();
    });

    it('handles malformed self-closing tags', function (): void {
        $template = '<img src="test.jpg"';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $element = $nodes[0]->asElement();

        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe('img');
    });

    it('handles void elements with malformed syntax', function (): void {
        $template = '<br<hr<input type="text"';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $tagNames = collect($nodes)
            ->map(fn ($node) => $node->tagNameText())
            ->toArray();

        expect($tagNames)->toBe(['br', 'hr', 'input']);
    });

    it('handles malformed script tag', function (): void {
        $template = '<script>console.log("test");</script';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $element = $nodes[0]->asElement();
        $elementChildren = $element->getChildren();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe('script')
            ->and($element->isPaired())->toBeTrue()
            ->and($elementChildren)->toHaveCount(1);
    });

    it('handles malformed style tag', function (): void {
        $template = '<style>body { color: red; }</style';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $element = $nodes[0]->asElement();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->tagNameText())->toBe('style')
            ->and($element->isPaired())->toBeTrue();
    });

    it('handles closing tag followed by tab', function (): void {
        $template = "<div>content</div\t";
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by carriage return', function (): void {
        $template = "<div>content</div\r";
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('handles closing tag followed by mixed whitespace and text', function (): void {
        $template = "<div>content</div \n\t hello";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0]->asElement()->isPaired())->toBeTrue()
            ->and($nodes[1]->asText()->getContent())->toBe(" \n\t hello");
    });

    it('handles deeply nested malformed tags', function (): void {
        $template = '<div><section><article><p>content</p</article></section></div>';
        $doc = $this->parse($template);
        $div = $doc->firstChild()->asElement();

        expect($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();
    });

    it('handles malformed tags with blade constructs inside', function (): void {
        $template = '<div>{{ $variable }}</div<p>more</p>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $div = $nodes[0]->asElement();
        $divChildren = $div->getChildren();

        expect($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue()
            ->and($divChildren)->toHaveCount(1);
    });

    it('handles attributes on malformed closing tags', function (): void {
        $template = '<div>content</div class="oops"<p>next</p>';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticToken = collect($tokens)
            ->firstWhere('type', TokenType::SyntheticClose);

        expect($syntheticToken)->not->toBeNull();
    });

    it('maintains correct token positions after recovery', function (): void {
        $template = '<div>content</div extra';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $textTokens = collect($tokens)->where('type', TokenType::Text);
        expect($textTokens)->toHaveCount(2);

        $lastTextToken = $textTokens->last();
        expect(substr($template, $lastTextToken['start'], $lastTextToken['end'] - $lastTextToken['start']))->toBe(' extra');
    });

    it('preserves document content integrity', function (): void {
        $template = '<div>hello</div world';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $reconstructed = collect($nodes)
            ->map(fn ($node) => $node->getDocumentContent())
            ->join('');

        expect($reconstructed)->toBe($template);
    });

    it('handles consecutive malformed tags correctly', function (): void {
        $template = '<div>one</div<p>two</p<span>three</span<em>four</em>';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        $syntheticTokens = collect($tokens)
            ->where('type', TokenType::SyntheticClose);

        expect($syntheticTokens)->toHaveCount(3);
    });

    it('handles empty malformed closing tag', function (): void {
        $template = '<></>hello';
        $lexer = new Lexer($template);
        $tokens = $lexer->tokenize()->tokens;

        expect($tokens)->not->toBeEmpty();
    });

    it('handles very long content after malformed tag', function (): void {
        $longContent = str_repeat('x', 1000);
        $template = "<div>content</div {$longContent}";
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $textNode = $nodes[1]->asText();
        expect(str_starts_with((string) $textNode->getContent(), ' x'))->toBeTrue()
            ->and(strlen((string) $textNode->getContent()))->toBe(1001);
    });

    it('handles unicode content after malformed tag', function (): void {
        $template = '<div>content</div 🚀 unicode text';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $textNode = $nodes[1]->asText();
        expect($textNode->getContent())->toBe(' 🚀 unicode text');
    });

    it('handles malformed tag with complex following content', function (): void {
        $template = <<<'HTML'
<div>content</div
<p>This is a paragraph with {{ $variable }} and @if($condition) directive @endif</p>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $div = $nodes[0]->asElement();

        expect($nodes)->toHaveCount(3)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();
    });

    it('handles unclosed span inside div', function (): void {
        $template = '<div><span>Text</div> Text2';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $div = $nodes[0]->asElement();

        expect($nodes)->toHaveCount(2)
            ->and($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeFalse();

        $spanChildren = $span->getChildren();
        expect($spanChildren)->toHaveCount(1);

        $text = $spanChildren[0]->asText();
        expect($text)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text->getContent()))->toBe('Text');

        $text2 = $nodes[1]->asText();
        expect($text2)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text2->getContent()))->toBe('Text2');
    });

    it('handles unpaired closing span after div', function (): void {
        $template = '<div><span>Text</div></span> Text2';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        $unpairedSpan = $nodes[1]->asStrayClosingTag();
        expect($unpairedSpan)->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($unpairedSpan->tagNameText())->toBe('span');

        $text2 = $nodes[2]->asText();
        expect($text2)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text2->getContent()))->toBe('Text2');
    });

    it('handles multiple nested unclosed elements', function (): void {
        $template = '<div><p><span>Text</div> More text';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $div = $nodes[0]->asElement();

        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $p = $divChildren[0]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->tagNameText())->toBe('p')
            ->and($p->isPaired())->toBeFalse();

        $pChildren = $p->getChildren();
        expect($pChildren)->toHaveCount(1);

        $span = $pChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeFalse();

        $spanChildren = $span->getChildren();
        expect($spanChildren)->toHaveCount(1)
            ->and($spanChildren[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $spanChildren[0]->asText()->getContent()))->toBe('Text');

        $moreText = $nodes[1]->asText();
        expect($moreText)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $moreText->getContent()))->toBe('More text');
    });

    it('handles correct nesting after malformed section', function (): void {
        $template = '<div><span>Text</div><p>Valid paragraph</p>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->isPaired())->toBeTrue();

        $p = $nodes[1]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->tagNameText())->toBe('p')
            ->and($p->isPaired())->toBeTrue();

        $pChildren = $p->getChildren();
        expect($pChildren)->toHaveCount(1)
            ->and($pChildren[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $pChildren[0]->asText()->getContent()))->toBe('Valid paragraph');
    });

    it('handles malformed HTML inside if directive', function (): void {
        $template = '@if ($count > 5)<div><span>Text</div></span> Text2 @endif';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $blockDirective = $nodes[0]->asDirectiveBlock();
        expect($blockDirective)->toBeInstanceOf(DirectiveBlockNode::class);

        $blockChildren = $blockDirective->getChildren();
        expect($blockChildren)->toHaveCount(2);

        $startDirective = $blockChildren[0]->asDirective();
        expect($startDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($startDirective->nameText())->toBe('if');

        $endDirective = $blockChildren[1]->asDirective();
        expect($endDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endDirective->nameText())->toBe('endif');

        $ifContent = $startDirective->getChildren();
        expect($ifContent)->toHaveCount(3);

        $div = $ifContent[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div');

        $unpairedSpan = $ifContent[1]->asStrayClosingTag();
        expect($unpairedSpan)->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($unpairedSpan->tagNameText())->toBe('span');

        $text2 = $ifContent[2]->asText();
        expect($text2)->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $text2->getContent()))->toBe('Text2');
    });

    it('handles malformed HTML across directive boundaries', function (): void {
        $template = '<div>@if ($condition)<span>Text @endif</div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->isPaired())->toBeTrue();

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $ifDirective = $divChildren[0]->asDirectiveBlock();
        expect($ifDirective)->toBeInstanceOf(DirectiveBlockNode::class);

        $ifChildren = $ifDirective->getChildren();
        expect($ifChildren)->toHaveCount(2);

        $startDirective = $ifChildren[0]->asDirective();
        expect($startDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($startDirective->nameText())->toBe('if');

        $endDirective = $ifChildren[1]->asDirective();
        expect($endDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endDirective->nameText())->toBe('endif');

        $startDirectiveChildren = $startDirective->getChildren();
        expect($startDirectiveChildren)->toHaveCount(1);

        $span = $startDirectiveChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeFalse();
    });

    it('handles malformed HTML with Blade getEchoes', function (): void {
        $template = '<div><span>{{ $name }}</div> {{ $other }}';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class);

        $spanChildren = $span->getChildren();
        expect($spanChildren)->toHaveCount(1)
            ->and($spanChildren[0])->toBeInstanceOf(EchoNode::class);

        $whitespace = $nodes[1]->asText();
        expect($whitespace)->toBeInstanceOf(TextNode::class);

        $otherEcho = $nodes[2]->asEcho();
        expect($otherEcho)->toBeInstanceOf(EchoNode::class);
    });

    it('handles malformed HTML with attributes', function (): void {
        $template = '<div class="container"><span id="test">Content</div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $attributes = $div->attributes()->all();
        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]->nameText())->toBe('class')
            ->and($attributes[0]->valueText())->toBe('container');

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class);

        $spanAttributes = $span->attributes()->all();
        expect($spanAttributes)->toHaveCount(1)
            ->and($spanAttributes[0]->nameText())->toBe('id')
            ->and($spanAttributes[0]->valueText())->toBe('test');
    });

    it('handles self-closing tags in malformed context', function (): void {
        $template = '<div><br/><span>Text</div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(2);

        $br = $divChildren[0]->asElement();
        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('br')
            ->and($br->isSelfClosing())->toBeTrue();

        $span = $divChildren[1]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->isPaired())->toBeFalse();
    });

    it('handles void elements in malformed context', function (): void {
        $template = '<div><img src="test.jpg"><span>Text</div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(2);

        $img = $divChildren[0]->asElement();
        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->tagNameText())->toBe('img')
            ->and($img->isPaired())->toBeFalse()
            ->and($img->isSelfClosing())->toBeFalse();

        $span = $divChildren[1]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->isPaired())->toBeFalse();
    });

    it('handles deeply nested malformed structure', function (): void {
        $template = '<div><section><article><header><h1>Title</header></article><p>Text</div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->isPaired())->toBeTrue();

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $section = $divChildren[0]->asElement();
        expect($section)->toBeInstanceOf(ElementNode::class)
            ->and($section->tagNameText())->toBe('section')
            ->and($section->isPaired())->toBeFalse();

        $sectionChildren = $section->getChildren();
        expect($sectionChildren)->toHaveCount(2);

        $article = $sectionChildren[0]->asElement();
        expect($article)->toBeInstanceOf(ElementNode::class)
            ->and($article->tagNameText())->toBe('article')
            ->and($article->isPaired())->toBeTrue();

        $p = $sectionChildren[1]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->tagNameText())->toBe('p')
            ->and($p->isPaired())->toBeFalse();

        $articleChildren = $article->getChildren();
        expect($articleChildren)->toHaveCount(1);

        $header = $articleChildren[0]->asElement();
        expect($header)->toBeInstanceOf(ElementNode::class)
            ->and($header->tagNameText())->toBe('header')
            ->and($header->isPaired())->toBeTrue();

        $headerChildren = $header->getChildren();
        expect($headerChildren)->toHaveCount(1);

        $h1 = $headerChildren[0]->asElement();
        expect($h1)->toBeInstanceOf(ElementNode::class)
            ->and($h1->tagNameText())->toBe('h1')
            ->and($h1->isPaired())->toBeFalse();
    });

    it('handles malformed HTML with multiple text nodes', function (): void {
        $template = '<div>Start<span>Middle</div>End<p>Final</p>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(2)
            ->and($divChildren[0])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $divChildren[0]->getContent()))->toBe('Start');

        $span = $divChildren[1]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->isPaired())->toBeFalse()
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $nodes[1]->getContent()))->toBe('End');

        $p = $nodes[2]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->isPaired())->toBeTrue();
    });

    it('handles alternating malformed and correct elements', function (): void {
        $template = '<div><span>Bad</div><section><p>Good</p></section><article><h1>Bad</article>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isPaired())->toBeTrue();

        $section = $nodes[1]->asElement();
        expect($section)->toBeInstanceOf(ElementNode::class)
            ->and($section->tagNameText())->toBe('section')
            ->and($section->isPaired())->toBeTrue();

        $sectionChildren = $section->getChildren();
        expect($sectionChildren)->toHaveCount(1);

        $p = $sectionChildren[0]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->isPaired())->toBeTrue();

        $article = $nodes[2]->asElement();
        expect($article)->toBeInstanceOf(ElementNode::class)
            ->and($article->tagNameText())->toBe('article')
            ->and($article->isPaired())->toBeTrue();
    });

    it('handles malformed HTML with synthetic closing tokens', function (): void {
        $template = '<div><span>Text</div <p>More</p>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class)
            ->and($div->isPaired())->toBeTrue();

        $p = $nodes[2]->asElement();
        expect($p)->toBeInstanceOf(ElementNode::class)
            ->and($p->isPaired())->toBeTrue();
    });

    it('handles malformed HTML with only whitespace content', function (): void {
        $template = '<div><span>   </div>   ';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class);

        $spanChildren = $span->getChildren();
        expect($spanChildren)->toHaveCount(1)
            ->and($spanChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class);
    });

    it('handles empty malformed elements', function (): void {
        $template = '<div><span></div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $div = $nodes[0]->asElement();
        expect($div)->toBeInstanceOf(ElementNode::class);

        $divChildren = $div->getChildren();
        expect($divChildren)->toHaveCount(1);

        $span = $divChildren[0]->asElement();
        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->isPaired())->toBeFalse()
            ->and($span->getChildren())->toHaveCount(0);
    });

    it('handles malformed HTML at document root', function (): void {
        $template = '<span>Root content</div>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1);

        $span = $nodes[0]->asElement();

        expect($span)->toBeInstanceOf(ElementNode::class)
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeFalse();

        $spanChildren = $span->getChildren();
        $unpairedDiv = $spanChildren[1]->asStrayClosingTag();

        expect($spanChildren)->toHaveCount(2)
            ->and($spanChildren[0]->asText())->toBeInstanceOf(TextNode::class)
            ->and($spanChildren[0]->asText()->getContent())->toBe('Root content')
            ->and($unpairedDiv)->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($unpairedDiv->tagNameText())->toBe('div');
    });

    it('preserves document content offsets correctly', function (): void {
        $template = '<div><span>Text</div> More';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $div = $nodes[0]->asElement();
        expect($div->startOffset())->toBe(0)
            ->and($div->endOffset())->toBe(21)
            ->and($nodes[1]->asText()->startOffset())->toBe(21);
    });

    it('handles malformed HTML inside script tags', function (): void {
        $template = '<script><div><span>alert("test")</div></script>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $script = $nodes[0]->asElement();
        $scriptChildren = $script->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($script)->toBeInstanceOf(ElementNode::class)
            ->and($script->tagNameText())->toBe('script')
            ->and($script->isPaired())->toBeTrue()
            ->and($scriptChildren)->toHaveCount(1)
            ->and($scriptChildren[0])->toBeInstanceOf(TextNode::class);
    });

    it('handles malformed HTML inside style tags', function (): void {
        $template = '<style><div> { color: red; }</div></style>';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $style = $nodes[0]->asElement();
        $styleChildren = $style->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($style)->toBeInstanceOf(ElementNode::class)
            ->and($style->tagNameText())->toBe('style')
            ->and($style->isPaired())->toBeTrue()
            ->and($styleChildren)->toHaveCount(1)
            ->and($styleChildren[0])->toBeInstanceOf(TextNode::class);
    });

    it('handles Blade directives with malformed HTML structure', function (): void {
        $template = <<<'HTML'
@if($condition)
<div class="container"
    <p>Missing closing bracket above @if($inner)</p>
@endif
<span>Unpaired span @endfor
@endif
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class);
    });

    it('handles deeply nested malformed elements with Blade getEchoes', function (): void {
        $template = <<<'HTML'
<article class="{{ $class }}"
    <section data-id={{ $id }} checked
        <header><h1>{{ $title }}</h1>
        <div><span>Content {{ $content }} more</div>
    </section
</article>
HTML;

        $article = $this->parseElement($template);
        $children = $article->getChildren();
        $section = $children[0]->asElement();

        expect($article)->toBeInstanceOf(ElementNode::class)
            ->and($article->tagNameText())->toBe('article')
            ->and($children)->toHaveCount(2)
            ->and($section)->not->toBeNull()
            ->and($section->tagNameText())->toBe('section')
            ->and($children[1]->asText())->not->toBeNull()
            ->and($section->getChildren())->toHaveCount(1);

        $innerHeader = $section->getChildren()[0]->asElement();

        expect($innerHeader)->not->toBeNull()
            ->and($innerHeader->tagNameText())->toBe('header');

        $headerChildren = $innerHeader->getChildren();
        $h1 = $headerChildren[0]->asElement();
        $div = $headerChildren[2]->asElement();

        expect($headerChildren)->toHaveCount(4)
            ->and($h1)->not->toBeNull()
            ->and($div)->not->toBeNull()
            ->and($h1->tagNameText())->toBe('h1')
            ->and($div->tagNameText())->toBe('div');
    });

    it('handles mixed quote styles and unquoted attributes', function (): void {
        $template = <<<'HTML'
<form method=post action="/submit" data-test='single quotes' class="double quotes" required>
    <input type=email name="user_email" value='{{ $email }}' placeholder=Enter\ your\ email required />
    <button type="submit" onclick='alert("Hello {{ $name }}!")'>Submit</button>
</form>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $form = $nodes[0]->asElement();

        expect($nodes)->toHaveCount(1)
            ->and($form)->toBeInstanceOf(ElementNode::class)
            ->and($form->attributes()->all())->toHaveCount(5);
    });

    it('handles self-closing tags with malformed syntax', function (): void {
        $template = <<<'HTML'
<img src="/image.jpg" alt="Test image" />
<br>
<hr class="divider"
<input type="hidden" name="_token" value="{{ csrf_token() }}" /
<meta charset="utf-8">
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(8);

        $img = $nodes[0]->asElement();
        expect($img)->toBeInstanceOf(ElementNode::class)
            ->and($img->tagNameText())->toBe('img')
            ->and($img->isSelfClosing())->toBeTrue()
            ->and($nodes[1])->toBeInstanceOf(TextNode::class);

        $br = $nodes[2]->asElement();

        expect($br)->toBeInstanceOf(ElementNode::class)
            ->and($br->tagNameText())->toBe('br')
            ->and($nodes[3])->toBeInstanceOf(TextNode::class);

        $hr = $nodes[4]->asElement();
        expect($hr)->toBeInstanceOf(ElementNode::class)
            ->and($hr->tagNameText())->toBe('hr');

        $input = $nodes[5]->asElement();
        expect($input)->toBeInstanceOf(ElementNode::class)
            ->and($input->tagNameText())->toBe('input')
            ->and($input->isSelfClosing())->toBeTrue()
            ->and($nodes[6])->toBeInstanceOf(TextNode::class);

        $meta = $nodes[7]->asElement();
        expect($meta)->toBeInstanceOf(ElementNode::class)
            ->and($meta->tagNameText())->toBe('meta');
    });

    it('handles HTML comments mixed with malformed elements', function (): void {
        $template = <<<'HTML'
<!-- Main content section -->
<main class="content"
    <!-- TODO: Fix this div -->
    <div data-component="widget" @click="handleClick($event)"
        <p>Some content <!-- inline comment --> here</p>
    </div
    <!-- End of widget -->
</main>
HTML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $mainComment = $nodes[0];
        expect($mainComment)->toBeInstanceOf(CommentNode::class)
            ->and($mainComment->startLine())->toBe(1)
            ->and($mainComment->endLine())->toBe(1)
            ->and($mainComment->startOffset())->toBe(0)
            ->and($mainComment->endOffset())->toBe(29)
            ->and($mainComment->getDocumentContent())->toBe('<!-- Main content section -->');

        $whitespaceText = $nodes[1]->asText();
        expect($whitespaceText)->toBeInstanceOf(TextNode::class);

        $mainElement = $nodes[2]->asElement();
        expect($mainElement)->toBeInstanceOf(ElementNode::class)
            ->and($mainElement->tagNameText())->toBe('main')
            ->and($mainElement->startLine())->toBe(2)
            ->and($mainElement->endLine())->toBe(8)
            ->and($mainElement->isPaired())->toBeTrue()
            ->and($mainElement->isSelfClosing())->toBeFalse();

        $mainChildren = $mainElement->getChildren();

        expect($mainChildren)->toHaveCount(6);

        $divElement = $mainElement->firstChildOfType(ElementNode::class)->asElement();

        expect($divElement)->not->toBeNull()
            ->and($divElement)->toBeInstanceOf(ElementNode::class)
            ->and($divElement->tagNameText())->toBe('div')
            ->and($divElement->startLine())->toBe(4)
            ->and($divElement->endLine())->toBe(6)
            ->and($divElement->hasSyntheticClosing())->toBeTrue()
            ->and($divElement->isPaired())->toBeTrue();

        $mainAttributes = $mainElement->attributes()->all();
        expect($mainAttributes)->not->toBeEmpty();

        $divAttributes = $divElement->attributes()->all();
        expect($divAttributes)->not->toBeEmpty()
            ->and($doc->render())->toBe($template);
    });

    test('html comment ending at EOF', function (): void {
        $template = '<!-- comment --';

        expect($this->parse($template)->render())->toBe($template);
    });

    it('preserves trailing <R after <Map', function (): void {
        $template = '<Map<R';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $mapElement = $nodes[0]->asElement();
        $rElement = $nodes[1]->asElement();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($mapElement)->toBeInstanceOf(ElementNode::class)
            ->and($mapElement->hasSyntheticClosing())->toBeTrue()
            ->and($mapElement->tagNameText())->toBe('Map')
            ->and($rElement)->toBeInstanceOf(ElementNode::class)
            ->and($rElement->hasSyntheticClosing())->toBeTrue()
            ->and($rElement->tagNameText())->toBe('R');
    });
});
