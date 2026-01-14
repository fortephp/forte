<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\EchoNode;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\PhpTagNode;

describe('PHP HERE/NOWDOC', function (): void {
    test('PHP tag with heredoc skips until end identifier', function (): void {
        $template = "<?php\n".
            "\$txt = <<<EOT\n".
            "Line 1\n".
            "}} should not close Blade here\n".
            "@endphp should not close a Blade block either\n".
            "EOT;\n".
            '?>';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(PhpTagNode::class)
            ->and($nodes[0]->asPhpTag()->isPhpTag())->toBeTrue()
            ->and($nodes[0]->asPhpTag()->content())->toContain('<<<EOT')
            ->and($nodes[0]->asPhpTag()->content())->toContain('EOT;')
            ->and($doc->render())->toBe($template);
    });

    test('Blade @php block with nowdoc is handled', function (): void {
        $template = "@php\n".
            "\$code = <<<'TXT'\n".
            "Nowdoc content\n".
            "}} }} }} should be ignored inside nowdoc\n".
            "TXT;\n".
            '@endphp';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asPhpBlock())->toBeInstanceOf(PhpBlockNode::class)
            ->and($nodes[0]->asPhpBlock()->getDocumentContent())->toContain("<<<'TXT'")
            ->and($nodes[0]->asPhpBlock()->getDocumentContent())->toContain('TXT;')
            ->and($doc->render())->toBe($template);
    });

    test('Blade echo with heredoc content is handled', function (): void {
        $template = "{{ <<<EOT\n".
            "Some echo content\n".
            "@endif should not break here\n".
            'EOT; }}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asEcho())->toBeInstanceOf(EchoNode::class)
            ->and($nodes[0]->asEcho()->isEscaped())->toBeTrue()
            ->and($nodes[0]->asEcho()->content())->toContain('<<<EOT')
            ->and($nodes[0]->asEcho()->content())->toContain('EOT;')
            ->and($doc->render())->toBe($template);
    });

    test('Raw echo with nowdoc content is handled', function (): void {
        $template = "{!! <<<'TXT'\n".
            "Raw echo nowdoc body\n".
            "}} should not close echo\n".
            'TXT; !!}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asEcho())->toBeInstanceOf(EchoNode::class)
            ->and($nodes[0]->asEcho()->isRaw())->toBeTrue()
            ->and($nodes[0]->asEcho()->content())->toContain("<<<'TXT'")
            ->and($nodes[0]->asEcho()->content())->toContain('TXT;')
            ->and($doc->render())->toBe($template);
    });

    test('directive arguments can include heredoc', function (): void {
        $template = <<<'BLADE'
@if (strlen(<<<EOT
Hello from directive args
EOT; ) > 0)
OK
@endif
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->asDirectiveBlock()->getDocumentContent())->toContain('<<<EOT')
            ->and($nodes[0]->asDirectiveBlock()->getDocumentContent())->toContain('EOT;')
            ->and($doc->render())->toBe($template);
    });

    test('directive arguments can include nowdoc', function (): void {
        $template = <<<'BLADE'
@if (strlen(<<<'TXT'
Hello from NOWDOC in args
TXT; ) === 27)
OK
@endif
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0]->asDirectiveBlock())->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($nodes[0]->asDirectiveBlock()->getDocumentContent())->toContain("<<<'TXT'")
            ->and($nodes[0]->asDirectiveBlock()->getDocumentContent())->toContain('TXT;')
            ->and($doc->render())->toBe($template);
    });

    test('directive arguments support multiple heredoc/nowdoc args and commas within bodies', function (): void {
        $template = <<<'BLADE'
@isset(
    <<<EOT
First, body with commas, and }} and @endif should be text
EOT;,
    <<<'TXT'
Second body, with @php and {{ not closing anything
TXT;,
    123,
    'plain'
)
OK
@endisset
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();

        expect($block->getDocumentContent())->toContain('<<<EOT')
            ->and($block->getDocumentContent())->toContain("<<<'TXT'")
            ->and($block->getDocumentContent())->toContain('EOT;')
            ->and($block->getDocumentContent())->toContain('TXT;')
            ->and($block->getDocumentContent())->toContain('First, body with commas')
            ->and($block->getDocumentContent())->toContain('Second body, with @php and {{ not closing anything')
            ->and($doc->render())->toBe($template);
    });

    test('directive arguments handle consecutive heredocs within a single expression', function (): void {
        $template = <<<'BLADE'
@if (strlen(<<<A
abc,def
A;) + strlen(<<<B
xyz
B;) > 0)
OK
@endif
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[0]->asDirectiveBlock();

        expect($block->getDocumentContent())->toContain('<<<A')
            ->and($block->getDocumentContent())->toContain('A;')
            ->and($block->getDocumentContent())->toContain('<<<B')
            ->and($block->getDocumentContent())->toContain('B;')
            ->and($block->getDocumentContent())->toContain('abc,def')
            ->and($doc->render())->toBe($template);
    });
});
