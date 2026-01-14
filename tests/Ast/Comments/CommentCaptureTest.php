<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\Document\Document;
use Forte\Ast\Elements\CommentNode;
use Forte\Ast\TextNode;

describe('Comment Capture', function (): void {
    test('blade comment node ends at --}} and preserves following text', function (): void {
        $snippet = <<<'BLADE'
{{-- Blade comment --}}a
 a
BLADE;

        $doc = Document::parse($snippet);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($snippet)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(BladeCommentNode::class)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class);

        $comment = $nodes[0];
        $commentRender = $comment->render();

        expect(str_ends_with($commentRender, '--}}'))->toBeTrue()
            ->and(str_contains($commentRender, '--}}a'))->toBeFalse()
            ->and($commentRender)->toBe('{{-- Blade comment --}}');

        $expectedText = <<<'EXPECTED'
a
 a
EXPECTED;

        expect($nodes[1]->asText()->getContent())
            ->toBe($expectedText);
    });

    test('HTML comment node ends at --> and preserves following text', function (): void {
        $template = "<!-- An HTML comment -->\nMore text";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(CommentNode::class)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getContent())->toContain("\n");
    });

    test('blade comment with newlines preserves structure', function (): void {
        $template = <<<'BLADE'
{{--
Multi-line
Blade comment
--}}
BLADE;

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();
        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(BladeCommentNode::class);

        $content = $nodes[0]->asBladeComment()->content();
        expect($content)->toContain('Multi-line')
            ->and($content)->toContain('Blade comment');
    });

    test('HTML comment with Blade constructs inside preserves them as text', function (): void {
        $template = '<!-- {{ $var }} @if($x) -->';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();
        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(CommentNode::class);

        $content = $nodes[0]->content();
        expect($content)->toContain('{{ $var }}')
            ->and($content)->toContain('@if($x)');
    });

    test('unclosed blade comment captures to EOF', function (): void {
        $template = '{{-- unclosed comment';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();
        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(BladeCommentNode::class);
    });

    test('unclosed HTML comment captures to EOF', function (): void {
        $template = '<!-- unclosed comment';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);

        $nodes = $doc->getChildren();
        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(CommentNode::class);
    });

    test('HTML comment with trailing newline from heredoc', function (): void {
        $template = <<<'HTML_WRAP'
        <!-- An HTML comment -->

        HTML_WRAP;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(CommentNode::class)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getContent())->toBe("\n");
    });

    test('HTML comment with explicit leading whitespace preserves all nodes', function (): void {
        $template = "        <!-- An HTML comment -->\n\n        ";

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getContent())->toBe('        ')
            ->and($nodes[1])->toBeInstanceOf(CommentNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getContent())->toBe("\n\n        ");
    });
});
