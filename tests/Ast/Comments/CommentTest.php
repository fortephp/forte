<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Document\Document;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\Elements\ConditionalCommentNode;
use Forte\Ast\TextNode;

describe('Comment Parsing', function (): void {
    it('parses basic HTML comment', function (): void {
        $source = '<!-- test -->';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(CommentNode::class)
            ->and($children[0]->asComment()->content())->toBe(' test ')
            ->and($children[0]->render())->toBe($source);
    });

    it('parses empty HTML comment', function (): void {
        $source = '<!---->';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(CommentNode::class)
            ->and($children[0]->asComment()->isEmpty())->toBeTrue()
            ->and($children[0]->render())->toBe($source);
    });

    it('parses Blade comment', function (): void {
        $source = '{{-- test comment --}}';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(BladeCommentNode::class)
            ->and($children[0]->asBladeComment()->content())->toBe(' test comment ')
            ->and($children[0]->asBladeComment()->text())->toBe('test comment')
            ->and($children[0]->getDocumentContent())->toBe($source);
    });

    it('parses empty Blade comment', function (): void {
        $source = '{{----}}';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(BladeCommentNode::class)
            ->and($children[0]->asBladeComment()->isEmpty())->toBeTrue();
    });

    it('parses HTML comment with mixed content', function (): void {
        $source = 'Text before <!-- comment --> text after';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('Text before ')
            ->and($children[1])->toBeInstanceOf(CommentNode::class)
            ->and($children[1]->asComment()->content())->toBe(' comment ')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[2]->getDocumentContent())->toBe(' text after')
            ->and($doc->render())->toBe($source);
    });

    it('parses multiline HTML comment', function (): void {
        $source = <<<'HTML'
<!--
    This is a
    multiline comment
-->
HTML;
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(CommentNode::class)
            ->and($children[0]->render())->toBe($source);
    });

    it('parses multiline Blade comment', function (): void {
        $source = <<<'BLADE'
{{--
    This is a
    multiline Blade comment
--}}
BLADE;
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(BladeCommentNode::class)
            ->and($children[0]->getDocumentContent())->toBe($source);
    });

    it('detects conditional comments', function (): void {
        $source = '<!--[if IE]>content<![endif]-->';
        $doc = Document::parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(ConditionalCommentNode::class)
            ->and($children[0]->isConditionalComment())->toBeTrue()
            ->and($children[0]->render())->toBe($source);
    });
});
