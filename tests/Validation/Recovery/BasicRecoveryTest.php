<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\Elements\StrayClosingTagNode;
use Forte\Ast\TextNode;

describe('Basic Recovery', function (): void {
    test('directive with HTML in args preserves content', function (): void {
        $template = '@include( <div>bar</div>) tail';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $directive = $doc->findDirectiveByName('include');

        expect($directive)->not()->toBeNull()
            ->and($directive->nameText())->toBe('include')
            ->and($directive->arguments())->toContain('<div>bar</div>');
    });

    test('directive with PHP in args preserves content', function (): void {
        $template = '@include($a, <?php echo 1; ?>)';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $directive = $doc->findDirectiveByName('include');

        expect($directive)->not()->toBeNull()
            ->and($directive->nameText())->toBe('include')
            ->and($directive->arguments())->toContain('<?php');
    });

    test('directive with nested directive in args preserves content', function (): void {
        $template = '@include($x, @yield("slot")) done';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $include = $doc->findDirectiveByName('include');

        expect($include)->not()->toBeNull()
            ->and($include->nameText())->toBe('include')
            ->and($include->arguments())->toContain('@yield');
    });

    test('directive with Blade comment in args preserves content', function (): void {
        $template = '@include($x {{-- comment --}})';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $include = $doc->findDirectiveByName('include');

        expect($include)->not()->toBeNull()
            ->and($include->nameText())->toBe('include')
            ->and($include->arguments())->toContain('{{--');
    });

    test('synthetic greater-than is emitted for incomplete opening tag', function (): void {
        $template = '<div class="x" <span>y</span>';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $div = $doc->findElementByName('div');

        expect($div)->not()->toBeNull()
            ->and($div->tagNameText())->toBe('div')
            ->and($div->isSelfClosing())->toBeFalse()
            ->and($div->hasSyntheticClosing())->toBeTrue();

        $span = $div->firstChildOfType(ElementNode::class)->asElement();

        expect($span)->not()->toBeNull()
            ->and($span->tagNameText())->toBe('span')
            ->and($span->isPaired())->toBeTrue();
    });

    test('top-level unpaired closing tag yields StrayClosingTagNode', function (): void {
        $template = '</span>Leading text<div>ok</div>';

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $unpaired = $doc->getChildAt(0)->asStrayClosingTag();
        expect($unpaired)->toBeInstanceOf(StrayClosingTagNode::class)
            ->and($unpaired->tagNameText())->toBe('span');

        $hasText = $doc->firstChildWhere(
            fn ($n) => $n instanceof TextNode && str_contains($n->getDocumentContent(), 'Leading text')
        ) !== null;
        $div = $doc->findElementByName('div');

        expect($hasText)->toBeTrue()
            ->and($div)->not()->toBeNull()
            ->and($div->isPaired())->toBeTrue();
    });

    test('outer block directives bound element children without leaking closing to inside element', function (): void {
        $template = <<<'BLADE'
@if(true)
    <div><em>in</em></div>
@else
    <div>out</div>
@endif
BLADE;

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $ifBlock = $doc->findBlockDirectiveByName('if');

        expect($ifBlock)->not()->toBeNull()
            ->and($ifBlock->nameText())->toBe('if');

        $startDir = $ifBlock->startDirective();
        $endDir = $ifBlock->endDirective();

        expect($startDir)->not()->toBeNull()
            ->and($startDir->nameText())->toBe('if')
            ->and($endDir)->not()->toBeNull()
            ->and($endDir->nameText())->toBe('endif');

        $directiveNames = array_map(
            fn ($d) => $d->nameText(),
            $ifBlock->getChildrenOfType(DirectiveNode::class)
        );

        expect($directiveNames)->toContain('if')
            ->and($directiveNames)->toContain('else')
            ->and($directiveNames)->toContain('endif');
    });

    test('escaped Blade sequences are emitted as escape and text nodes (@@, @{{ }})', function (): void {
        $template = <<<'BLADE'
@@ This at-sign should be literal
@{{ name }}
BLADE;

        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $hasDirective = $doc->firstChildWhere(
            fn ($n) => $n instanceof DirectiveNode || $n instanceof DirectiveBlockNode
        ) !== null;
        $hasEcho = $doc->firstChildOfType(EchoNode::class) !== null;

        expect($hasDirective)->toBeFalse()
            ->and($hasEcho)->toBeFalse();
    });

    test('empty heredoc label is handled gracefully', function (): void {
        $template = '@php(<<<
;
LABEL)';

        $doc = $this->parse($template);
        $result = $doc->render();

        expect($result)->toBeString();
    });

    test('directive collection does not enter infinite loop on malformed input', function (): void {
        $template = '@if($x)@if($y)broken';

        $start = microtime(true);
        $doc = $this->parse($template);
        $result = $doc->render();
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.05)
            ->and($result)->toBeString();
    });

    test('deeply nested unclosed directives do not hang', function (): void {
        $template = '@if($a)@if($b)@if($c)@if($d)@if($e)content';

        $start = microtime(true);
        $doc = $this->parse($template);
        $result = $doc->render();
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(0.05)
            ->and($result)->toBe($template);
    });

    test('parser rewind at start of document does not crash', function (): void {
        $template = '@if($x)content@endif';
        $doc = $this->parse($template);
        $result = $doc->render();

        expect($result)->toBe($template);
    });

    test('empty document does not crash on rewind', function (): void {
        $template = '';
        $doc = $this->parse($template);
        $result = $doc->render();

        expect($result)->toBe($template);
    });

    test('malformed attributes with directive-like content', function (): void {
        $template = '<div class="@if($x)active@endif">content</div>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $div = $doc->findElementByName('div');

        expect($div)->not()->toBeNull()
            ->and($div->tagNameText())->toBe('div');
    });

    test('incomplete directive block at EOF', function (): void {
        $template = '@if($condition)content without endif';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('mismatched closing directive', function (): void {
        $template = '@if($x)content@endforeach';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('multiple consecutive opening directives', function (): void {
        $template = '@if($a)@foreach($items as $item)@if($b)nested@endif@endforeach@endif';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);
    });

    test('directive inside element attribute position', function (): void {
        $template = '<div @if($show)visible@endif class="base">content</div>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $div = $doc->findElementByName('div');

        expect($div)->not()->toBeNull();
    });

    test('blade echo in attribute value', function (): void {
        $template = '<a href="{{ $url }}">link</a>';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $a = $doc->findElementByName('a');

        expect($a)->not()->toBeNull()
            ->and($a->isPaired())->toBeTrue();
    });

    test('self-closing element with blade attributes', function (): void {
        $template = '<input type="{{ $type }}" value="{{ $value }}" />';
        $doc = $this->parse($template);

        expect($doc->render())->toBe($template);

        $input = $doc->findElementByName('input');

        expect($input)->not()->toBeNull()
            ->and($input->isSelfClosing())->toBeTrue();
    });
});
