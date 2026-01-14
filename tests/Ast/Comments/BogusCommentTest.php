<?php

declare(strict_types=1);

use Forte\Ast\Elements\BogusCommentNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\TextNode;

describe('Bogus Comment Parsing', function (): void {
    it('parses single-dash bogus comment as BogusCommentNode', function (): void {
        $template = '<!- comment text >';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(BogusCommentNode::class)
            ->and($nodes[0]->asBogusComment()->isBogusComment())->toBeTrue()
            ->and($nodes[0]->asBogusComment()->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($template);
    });

    it('parses processing instruction bogus comment as BogusCommentNode', function (): void {
        $template = '<? xml version="1.0" ?>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(BogusCommentNode::class)
            ->and($nodes[0]->asBogusComment()->isBogusComment())->toBeTrue()
            ->and($nodes[0]->asBogusComment()->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($template);
    });

    it('parses bogus comment content correctly', function (): void {
        $template = '<!- inner text >';

        $doc = $this->parse($template);
        $comment = $doc->firstChildOfType(BogusCommentNode::class);

        expect($comment)->toBeInstanceOf(BogusCommentNode::class)
            ->and($comment->isBogusComment())->toBeTrue()
            ->and($comment->content())
            ->toContain('inner text');
    });

    it('parses empty bogus comment', function (): void {
        $template = '<!->';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(BogusCommentNode::class)
            ->and($nodes[0]->isBogusComment())->toBeTrue()
            ->and($doc->render())->toBe($template);
    });

    it('parses unclosed bogus comment', function (): void {
        $template = '<!- unclosed';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $comment = $nodes[0];

        expect($comment)->toBeInstanceOf(BogusCommentNode::class)
            ->and($comment->isBogusComment())->toBeTrue()
            ->and($comment->asBogusComment()->hasClose())->toBeFalse()
            ->and($doc->render())->toBe($template);
    });

    it('bogus comment ends at first > character', function (): void {
        $template = '<!- text > more';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(BogusCommentNode::class)
            ->and($nodes[0]->isBogusComment())->toBeTrue()
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getContent())->toBe(' more')
            ->and($nodes[0]->asBogusComment()->content())->toContain('text');

    });

    it('parses bogus comment with Blade directive inside', function (): void {
        $template = '<!- @if(true) text @endif >';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();
        $comment = $nodes[0];

        expect($comment)->toBeInstanceOf(BogusCommentNode::class)
            ->and($comment->isBogusComment())->toBeTrue()
            ->and($doc->render())->toBe($template)
            ->and($comment->asBogusComment()->content())->toContain('@if');
    });

    test('bogus comments can be reconstructed', function (string $template): void {
        expect($this->parse($template)->render())->toBe($template);
    })->with([
        '<!- simple >',
        '<? processing ?>',
        '<!- with spaces >',
        '<!- @directive >',
    ]);

    it('parses multiple bogus comments', function (): void {
        $template = '<!- first ><div>content</div><!- second >';

        $doc = $this->parse($template);

        expect($doc->getChildrenOfType(BogusCommentNode::class))->toHaveCount(2);
    });

    it('parses mix of comment types', function (): void {
        $template = '<!-- html -->{{-- blade --}}<!- bogus >';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3);

        $lastComment = $nodes[2];
        expect($lastComment)->toBeInstanceOf(BogusCommentNode::class)
            ->and($lastComment->isBogusComment())->toBeTrue();
    });

    test('standard HTML comment is not bogus', function (): void {
        $template = '<!-- standard -->';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes[0])->toBeInstanceOf(CommentNode::class)
            ->and($nodes[0]->isBogusComment())->toBeFalse()
            ->and($nodes[0]->asComment()->hasClose())->toBeTrue();
    });

    test('isEmpty returns true for empty bogus comment', function (): void {
        $template = '<!->';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asBogusComment();

        expect($node)->toBeInstanceOf(BogusCommentNode::class)
            ->and($node->isEmpty())->toBeTrue();
    });

    test('isEmpty returns false for non-empty bogus comment', function (): void {
        $template = '<!- content >';

        $doc = $this->parse($template);
        $node = $doc->getChildren()[0]->asBogusComment();

        expect($node)->toBeInstanceOf(BogusCommentNode::class)
            ->and($node->isEmpty())->toBeFalse();
    });
});
