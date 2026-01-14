<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\Attributes;
use Forte\Ast\Elements\CdataNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\TextNode;
use Forte\Ast\XmlDeclarationNode;

describe('XML Compatibility', function (): void {
    it('parses basic XML with namespaces and self-closing tags', function (): void {
        $template = <<<'XML'
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="en">
  <title>Example Feed</title>
  <link href="http://example.org/"/>
  <entry>
    <title>Atom entry</title>
    <link href="http://example.org/2003/12/13/atom03"/>
  </entry>
</feed>
XML;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class)
            ->and((string) $nodes[0]->tagName())->toBe('feed')
            ->and($nodes[0]->isPaired())->toBeTrue();

        $feed = $nodes[0]->asElement();
        $attrs = $feed->getAttributes();

        expect($attrs)->toHaveCount(2)
            ->and($attrs[0]->nameText())->toBe('xmlns')
            ->and($attrs[0]->type())->toBe('static');

        $childEls = $feed->getChildren();
        expect($childEls)->toHaveCount(7);

        $titleIdx = 1;
        expect($childEls[$titleIdx])->toBeInstanceOf(ElementNode::class)
            ->and((string) $childEls[$titleIdx]->asElement()->tagNameText())->toBe('title')
            ->and($childEls[$titleIdx]->asElement()->isPaired())->toBeTrue();

        $linkIdx = 3;
        expect($childEls[$linkIdx])->toBeInstanceOf(ElementNode::class)
            ->and((string) $childEls[$linkIdx]->asElement()->tagNameText())->toBe('link')
            ->and($childEls[$linkIdx]->asElement()->isSelfClosing())->toBeTrue();
    });

    it('handles namespaced elements and attributes', function (): void {
        $template = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="#icon" width="24" height="24"/></svg>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class)
            ->and((string) $nodes[0]->asElement()->tagNameText())->toBe('svg')
            ->and($nodes[0]->asElement()->isPaired())->toBeTrue();

        $svg = $nodes[0]->asElement();
        $children = $svg->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class)
            ->and((string) $children[0]->asElement()->tagNameText())->toBe('use')
            ->and($children[0]->asElement()->isSelfClosing())->toBeTrue();

        $use = $children[0]->asElement();
        $attributes = $use->getAttributes();

        expect($attributes[0]->nameText())->toBe('xlink:href')
            ->and($attributes[1]->nameText())->toBe('width')
            ->and($attributes[2]->nameText())->toBe('height');
    });

    it('parses XML with CDATA, comments, and Blade inside XML text', function (): void {
        $template = <<<'XML'
<note>
  <!-- a comment -->
  <![CDATA[ some <xml> content & specials ]]>
  <to>{{ $name }}</to>
  <message>@if($ok)Yes.@endif</message>
</note>
XML;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template);

        $note = $nodes[0]->asElement();
        expect($note)->toBeInstanceOf(ElementNode::class)
            ->and((string) $note->tagName())->toBe('note')
            ->and($note->startLine())->toBe(1)
            ->and($note->endLine())->toBe(6);

        $noteChildren = $note->getChildren();
        expect($noteChildren)->toHaveCount(9);

        $text1 = $noteChildren[0];
        expect($text1)->toBeInstanceOf(TextNode::class);

        $comment = $noteChildren[1]->asComment();
        expect($comment)->toBeInstanceOf(CommentNode::class)
            ->and($comment->startLine())->toBe(2)
            ->and($comment->endLine())->toBe(2)
            ->and($comment->content())->toBe(' a comment ');

        $text2 = $noteChildren[2]->asText();
        expect($text2)->toBeInstanceOf(TextNode::class)
            ->and($text2->startLine())->toBe(2)
            ->and($text2->endLine())->toBe(3);

        $cdata = $noteChildren[3]->asCdata();
        expect($cdata)->toBeInstanceOf(CdataNode::class)
            ->and($cdata->content())->toBe(' some <xml> content & specials ')
            ->and($cdata->startLine())->toBe(3)
            ->and($cdata->endLine())->toBe(3);

        $text3 = $noteChildren[4];
        expect($text3)->toBeInstanceOf(TextNode::class);

        $to = $noteChildren[5]->asElement();
        expect($to)->toBeInstanceOf(ElementNode::class)
            ->and((string) $to->tagName())->toBe('to')
            ->and($to->startLine())->toBe(4)
            ->and($to->endLine())->toBe(4);

        $toChildren = $to->getChildren();
        expect($toChildren)->toHaveCount(1);

        $echo = $toChildren[0]->asEcho();
        expect($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->content())->toBe(' $name ')
            ->and($echo->startLine())->toBe(4)
            ->and($echo->endLine())->toBe(4);

        $text4 = $noteChildren[6];
        expect($text4)->toBeInstanceOf(TextNode::class)
            ->and($text4->startLine())->toBe(4)
            ->and($text4->endLine())->toBe(5);

        $message = $noteChildren[7]->asElement();
        expect($message)->toBeInstanceOf(ElementNode::class)
            ->and((string) $message->tagName())->toBe('message')
            ->and($message->startLine())->toBe(5)
            ->and($message->endLine())->toBe(5);

        $messageChildren = $message->getChildren();
        expect($messageChildren)->toHaveCount(1);

        $blockDirective = $messageChildren[0];
        expect($blockDirective)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($blockDirective->startLine())->toBe(5)
            ->and($blockDirective->endLine())->toBe(5);

        $blockChildren = $blockDirective->getChildren();
        expect($blockChildren)->toHaveCount(2);

        $ifDirective = $blockChildren[0]->asDirective();
        expect($ifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($ifDirective->nameText())->toBe('if')
            ->and($ifDirective->arguments())->toBe('($ok)')
            ->and($ifDirective->startLine())->toBe(5)
            ->and($ifDirective->endLine())->toBe(5);

        $ifChildren = $ifDirective->getChildren();
        expect($ifChildren)->toHaveCount(1);

        $ifText = $ifChildren[0];
        expect($ifText)->toBeInstanceOf(TextNode::class)
            ->and($ifText->getContent())->toBe('Yes.');

        $endifDirective = $blockChildren[1]->asDirective();
        expect($endifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endifDirective->nameText())->toBe('endif')
            ->and($endifDirective->arguments())->toBeNull()
            ->and($endifDirective->startLine())->toBe(5)
            ->and($endifDirective->endLine())->toBe(5);

        $endifChildren = $endifDirective->getChildren();
        expect($endifChildren)->toHaveCount(0);

        $text5 = $noteChildren[8];
        expect($text5)->toBeInstanceOf(TextNode::class);
    });

    it('handles Blade echos inside XML attributes', function (): void {
        $template = '<item id="{{ $id }}" data-value="@if(true)ok.@endif" />';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template);

        $item = $nodes[0]->asElement();
        expect($item)->toBeInstanceOf(ElementNode::class)
            ->and((string) $item->tagNameText())->toBe('item')
            ->and($item->startLine())->toBe(1)
            ->and($item->endLine())->toBe(1)
            ->and($item->isSelfClosing())->toBeTrue()
            ->and($item->isPaired())->toBeFalse();

        $itemChildren = $item->getChildren();
        expect($itemChildren)->toHaveCount(0);

        $attributes = $item->getAttributes();
        expect($attributes)->toHaveCount(2);

        $idAttr = $attributes[0];
        expect($idAttr->nameText())->toBe('id')
            ->and($idAttr->quote())->toBe('"')
            ->and($idAttr->valueText())->toBe('{{ $id }}');

        $dataAttr = $attributes[1];
        expect($dataAttr->nameText())->toBe('data-value')
            ->and($dataAttr->quote())->toBe('"')
            ->and($dataAttr->valueText())->toBe('@if(true)ok.@endif');
    });

    it('tolerates unpaired closing tag inside XML-like structure', function (): void {
        $template = '<root><child>text</child></oops></root>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($doc->render())->toBe($template);

        $rootElement = $nodes[0]->asElement();
        expect($rootElement)->toBeInstanceOf(ElementNode::class)
            ->and((string) $rootElement->tagNameText())->toBe('root')
            ->and($rootElement->startLine())->toBe(1)
            ->and($rootElement->endLine())->toBe(1)
            ->and($rootElement->isPaired())->toBeTrue()
            ->and($rootElement->isSelfClosing())->toBeFalse();

        $rootChildren = $rootElement->getChildren();
        expect($rootChildren)->toHaveCount(2);

        $childElement = $rootChildren[0]->asElement();
        expect($childElement)->toBeInstanceOf(ElementNode::class)
            ->and((string) $childElement->tagNameText())->toBe('child')
            ->and($childElement->startLine())->toBe(1)
            ->and($childElement->endLine())->toBe(1)
            ->and($childElement->isPaired())->toBeTrue()
            ->and($childElement->isSelfClosing())->toBeFalse();

        $childChildren = $childElement->getChildren();
        expect($childChildren)->toHaveCount(1);

        $childText = $childChildren[0]->asText();
        expect($childText)->toBeInstanceOf(TextNode::class)
            ->and($childText->getContent())->toBe('text')
            ->and($childText->startLine())->toBe(1)
            ->and($childText->endLine())->toBe(1)
            ->and($childText->startOffset())->toBe(13)
            ->and($childText->endOffset())->toBe(17);

        $unpairedElement = $rootChildren[1];
        expect($unpairedElement)->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($unpairedElement->tagNameText())->toBe('oops')
            ->and($unpairedElement->startLine())->toBe(1)
            ->and($unpairedElement->endLine())->toBe(1)
            ->and($unpairedElement->startOffset())->toBe(25)
            ->and($unpairedElement->endOffset())->toBe(32)
            ->and($unpairedElement->getDocumentContent())->toBe('</oops>')
            ->and($unpairedElement->getChildren())->toHaveCount(0)
            ->and($rootElement->getDocumentContent())->toBe('<root><child>text</child></oops></root>')
            ->and($childElement->getDocumentContent())->toBe('<child>text</child>')
            ->and($childText->getDocumentContent())->toBe('text')
            ->and($rootElement->getAttributes())->toHaveCount(0)
            ->and($childElement->getAttributes())->toHaveCount(0);
    });

    it('parses XML declaration as XmlDeclarationNode', function (): void {
        $template = '<?xml version="1.0" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($nodes[0]->isXmlDeclaration())->toBeTrue()
            ->and($doc->render())->toBe($template);
    });

    it('extracts version from XML declaration', function (): void {
        $template = '<?xml version="1.0"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->version())->toBe('1.0');
    });

    it('extracts encoding from XML declaration', function (): void {
        $template = '<?xml version="1.0" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->encoding())->toBe('UTF-8');
    });

    it('extracts standalone from XML declaration', function (): void {
        $template = '<?xml version="1.0" standalone="yes"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->standalone())->toBe('yes');
    });

    it('returns null for missing XML declaration attributes', function (): void {
        $template = '<?xml version="1.0"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->encoding())->toBeNull()
            ->and($node->standalone())->toBeNull();
    });

    it('handles single-quoted XML declaration attributes', function (): void {
        $template = "<?xml version='1.1' encoding='ISO-8859-1'?>";

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->version())->toBe('1.1')
            ->and($node->encoding())->toBe('ISO-8859-1');
    });

    it('parses full XML declaration with all attributes', function (): void {
        $template = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->version())->toBe('1.0')
            ->and($node->encoding())->toBe('UTF-8')
            ->and($node->standalone())->toBe('no');
    });

    it('gets content without delimiters from XML declaration', function (): void {
        $template = '<?xml version="1.0" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->content())->toBe(' version="1.0" encoding="UTF-8"');
    });

    it('provides attributes collection like ElementNode', function (): void {
        $template = '<?xml version="1.0" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($node->attributes())->toBeInstanceOf(Attributes::class)
            ->and($node->attributes()->count())->toBe(2)
            ->and($node->attributes()->has('version'))->toBeTrue()
            ->and($node->attributes()->has('encoding'))->toBeTrue()
            ->and($node->attributes()->has('standalone'))->toBeFalse();
    });

    it('iterates over XML declaration attributes', function (): void {
        $template = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class);

        $attrs = $node->attributes()->all();
        expect($attrs)->toHaveCount(3)
            ->and($attrs[0]->nameText())->toBe('version')
            ->and($attrs[0]->valueText())->toBe('1.0')
            ->and($attrs[1]->nameText())->toBe('encoding')
            ->and($attrs[1]->valueText())->toBe('UTF-8')
            ->and($attrs[2]->nameText())->toBe('standalone')
            ->and($attrs[2]->valueText())->toBe('yes');
    });

    it('supports Blade echo in XML declaration attribute value', function (): void {
        $template = '<?xml version="{{ $version }}" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($doc->render())->toBe($template);

        $versionAttr = $node->attributes()->get('version');
        expect($versionAttr)->not->toBeNull()
            ->and($versionAttr->hasComplexValue())->toBeTrue();
    });

    it('supports raw echo in XML declaration attribute value', function (): void {
        $template = '<?xml version="{!! $version !!}" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($doc->render())->toBe($template);

        $versionAttr = $node->attributes()->get('version');
        expect($versionAttr)->not->toBeNull()
            ->and($versionAttr->hasComplexValue())->toBeTrue();
    });

    it('supports mixed static and dynamic content in XML declaration value', function (): void {
        $template = '<?xml version="1.{{ $minor }}" encoding="UTF-8"?>';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asXmlDeclaration();

        expect($node)->toBeInstanceOf(XmlDeclarationNode::class)
            ->and($doc->render())->toBe($template);

        $versionAttr = $node->attributes()->get('version');
        expect($versionAttr)->not->toBeNull()
            ->and($versionAttr->hasComplexValue())->toBeTrue();
    });

    it('renders XML declaration with Blade correctly', function (): void {
        $template = '<?xml version="{{ $v }}" encoding="{{ $enc }}"?>';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });
});
