<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\TextNode;

describe('Comment Nodes', function (): void {
    it('parses basic blade comments', function (): void {
        $template = '{{-- This is a comment --}}';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->content())->toBe(' This is a comment ');
    });

    it('parses comments containing things that look like blade', function (): void {
        $template = <<<'EOT'
{{-- This is a comment with a @can inside --}}
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->content())->toBe(' This is a comment with a @can inside ');
    });

    it('parses comments containing curly braces', function (): void {
        $template = <<<'EOT'
{{-- This is a comment {{ --}}
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->content())->toBe(' This is a comment {{ ');
    });

    it('parses multiple comments', function (): void {
        $template = <<<'EOT'
{{-- This is a comment --}}Literal{{-- This is another comment --}}
EOT;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment1 = $nodes[0]->asBladeComment();
        $text = $nodes[1]->asText();
        $comment2 = $nodes[2]->asBladeComment();
        expect($nodes)->toHaveCount(3)
            ->and($comment1)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment1->content())->toBe(' This is a comment ')
            ->and($text)->toBeInstanceOf(TextNode::class)
            ->and($text->getContent())->toBe('Literal')
            ->and($comment2)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment2->content())->toBe(' This is another comment ');
    });

    it('comments with braces do not confuse the parser', function (): void {
        $template = <<<'EOT'
{{--a{{ $one }}b{{ $two }}c{{ $three }}d--}}a{{ $one }}b{{ $two }}c{{ $three }}d
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        $text1 = $nodes[1]->asText();
        $echo1 = $nodes[2]->asEcho();
        $text2 = $nodes[3]->asText();
        $echo2 = $nodes[4]->asEcho();
        $text3 = $nodes[5]->asText();
        $echo3 = $nodes[6]->asEcho();
        $text4 = $nodes[7]->asText();

        expect($nodes)->toHaveCount(8)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($text1)->toBeInstanceOf(TextNode::class)
            ->and($echo1)->toBeInstanceOf(EchoNode::class)
            ->and($text2)->toBeInstanceOf(TextNode::class)
            ->and($echo2)->toBeInstanceOf(EchoNode::class)
            ->and($text3)->toBeInstanceOf(TextNode::class)
            ->and($echo3)->toBeInstanceOf(EchoNode::class)
            ->and($text4)->toBeInstanceOf(TextNode::class)
            ->and($comment->content())->toBe('a{{ $one }}b{{ $two }}c{{ $three }}d')
            ->and($text1->getContent())->toBe('a')
            ->and($echo1->content())->toBe(' $one ')
            ->and($echo1->isEscaped())->toBeTrue()
            ->and($text2->getContent())->toBe('b')
            ->and($echo2->content())->toBe(' $two ')
            ->and($echo2->isEscaped())->toBeTrue()
            ->and($text3->getContent())->toBe('c')
            ->and($echo3->content())->toBe(' $three ')
            ->and($echo3->isEscaped())->toBeTrue()
            ->and($text4->getContent())->toBe('d');
    });

    it('parses comments without spaces', function (): void {
        $template = '{{--this is a comment--}}';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->content())->toBe('this is a comment');
    });

    it('parses comments that contain opening directives', function (): void {
        $template = '{{-- @foreach() --}}';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->content())->toBe(' @foreach() ');
    });

    test('blade comment render reconstructs closed block', function (): void {
        $template = <<<'EOT'
{{-- Hello {{ name }} --}}
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->render())->toBe($template);
    });

    test('blade comment render reconstructs unclosed block', function (): void {
        $template = <<<'EOT'
{{-- This has no end
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->render())->toBe($template);
    });

    test('html comment render reconstructs with children and round-trips', function (): void {
        $template = <<<'EOT'
<!-- a {{ $x }} b -->
EOT;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(CommentNode::class)
            ->and($comment->render())->toBe($template);
    });

    test('html comment render reconstructs when unclosed', function (): void {
        $template = <<<'EOT'
<!-- partial
EOT;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asComment();
        expect($nodes)->toHaveCount(1)
            ->and($comment)->toBeInstanceOf(CommentNode::class)
            ->and($comment->render())->toBe($template);
    });

    it('parses HTML comment before element', function (): void {
        $html = <<<'HTML'
<!-- This is a comment -->
<div>Content</div>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asComment();
        expect($nodes)->toHaveCount(3)
            ->and($comment)->toBeInstanceOf(CommentNode::class);
    });

    it('parses Blade comment before element', function (): void {
        $html = <<<'HTML'
{{-- This is a Blade comment --}}
<div>Content</div>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($nodes)->toHaveCount(3)
            ->and($comment)->toBeInstanceOf(BladeCommentNode::class);
    });

    it('handles multiple consecutive comments', function (): void {
        $html = <<<'HTML'
<!-- First comment -->
<!-- Second comment -->
<!-- Third comment -->
<div>Content</div>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $comments = array_filter($nodes, fn ($n) => $n instanceof CommentNode);
        expect($comments)->toHaveCount(3);
    });

    it('handles mixed comment types in sequence', function (): void {
        $html = <<<'HTML_WRAP'
        <!-- HTML comment -->
        {{-- Blade comment --}}
        <!-- Another HTML comment -->
        {{-- Another Blade comment --}}
        <div>Content</div>
        HTML_WRAP;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $htmlComments = array_filter($nodes, fn ($n) => $n instanceof CommentNode);
        $bladeComments = array_filter($nodes, fn ($n) => $n instanceof BladeCommentNode);

        expect($htmlComments)->toHaveCount(2)
            ->and($bladeComments)->toHaveCount(2);
    });

    it('parses comments with special characters', function (): void {
        $html = '<!-- Comment with <tags> & "quotes" --><div></div>';

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asComment();
        expect($comment)->toBeInstanceOf(CommentNode::class)
            ->and($comment->getDocumentContent())->toContain('<tags>')
            ->and($comment->getDocumentContent())->toContain('"quotes"');
    });

    it('parses multiline comments', function (): void {
        $html = <<<'HTML'
<!--
    Multiline
    comment
    content
-->
<div>Content</div>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asComment();
        expect($comment)->toBeInstanceOf(CommentNode::class)
            ->and($comment->getDocumentContent())->toContain('Multiline');
    });

    it('parses Blade multiline comments', function (): void {
        $html = <<<'HTML'
{{--
    Multiline
    Blade comment
--}}
<div>Content</div>
HTML;

        $doc = $this->parse($html);
        $nodes = $doc->getChildren();

        $comment = $nodes[0]->asBladeComment();
        expect($comment)->toBeInstanceOf(BladeCommentNode::class)
            ->and($comment->content())->toContain('Blade comment');
    });
});
