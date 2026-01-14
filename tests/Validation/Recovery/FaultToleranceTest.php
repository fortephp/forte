<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('Parser Fault Tolerance', function (): void {
    it('tolerates incomplete mixed HTML and Blade', function (): void {
        $template = 'ad <div data="{{ hi';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(ElementNode::class);

        $el = $nodes[1]->asElement();
        expect((string) $el->tagName())->toBe('div');

        $attrs = $el->getAttributes();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->nameText())->toBe('data')
            ->and($attr->quote())->toBe('"');

        $value = $attr->valueText();
        expect($value)->toContain('{{')
            ->and($value)->toContain('hi');
    });

    it('recovers when echo is interrupted by another echo start', function (): void {
        $template = '{{ $title   {{ $name }}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->isRaw())->toBeFalse();
    });

    it('recovers when raw echo is interrupted by echo start', function (): void {
        $template = '{!! $count  {{ $other }}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asEcho())->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->asEcho()->isRaw())->toBeFalse();
    });

    it('tolerates incomplete echo at EOF', function (): void {
        $template = '{{ $user->name';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(EchoNode::class);
    });

    it('tolerates attribute with interrupted echo and missing quote', function (): void {
        $template = '<input value="{{ $a {{ $b }}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(ElementNode::class);

        $el = $nodes[0]->asElement();
        $attrs = $el->getAttributes();
        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0];
        expect($attr->nameText())->toBe('value')
            ->and($attr->quote())->toBe('"');

        $value = $attr->valueText();
        expect($value)->toContain('{{')
            ->and($value)->toContain('$a')
            ->and($value)->toContain('$b');
    });

    it('tolerates directive args interrupted by echo start', function (): void {
        $template = '@include($a, {{ $b }}) content';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $directive = $doc->findDirectiveByName('include');

        expect($directive)->not()->toBeNull()
            ->and($directive->arguments())->toContain('$a')
            ->and($directive->arguments())->toContain('{{');
    });

    it('tolerates unterminated Blade comment followed by echo start', function (): void {
        $template = 'Text {{-- comment {{ $a';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($doc->render())->toBe($template)
            ->and($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(BladeCommentNode::class);
    });

    it('tolerates multiple incomplete constructs', function (): void {
        $template = '{{ $a {!! $b @if($c <div class="{{ $d';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('tolerates deeply nested incomplete constructs', function (): void {
        $template = '<div><span><a href="{{ $url"><b>{{ $text';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('tolerates directive in incomplete element', function (): void {
        $template = '<div @if($show)class="active"';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('tolerates script with incomplete Blade', function (): void {
        $template = '<script>var x = {{ $json';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });

    it('tolerates style with incomplete Blade', function (): void {
        $template = '<style>.class { color: {{ $color';

        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    });
});
