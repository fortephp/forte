<?php

declare(strict_types=1);

use Forte\Ast\TextNode;
use Forte\Ast\VerbatimNode;

describe('Verbatim Blocks', function (): void {
    test('verbatim is parsed as VerbatimNode with content and hasClose', function (): void {
        $template = <<<'EOT'
start @verbatim start

start
{{-- comment!!! --}}3
s1@props-two(['color' => (true ?? 'gray')])
s2@directive
@directive something
s3@props-three  (['color' => (true ?? 'gray')])
@props(['color' => 'gray'])
{!! $dooblyDoo !!}1
<ul {{ $attributes->merge(['class' => 'bg-'.$color.'-200']) }}>
{{ $slot }}
</ul>

end @endverbatim end
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1])->toBeInstanceOf(VerbatimNode::class)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class);

        $text1 = $nodes[0]->asText();
        expect($text1->getContent())->toBe('start ');

        $verbatim = $nodes[1]->asVerbatim();

        $innerContent = " start\n\nstart\n{{-- comment!!! --}}3\ns1@props-two(['color' => (true ?? 'gray')])\ns2@directive\n@directive something\ns3@props-three  (['color' => (true ?? 'gray')])\n@props(['color' => 'gray'])\n{!! \$dooblyDoo !!}1\n<ul {{ \$attributes->merge(['class' => 'bg-'.\$color.'-200']) }}>\n{{ \$slot }}\n</ul>\n\nend ";

        expect($verbatim->content())->toBe($innerContent)
            ->and($verbatim->hasClose())->toBeTrue()
            ->and($nodes[2]->asText()->getContent())->toBe(' end')
            ->and($doc->render())->toBe($template);
    });

    test('verbatim with simple content', function (): void {
        $template = '@verbatim content @endverbatim';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(VerbatimNode::class);

        $verbatim = $nodes[0]->asVerbatim();

        expect($verbatim->content())->toBe(' content ')
            ->and($verbatim->hasClose())->toBeTrue()
            ->and($doc->render())->toBe($template);
    });

    test('verbatim renders with raw content', function (): void {
        $template = <<<'EOT'
@verbatim
Hello {{ name }}!
@endverbatim
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(VerbatimNode::class);

        $verbatim = $nodes[0]->asVerbatim();

        expect($verbatim->content())->toBe("\nHello {{ name }}!\n")
            ->and($verbatim->hasClose())->toBeTrue()
            ->and($verbatim->render())->toBe($template)
            ->and($doc->render())->toBe($template);
    });

    test('verbatim handles unclosed blocks by continuing to EOF', function (): void {
        $template = <<<'EOT'
@verbatim
This has no end
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(VerbatimNode::class);

        $verbatim = $nodes[0]->asVerbatim();

        expect($verbatim->content())->toBe("\nThis has no end")
            ->and($verbatim->hasClose())->toBeFalse()
            ->and($verbatim->render())->toBe($template)
            ->and($doc->render())->toBe($template);
    });
});
