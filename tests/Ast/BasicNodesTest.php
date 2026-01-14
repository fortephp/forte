<?php

declare(strict_types=1);

use Forte\Ast\BladeCommentNode;
use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\EscapeNode;
use Forte\Ast\PhpBlockNode;
use Forte\Ast\TextNode;
use Forte\Ast\VerbatimNode;

describe('Basic Nodes', function (): void {
    it('parses literal documents', function (): void {
        $blade = 'Hello World';
        $doc = $this->parse($blade);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('Hello World')
            ->and($doc->render())->toBe($blade);
    });

    it('parses basic directives', function ($directive): void {
        $template = 'Start @'.$directive.' End';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('Start ')
            ->and($nodes[1])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[1]->asDirective()->nameText())->toBe(strtolower($directive))
            ->and($nodes[1]->asDirective()->name())->toBe($directive)
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getDocumentContent())->toBe(' End')
            ->and($doc->render())->toBe($template);
    })->with('simple directives');

    it('parses basic directives with multibyte characters', function ($directive): void {
        $template = '🐘 @'.$directive.' 🐘';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('🐘 ')
            ->and($nodes[1])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[1]->asDirective()->nameText())->toBe(strtolower($directive))
            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getDocumentContent())->toBe(' 🐘')
            ->and($doc->render())->toBe($template);
    })->with('simple directives');

    it('parses directives with arguments', function (): void {
        $template = <<<'EOT'
Start @can ('do something') End
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('Start ')
            ->and($nodes[1])->toBeInstanceOf(DirectiveBlockNode::class);

        $block = $nodes[1]->asDirectiveBlock();
        $startDirective = $block->childAt(0)->asDirective();

        expect($startDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($startDirective->nameText())->toBe('can')
            ->and($startDirective->arguments())->toBe("('do something')");

        $textChild = $startDirective->firstChild()->asText();
        expect($startDirective->getChildren())->toHaveCount(1)
            ->and($textChild)->toBeInstanceOf(TextNode::class)
            ->and($textChild->getDocumentContent())->toBe(' End')
            ->and($doc->render())->toBe($template);
    });

    it('parses neighboring nodes', function (): void {
        $template = '{{ $one }}{{ $two }}{{ $three }}';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[0]->asEcho()->content())->toBe(' $one ')

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->asEcho()->content())->toBe(' $two ')

            ->and($nodes[2])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[2]->asEcho()->content())->toBe(' $three ')
            ->and($doc->render())->toBe($template);
    });

    it('parses components with multibyte characters', function (): void {
        $template = <<<'BLADE'
<x-alert>🐘🐘🐘🐘</x-alert>
BLADE;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $element = $nodes[0]->asElement();
        $textChild = $element->firstChild()->asText();

        expect($nodes)->toHaveCount(1)
            ->and($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->getChildren())->toHaveCount(1)
            ->and($textChild)->toBeInstanceOf(TextNode::class)
            ->and($textChild->getDocumentContent())->toBe('🐘🐘🐘🐘')
            ->and($doc->render())->toBe($template);
    });

    it('parses neighboring nodes with literals', function (): void {
        $template = 'a{{ $one }}b{{ $two }}c{{ $three }}d';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(7)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('a')

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->asEcho()->content())->toBe(' $one ')

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getDocumentContent())->toBe('b')

            ->and($nodes[3])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[3]->asEcho()->content())->toBe(' $two ')

            ->and($nodes[4])->toBeInstanceOf(TextNode::class)
            ->and($nodes[4]->asText()->getDocumentContent())->toBe('c')

            ->and($nodes[5])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[5]->asEcho()->content())->toBe(' $three ')

            ->and($nodes[6])->toBeInstanceOf(TextNode::class)
            ->and($nodes[6]->asText()->getDocumentContent())->toBe('d')
            ->and($doc->render())->toBe($template);
    });

    it('ignores escaped nodes', function (): void {
        $template = <<<'EOT'
@@unless
@{{ $variable }}
@{!! $variable }}
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(6)
            ->and($nodes[0])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[0]->asEscape()->content())->toBe('@')
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getDocumentContent())->toBe("@unless\n")

            ->and($nodes[2])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[2]->asEscape()->content())->toBe('@')
            ->and($nodes[3])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3]->asText()->getDocumentContent())->toBe("{{ \$variable }}\n")

            ->and($nodes[4])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[4]->asEscape()->content())->toBe('@')
            ->and($nodes[5])->toBeInstanceOf(TextNode::class)
            ->and($nodes[5]->asText()->getDocumentContent())->toBe('{!! $variable }}')

            ->and($doc->render())->toBe($template);
    });

    it('escapes nodes mixed with other nodes', function (): void {
        $template = <<<'EOT'
@@unless
@{{ $variable }}
@{!! $variable }}

{{ test }}


@{!! $variable }}

    {{ another }}
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(11)
            ->and($nodes[0])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getDocumentContent())->toBe("@unless\n")

            ->and($nodes[2])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[3])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3]->asText()->getDocumentContent())->toBe("{{ \$variable }}\n")

            ->and($nodes[4])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[5])->toBeInstanceOf(TextNode::class)
            ->and($nodes[5]->asText()->getDocumentContent())->toBe("{!! \$variable }}\n\n")

            ->and($nodes[6])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[6]->asEcho()->content())->toBe(' test ')

            ->and($nodes[7])->toBeInstanceOf(TextNode::class)
            ->and($nodes[7]->asText()->getDocumentContent())->toBe("\n\n\n")

            ->and($nodes[8])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[9])->toBeInstanceOf(TextNode::class)
            ->and($nodes[9]->asText()->getDocumentContent())->toContain('{!! $variable }}')

            ->and($nodes[10])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[10]->asEcho()->content())->toBe(' another ')

            ->and($doc->render())->toBe($template);
    });

    it('parses many nodes', function (): void {
        $template = <<<'EOT'
start
    {{-- comment!!! --}}3
    s1@props_two(['color' => (true ?? 'gray')])
    s2@directive
    @directive something
    s3@props_three  (['color' => (true ?? 'gray')])
    @props(['color' => 'gray'])
 {!! $dooblyDoo !!}1
<ul {{ $attributes->merge(['class' => 'bg-'.$color.'-200']) }}>
    {{ $slot }}
</ul>
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(10)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe("start\n    ")

            ->and($nodes[1])->toBeInstanceOf(BladeCommentNode::class)
            ->and($nodes[1]->asBladeComment()->content())->toBe(' comment!!! ')

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getDocumentContent())->toBe("3\n    s1@props_two(['color' => (true ?? 'gray')])\n    s2@directive\n    ")

            ->and($nodes[3])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[3]->asDirective()->nameText())->toBe('directive')
            ->and($nodes[3]->asDirective()->arguments())->toBeNull()

            ->and($nodes[4])->toBeInstanceOf(TextNode::class)
            ->and($nodes[4]->asText()->getDocumentContent())->toBe(" something\n    s3@props_three  (['color' => (true ?? 'gray')])\n    ")

            ->and($nodes[5])->toBeInstanceOf(DirectiveNode::class)
            ->and($nodes[5]->asDirective()->nameText())->toBe('props')
            ->and($nodes[5]->asDirective()->arguments())->toBe("(['color' => 'gray'])")

            ->and($nodes[6])->toBeInstanceOf(TextNode::class)
            ->and($nodes[6]->asText()->getDocumentContent())->toBe("\n ")

            ->and($nodes[7])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[7]->asEcho()->content())->toBe(' $dooblyDoo ')
            ->and($nodes[7]->asEcho()->isRaw())->toBeTrue()

            ->and($nodes[8])->toBeInstanceOf(TextNode::class)
            ->and($nodes[8]->asText()->getDocumentContent())->toBe("1\n");

        $element = $nodes[9]->asElement();
        expect($element)->toBeInstanceOf(ElementNode::class)
            ->and($element->isSelfClosing())->toBeFalse()
            ->and($element->tagNameText())->toBe('ul');

        $attributes = $element->attributes();
        $allAttrs = $attributes->all();

        expect($allAttrs)->toHaveCount(1);

        $elementChildren = $element->getChildren();

        expect($elementChildren)->toHaveCount(3)
            ->and($elementChildren[0])->toBeInstanceOf(TextNode::class)
            ->and($elementChildren[0]->asText()->getDocumentContent())->toBe("\n    ")

            ->and($elementChildren[1])->toBeInstanceOf(EchoNode::class)
            ->and($elementChildren[1]->asEcho()->content())->toBe(' $slot ')
            ->and($elementChildren[1]->asEcho()->isEscaped())->toBeTrue()

            ->and($elementChildren[2])->toBeInstanceOf(TextNode::class)
            ->and($elementChildren[2]->asText()->getDocumentContent())->toBe("\n")
            ->and($doc->render())->toBe($template);
    });

    it('parses simple templates one', function (): void {
        $template = 'The current UNIX timestamp is {{ time() }}.';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('The current UNIX timestamp is ')

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->asEcho()->isEscaped())->toBeTrue()
            ->and($nodes[1]->asEcho()->content())->toBe(' time() ')

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getDocumentContent())->toBe('.')
            ->and($doc->render())->toBe($template);
    });

    it('parses simple templates two', function (): void {
        $template = 'Hello, {!! $name !!}.';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(3)
            ->and($nodes[0])->toBeInstanceOf(TextNode::class)
            ->and($nodes[0]->asText()->getDocumentContent())->toBe('Hello, ')

            ->and($nodes[1])->toBeInstanceOf(EchoNode::class)
            ->and($nodes[1]->asEcho()->isRaw())->toBeTrue()
            ->and($nodes[1]->asEcho()->content())->toBe(' $name ')

            ->and($nodes[2])->toBeInstanceOf(TextNode::class)
            ->and($nodes[2]->asText()->getDocumentContent())->toBe('.')
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template three', function (): void {
        $template = <<<'EOT'
<h1>Laravel</h1>

Hello, @{{ name }}.
EOT;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $h1 = $nodes[0]->asElement();
        $h1Text = $h1->firstChild()->asText();

        expect($nodes)->toHaveCount(4)
            ->and($h1)->toBeInstanceOf(ElementNode::class)
            ->and($h1->tagNameText())->toBe('h1')
            ->and($h1->getChildren())->toHaveCount(1)
            ->and($h1Text)->toBeInstanceOf(TextNode::class)
            ->and($h1Text->getDocumentContent())->toBe('Laravel')

            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getDocumentContent())->toBe("\n\nHello, ")

            ->and($nodes[2])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[2]->asEscape()->content())->toBe('@')

            ->and($nodes[3])->toBeInstanceOf(TextNode::class)
            ->and($nodes[3]->asText()->getDocumentContent())->toBe('{{ name }}.')

            ->and($doc->render())->toBe($template);
    });

    it('parses simple template four', function (): void {
        $template = '@@if';
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        expect($nodes)->toHaveCount(2)
            ->and($nodes[0])->toBeInstanceOf(EscapeNode::class)
            ->and($nodes[0]->asEscape()->content())->toBe('@')

            ->and($nodes[1])->toBeInstanceOf(TextNode::class)
            ->and($nodes[1]->asText()->getDocumentContent())->toBe('@if')

            ->and($doc->render())->toBe($template);
    });

    it('parses simple template five', function (): void {
        $template = <<<'EOT'
<script>
var app = {{ Illuminate\Support\Js::from($array) }};
</script>
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $script = $nodes[0]->asElement();
        expect($nodes)->toHaveCount(1)
            ->and($script)->toBeInstanceOf(ElementNode::class)
            ->and($script->tagNameText())->toBe('script')
            ->and($script->getChildren())->toHaveCount(3)
            ->and($script->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and($script->childAt(0)->asText()->getDocumentContent())->toBe("\nvar app = ")
            ->and($script->childAt(1))->toBeInstanceOf(EchoNode::class)
            ->and($script->childAt(1)->asEcho()->isEscaped())->toBeTrue()
            ->and($script->childAt(1)->asEcho()->content())->toBe(' Illuminate\Support\Js::from($array) ')
            ->and($script->childAt(2))->toBeInstanceOf(TextNode::class)
            ->and($script->childAt(2)->asText()->getDocumentContent())->toBe(";\n")
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template six', function (): void {
        $template = <<<'EOT'
<script>
var app = {{ Js::from($array) }};
</script>
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $script = $nodes[0]->asElement();
        expect($nodes)->toHaveCount(1)
            ->and($script)->toBeInstanceOf(ElementNode::class)
            ->and($script->tagNameText())->toBe('script')
            ->and($script->getChildren())->toHaveCount(3)
            ->and($script->childAt(0))->toBeInstanceOf(TextNode::class)
            ->and($script->childAt(0)->asText()->getDocumentContent())->toBe("\nvar app = ")
            ->and($script->childAt(1))->toBeInstanceOf(EchoNode::class)
            ->and($script->childAt(1)->asEcho()->isEscaped())->toBeTrue()
            ->and($script->childAt(1)->asEcho()->content())->toBe(' Js::from($array) ')
            ->and($script->childAt(2))->toBeInstanceOf(TextNode::class)
            ->and($script->childAt(2)->asText()->getDocumentContent())->toBe(";\n")
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template seven', function (): void {
        $template = <<<'EOT'
@verbatim
<div class="container">
    Hello, {{ name }}.
</div>
@endverbatim
EOT;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $innerContent = <<<'INNER'

<div class="container">
    Hello, {{ name }}.
</div>

INNER;

        expect($nodes)->toHaveCount(1)
            ->and($nodes[0])->toBeInstanceOf(VerbatimNode::class)
            ->and($nodes[0]->asVerbatim()->content())->toBe($innerContent)
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template eight', function (): void {
        $template = <<<'EOT'
@if (count($records) === 1)
I have one record!
@elseif (count($records) > 1)
I have multiple records!
@else
I don't have any records!
@endif
EOT;
        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $blockDirective = $nodes[0]->asDirectiveBlock();
        expect($nodes)->toHaveCount(1)
            ->and($blockDirective)->toBeInstanceOf(DirectiveBlockNode::class);

        $ifDirective = $blockDirective->childAt(0)->asDirective();
        $elseifDirective = $blockDirective->childAt(1)->asDirective();
        $elseDirective = $blockDirective->childAt(2)->asDirective();
        $endifDirective = $blockDirective->childAt(3)->asDirective();

        expect($blockDirective->getChildren())->toHaveCount(4)
            ->and($ifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($ifDirective->nameText())->toBe('if')
            ->and($ifDirective->arguments())->toBe('(count($records) === 1)')
            ->and($ifDirective->getChildren())->toHaveCount(1)
            ->and($ifDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $ifDirective->firstChild()->asText()->getDocumentContent()))->toBe('I have one record!')

            ->and($elseifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseifDirective->nameText())->toBe('elseif')
            ->and($elseifDirective->arguments())->toBe('(count($records) > 1)')
            ->and($elseifDirective->getChildren())->toHaveCount(1)
            ->and($elseifDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $elseifDirective->firstChild()->asText()->getDocumentContent()))->toBe('I have multiple records!')

            ->and($elseDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($elseDirective->nameText())->toBe('else')
            ->and($elseDirective->arguments())->toBeNull()
            ->and($elseDirective->getChildren())->toHaveCount(1)
            ->and($elseDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $elseDirective->firstChild()->asText()->getDocumentContent()))->toBe("I don't have any records!")

            ->and($endifDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endifDirective->nameText())->toBe('endif')
            ->and($endifDirective->arguments())->toBeNull()
            ->and($endifDirective->getChildren())->toHaveCount(0)
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template nine', function (): void {
        $template = <<<'EOT'
@unless (Auth::check())
You are not signed in.
@endunless
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $blockDirective = $nodes[0]->asDirectiveBlock();
        expect($nodes)->toHaveCount(1)
            ->and($blockDirective)->toBeInstanceOf(DirectiveBlockNode::class);

        $unlessDirective = $blockDirective->childAt(0)->asDirective();
        $endunlessDirective = $blockDirective->childAt(1)->asDirective();

        expect($blockDirective->getChildren())->toHaveCount(2)
            ->and($unlessDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($unlessDirective->nameText())->toBe('unless')
            ->and($unlessDirective->arguments())->toBe('(Auth::check())')
            ->and($unlessDirective->getChildren())->toHaveCount(1)
            ->and($unlessDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $unlessDirective->firstChild()->asText()->getDocumentContent()))->toBe('You are not signed in.')

            ->and($endunlessDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endunlessDirective->nameText())->toBe('endunless')
            ->and($endunlessDirective->arguments())->toBeNull()
            ->and($endunlessDirective->getChildren())->toHaveCount(0)
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template ten', function (): void {
        $template = <<<'EOT'
@isset($records)
// $records is defined and is not null...
@endisset
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $blockDirective = $nodes[0]->asDirectiveBlock();
        expect($nodes)->toHaveCount(1)
            ->and($blockDirective)->toBeInstanceOf(DirectiveBlockNode::class);

        $issetDirective = $blockDirective->childAt(0)->asDirective();
        $endissetDirective = $blockDirective->childAt(1)->asDirective();

        expect($blockDirective->getChildren())->toHaveCount(2)

            ->and($issetDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($issetDirective->nameText())->toBe('isset')
            ->and($issetDirective->arguments())->toBe('($records)')
            ->and($issetDirective->getChildren())->toHaveCount(1)
            ->and($issetDirective->firstChild())->toBeInstanceOf(TextNode::class)
            ->and(trim((string) $issetDirective->firstChild()->asText()->getDocumentContent()))->toBe('// $records is defined and is not null...')

            ->and($endissetDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endissetDirective->nameText())->toBe('endisset')
            ->and($endissetDirective->arguments())->toBeNull()
            ->and($endissetDirective->getChildren())->toHaveCount(0)
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template eleven', function (): void {
        $template = '{{ $name }}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $echo = $nodes[0]->asEcho();
        expect($nodes)->toHaveCount(1)
            ->and($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->isEscaped())->toBeTrue()
            ->and($echo->content())->toBe(' $name ')
            ->and($doc->render())->toBe($template);
    });

    it('parses simple template twelve', function (): void {
        $template = '{{{ $name }}}';

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $echo = $nodes[0]->asEcho();
        expect($nodes)->toHaveCount(1)
            ->and($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->isTriple())->toBeTrue()
            ->and($echo->content())->toBe(' $name ')
            ->and($doc->render())->toBe($template);
    });

    it('parses echo spanning multiple lines', function (): void {
        $template = <<<'EOT'
{{
         $name
 }}
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $echo = $nodes[0]->asEcho();
        expect($nodes)->toHaveCount(1)
            ->and($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->isEscaped())->toBeTrue()
            ->and($echo->content())->toBe("\n         \$name\n ")
            ->and($doc->render())->toBe($template);
    });

    it('ignores blade-like things inside php block', function (): void {
        $template = <<<'EOT'
@php echo 'I am PHP {{ not Blade }}' @endphp
EOT;

        $doc = $this->parse($template);
        $nodes = $doc->getChildren();

        $phpBlock = $nodes[0]->asPhpBlock();
        expect($nodes)->toHaveCount(1)
            ->and($phpBlock)->toBeInstanceOf(PhpBlockNode::class)
            ->and($phpBlock->code())->toBe("echo 'I am PHP {{ not Blade }}'")
            ->and($doc->render())->toBe($template);
    });
});
