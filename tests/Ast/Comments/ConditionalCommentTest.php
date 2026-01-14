<?php

declare(strict_types=1);

use Forte\Ast\Document\Document;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ConditionalCommentNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;
use Forte\Parser\NodeKind;

describe('Conditional Comment Parsing', function (): void {
    it('parses basic IE conditional comment', function (): void {
        $source = '<!--[if IE]><p>You are using Internet Explorer</p><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $comment = $children[0]->asConditionalComment();

        expect($comment)->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($comment->kind())->toBe(NodeKind::ConditionalComment)
            ->and($comment->isConditionalComment())->toBeTrue()
            ->and($comment->condition())->toBe('IE')
            ->and($comment->hasClose())->toBeTrue()
            ->and($comment->isDownlevelRevealed())->toBeTrue()
            ->and($comment->isDownlevelHidden())->toBeFalse()
            ->and($comment->content())->toBe('<p>You are using Internet Explorer</p>')
            ->and($comment->render())->toBe($source);
    });

    it('parses IE conditional comment with version check', function (): void {
        $source = '<!--[if lt IE 9]><script src="html5shiv.js"></script><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $comment = $children[0]->asConditionalComment();

        expect($comment)->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($comment->kind())->toBe(NodeKind::ConditionalComment)
            ->and($comment->condition())->toBe('lt IE 9')
            ->and($comment->hasClose())->toBeTrue()
            ->and($comment->content())->toBe('<script src="html5shiv.js"></script>')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with parentheses condition - mso', function (): void {
        $source = '<!--[if (gte mso 9)]><xml>Office content</xml><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $comment = $children[0]->asConditionalComment();

        expect($comment)->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($comment->kind())->toBe(NodeKind::ConditionalComment)
            ->and($comment->condition())->toBe('(gte mso 9)')
            ->and($comment->hasClose())->toBeTrue()
            ->and($comment->content())->toBe('<xml>Office content</xml>')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with OR condition', function (): void {
        $source = '<!--[if (gte mso 9)|(IE)]><table><tr><td>Outlook</td></tr></table><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $comment = $children[0]->asConditionalComment();

        expect($comment)->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($comment->kind())->toBe(NodeKind::ConditionalComment)
            ->and($comment->condition())->toBe('(gte mso 9)|(IE)')
            ->and($comment->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with AND condition', function (): void {
        $source = '<!--[if (gt IE 5)&(lt IE 7)]><link rel="stylesheet" type="text/css" href="ie6.css" /><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[0]->asConditionalComment()->condition())->toBe('(gt IE 5)&(lt IE 7)')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with NOT condition', function (): void {
        $source = '<!--[if !IE]><p>Not Internet Explorer</p><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[0]->asConditionalComment()->condition())->toBe('!IE')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with lte version', function (): void {
        $source = '<!--[if lte IE 8]><link href="ie8.css"><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[0]->asConditionalComment()->condition())->toBe('lte IE 8')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with gte version', function (): void {
        $source = '<!--[if gte IE 10]><meta content="ie=edge"><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[0]->asConditionalComment()->condition())->toBe('gte IE 10')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with gt version', function (): void {
        $source = '<!--[if gt IE 7]><div>Modern IE</div><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[0]->asConditionalComment()->condition())->toBe('gt IE 7')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment full structure with exact children', function (): void {
        $source = '<!--[if IE]><p>IE content</p><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        /** @var ConditionalCommentNode $conditional */
        $conditional = $children[0];

        expect($conditional->kind())->toBe(NodeKind::ConditionalComment)
            ->and($conditional->condition())->toBe('IE')
            ->and($conditional->hasClose())->toBeTrue()
            ->and($conditional->isDownlevelRevealed())->toBeTrue()
            ->and($conditional->isDownlevelHidden())->toBeFalse()
            ->and($conditional->content())->toBe('<p>IE content</p>')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with mso condition', function (): void {
        $source = '<!--[if (gte mso 9)]><xml><o:OfficeDocumentSettings></o:OfficeDocumentSettings></xml><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        /** @var ConditionalCommentNode $conditional */
        $conditional = $children[0];

        expect($conditional->kind())->toBe(NodeKind::ConditionalComment)
            ->and($conditional->condition())->toBe('(gte mso 9)')
            ->and($conditional->hasClose())->toBeTrue()
            ->and($conditional->content())->toBe('<xml><o:OfficeDocumentSettings></o:OfficeDocumentSettings></xml>')
            ->and($doc->render())->toBe($source);
    });

    it('parses multiple conditional comments in sequence', function (): void {
        $source = '<!--[if IE]><p>IE</p><![endif]--><!--[if !IE]><p>Not IE</p><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(2)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[1])->toBeInstanceOf(ConditionalCommentNode::class);

        $first = $children[0]->asConditionalComment();
        $second = $children[1]->asConditionalComment();

        expect($first->condition())->toBe('IE')
            ->and($first->content())->toBe('<p>IE</p>')
            ->and($first->hasClose())->toBeTrue()
            ->and($second->condition())->toBe('!IE')
            ->and($second->content())->toBe('<p>Not IE</p>')
            ->and($second->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment with text before and after', function (): void {
        $source = 'before<!--[if IE]>middle<![endif]-->after';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[1])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('before')
            ->and($children[1]->asConditionalComment()->condition())->toBe('IE')
            ->and($children[1]->asConditionalComment()->content())->toBe('middle')
            ->and($children[2]->getDocumentContent())->toBe('after')
            ->and($doc->render())->toBe($source);
    });

    it('parses conditional comment in HTML document', function (): void {
        $source = '<html><head><!--[if lt IE 9]><script src="html5.js"></script><![endif]--></head></html>';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class)
            ->and($children[0]->asElement()->tagNameText())->toBe('html');

        $html = $children[0]->asElement();
        $htmlChildren = $html->getChildren();

        expect($htmlChildren)->toHaveCount(1)
            ->and($htmlChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($htmlChildren[0]->asElement()->tagNameText())->toBe('head');

        $head = $htmlChildren[0]->asElement();
        $headChildren = $head->getChildren();

        expect($headChildren)->toHaveCount(1)
            ->and($headChildren[0])->toBeInstanceOf(ConditionalCommentNode::class);

        $conditional = $headChildren[0]->asConditionalComment();

        expect($conditional->condition())->toBe('lt IE 9')
            ->and($conditional->content())->toBe('<script src="html5.js"></script>')
            ->and($conditional->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($source);
    });

    test('conditional comment with complex nested content', function (): void {
        $source = '<!--[if IE]><div class="ie-only"><p>Paragraph</p></div><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        $conditional = $children[0]->asConditionalComment();

        expect($conditional->condition())->toBe('IE')
            ->and($conditional->content())->toBe('<div class="ie-only"><p>Paragraph</p></div>')
            ->and($conditional->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($source);
    });

    test('conditional comment start only', function (): void {
        $source = '<!--[if IE]>content';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        $conditional = $children[0]->asConditionalComment();

        expect($conditional->condition())->toBe('IE')
            ->and($conditional->hasClose())->toBeFalse()
            ->and($doc->render())->toBe($source);
    });

    test('extracts condition from various patterns', function (string $source, string $expectedCondition): void {
        $doc = Document::parse($source);
        $node = $doc->firstChild()->asConditionalComment();

        expect($node)->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($node->condition())->toBe($expectedCondition);
    })->with([
        ['<!--[if IE]>content<![endif]-->', 'IE'],
        ['<!--[if lt IE 9]>content<![endif]-->', 'lt IE 9'],
        ['<!--[if lte IE 8]>content<![endif]-->', 'lte IE 8'],
        ['<!--[if gt IE 7]>content<![endif]-->', 'gt IE 7'],
        ['<!--[if gte IE 10]>content<![endif]-->', 'gte IE 10'],
        ['<!--[if !IE]>content<![endif]-->', '!IE'],
        ['<!--[if (gte mso 9)]>content<![endif]-->', '(gte mso 9)'],
        ['<!--[if (gte mso 9)|(IE)]>content<![endif]-->', '(gte mso 9)|(IE)'],
        ['<!--[if (gt IE 5)&(lt IE 7)]>content<![endif]-->', '(gt IE 5)&(lt IE 7)'],
        ['<!--[if IE 6]>content<![endif]-->', 'IE 6'],
        ['<!--[if IE 7]>content<![endif]-->', 'IE 7'],
        ['<!--[if IE 8]>content<![endif]-->', 'IE 8'],
    ]);

    test('standard HTML comment is not conditional', function (): void {
        $source = '<!-- standard -->';

        $doc = Document::parse($source);
        $node = $doc->firstChild()->asComment();

        expect($node)->toBeInstanceOf(CommentNode::class)
            ->and($node)->not->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($node->isConditionalComment())->toBeFalse();
    });

    test('conditional comment with nested table', function (): void {
        $source = '<!--[if (gte mso 9)|(IE)]>
<table width="600" align="center">
<tr>
<td>
<![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        $conditional = $children[0]->asConditionalComment();

        expect($conditional->condition())->toBe('(gte mso 9)|(IE)')
            ->and($conditional->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($source);
    });

    test('paired conditional comments with open/close table elements', function (): void {
        $source = '<body width="100%" align="center">
  <center>
    <!--[if (gte mso 9)|(IE)]><table cellpadding="0" cellspacing="0" border="0" width="600" align="center"><tr><td><![endif]-->
    <div></div>
    <!--[if (gte mso 9)|(IE)]></td></tr></table><![endif]-->
  </center>
</body>';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ElementNode::class)
            ->and($children[0]->asElement()->tagNameText())->toBe('body');

        $body = $children[0]->asElement();
        $bodyChildren = $body->getChildren();

        expect($bodyChildren)->toHaveCount(3)
            ->and($bodyChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($bodyChildren[1])->toBeInstanceOf(ElementNode::class)
            ->and($bodyChildren[1]->asElement()->tagNameText())->toBe('center')
            ->and($bodyChildren[2])->toBeInstanceOf(TextNode::class);

        $center = $bodyChildren[1]->asElement();
        $centerChildren = $center->getChildren();

        expect($centerChildren)->toHaveCount(7)
            ->and($centerChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($centerChildren[1])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($centerChildren[2])->toBeInstanceOf(TextNode::class)
            ->and($centerChildren[3])->toBeInstanceOf(ElementNode::class)
            ->and($centerChildren[3]->asElement()->tagNameText())->toBe('div')
            ->and($centerChildren[4])->toBeInstanceOf(TextNode::class)
            ->and($centerChildren[5])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($centerChildren[6])->toBeInstanceOf(TextNode::class);

        $conditional1 = $centerChildren[1]->asConditionalComment();
        expect($conditional1->condition())->toBe('(gte mso 9)|(IE)')
            ->and($conditional1->hasClose())->toBeTrue()
            ->and($conditional1->content())->toBe('<table cellpadding="0" cellspacing="0" border="0" width="600" align="center"><tr><td>');

        $conditional2 = $centerChildren[5]->asConditionalComment();
        expect($conditional2->condition())->toBe('(gte mso 9)|(IE)')
            ->and($conditional2->hasClose())->toBeTrue()
            ->and($conditional2->content())->toBe('</td></tr></table>')
            ->and($doc->render())->toBe($source);
    });

    test('conditional comment end without start is parsed', function (): void {
        $source = 'text<![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->not->toBeEmpty()
            ->and($doc->render())->toBe($source);
    });

    test('children returns parsed element node', function (): void {
        $source = '<!--[if IE]><p>IE content</p><![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        $conditional = $children[0]->asConditionalComment();
        $innerChildren = $conditional->getChildren();

        expect($innerChildren)->toHaveCount(1)
            ->and($innerChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($innerChildren[0]->asElement()->tagNameText())->toBe('p');
    });

    test('children returns multiple elements', function (): void {
        $source = '<!--[if IE]><div>one</div><div>two</div><![endif]-->';

        $doc = Document::parse($source);
        $innerChildren = $doc->firstChild()->getChildren();

        expect($innerChildren)->toHaveCount(2)
            ->and($innerChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($innerChildren[0]->asElement()->tagNameText())->toBe('div')
            ->and($innerChildren[1])->toBeInstanceOf(ElementNode::class)
            ->and($innerChildren[1]->asElement()->tagNameText())->toBe('div');
    });

    test('children returns mixed nodes with text', function (): void {
        $source = '<!--[if IE]>Hello <b>World</b>!<![endif]-->';

        $doc = Document::parse($source);
        $innerChildren = $doc->firstChild()->getChildren();

        expect($innerChildren)->toHaveCount(3)
            ->and($innerChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($innerChildren[0]->getDocumentContent())->toBe('Hello ')
            ->and($innerChildren[1])->toBeInstanceOf(ElementNode::class)
            ->and($innerChildren[1]->asElement()->tagNameText())->toBe('b')
            ->and($innerChildren[2])->toBeInstanceOf(TextNode::class)
            ->and($innerChildren[2]->getDocumentContent())->toBe('!');
    });

    test('content still returns raw string for backward compatibility', function (): void {
        $source = '<!--[if IE]><p>IE content</p><![endif]-->';

        $doc = Document::parse($source);

        $conditional = $doc->firstChild()->asConditionalComment();

        expect($conditional->content())->toBe('<p>IE content</p>');
    });

    test('empty conditional comment has no children', function (): void {
        $source = '<!--[if IE]><![endif]-->';

        $doc = Document::parse($source);
        $conditional = $doc->firstChild()->asConditionalComment();
        $innerChildren = $conditional->getChildren();

        expect($innerChildren)->toBeEmpty()
            ->and($conditional->content())->toBe('')
            ->and($conditional->isEmpty())->toBeTrue();
    });

    test('unclosed conditional comment parses available content', function (): void {
        $source = '<!--[if IE]><p>content</p>';

        $doc = Document::parse($source);
        $conditional = $doc->firstChild()->asConditionalComment();
        $innerChildren = $conditional->getChildren();

        expect($conditional->hasClose())->toBeFalse()
            ->and($innerChildren)->toHaveCount(1)
            ->and($innerChildren[0])->toBeInstanceOf(ElementNode::class);
    });

    test('render round-trip preserves source', function (): void {
        $source = '<!--[if IE]><p>test</p><![endif]-->';

        $doc = Document::parse($source);

        expect($doc->render())->toBe($source);
    });

    test('nested elements inside conditional comment', function (): void {
        $source = '<!--[if IE]><div class="ie-only"><p>Nested</p></div><![endif]-->';

        $doc = Document::parse($source);
        $innerChildren = $doc->firstChild()->getChildren();

        expect($innerChildren)->toHaveCount(1)
            ->and($innerChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($innerChildren[0]->asElement()->tagNameText())->toBe('div');

        $div = $innerChildren[0]->asElement();
        $divChildren = $div->getChildren();

        expect($divChildren)->toHaveCount(1)
            ->and($divChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($divChildren[0]->asElement()->tagNameText())->toBe('p');
    });

    test('can query elements inside conditional comments', function (): void {
        $source = '<!--[if IE]><script src="ie.js"></script><link href="ie.css"><![endif]-->';

        $doc = Document::parse($source);
        $conditional = $doc->firstChild()->asConditionalComment();
        $innerChildren = $conditional->getChildren();

        expect($innerChildren)->toHaveCount(2);

        $tagNames = array_map(fn ($el) => $el->asElement()->tagNameText(), $innerChildren);
        expect($tagNames)->toBe(['script', 'link']);
    });

    test('conditional comment with text only content', function (): void {
        $source = '<!--[if IE]>Just some text<![endif]-->';

        $doc = Document::parse($source);
        $conditional = $doc->firstChild()->asConditionalComment();
        $innerChildren = $conditional->getChildren();

        expect($innerChildren)->toHaveCount(1)
            ->and($innerChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($innerChildren[0]->getDocumentContent())->toBe('Just some text');
    });

    test('downlevel-hidden conditional comment parses children', function (): void {
        $source = '<!--[if IE]><!--><p>content</p><!--<![endif]-->';

        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class);

        $conditional = $children[0]->asConditionalComment();

        expect($conditional->isDownlevelHidden())->toBeTrue()
            ->and($conditional->condition())->toBe('IE')
            ->and($conditional->content())->toBe('<p>content</p>')
            ->and($doc->render())->toBe($source);

        $innerChildren = $conditional->getChildren();

        expect($innerChildren)->toHaveCount(1)
            ->and($innerChildren[0])->toBeInstanceOf(ElementNode::class)
            ->and($innerChildren[0]->asElement()->tagNameText())->toBe('p');
    });
});
