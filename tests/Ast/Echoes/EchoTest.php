<?php

declare(strict_types=1);

use Forte\Ast\EchoNode;
use Forte\Ast\TextNode;

describe('Echo Parsing', function (): void {
    it('parses regular echo {{ }}', function (): void {
        $source = '{{ $name }}';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);

        $echo = $children[0]->asEcho();

        expect($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->isEscaped())->toBeTrue()
            ->and($echo->echoType())->toBe('escaped')
            ->and($echo->expression())->toBe('$name')
            ->and($echo->render())->toBe($source);
    });

    it('parses raw echo {!! !!}', function (): void {
        $source = '{!! $html !!}';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->isRaw())->toBeTrue()
            ->and($children[0]->asEcho()->echoType())->toBe('raw')
            ->and($children[0]->asEcho()->expression())->toBe('$html')
            ->and($children[0]->render())->toBe($source);
    });

    it('parses triple echo {{{ }}}', function (): void {
        $source = '{{{ $escaped }}}';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1)
            ->and($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->isTriple())->toBeTrue()
            ->and($children[0]->asEcho()->echoType())->toBe('triple')
            ->and($children[0]->asEcho()->expression())->toBe('$escaped')
            ->and($children[0]->render())->toBe($source);
    });

    it('parses multiple echos in sequence', function (): void {
        $source = '{{ $a }} {!! $b !!} {{{ $c }}}';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(5)
            ->and($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->isEscaped())->toBeTrue()
            ->and($children[1])->toBeInstanceOf(TextNode::class)
            ->and($children[2])->toBeInstanceOf(EchoNode::class)
            ->and($children[2]->asEcho()->isRaw())->toBeTrue()
            ->and($children[3])->toBeInstanceOf(TextNode::class)
            ->and($children[4])->toBeInstanceOf(EchoNode::class)
            ->and($children[4]->asEcho()->isTriple())->toBeTrue()
            ->and($doc->render())->toBe($source);
    });

    it('parses neighboring getEchoes without text', function (): void {
        $source = '{{ $one }}{{ $two }}{{ $three }}';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(EchoNode::class)
            ->and($children[0]->asEcho()->expression())->toBe('$one')
            ->and($children[1])->toBeInstanceOf(EchoNode::class)
            ->and($children[1]->asEcho()->expression())->toBe('$two')
            ->and($children[2])->toBeInstanceOf(EchoNode::class)
            ->and($children[2]->asEcho()->expression())->toBe('$three')
            ->and($doc->render())->toBe($source);
    });

    it('parses echo with text surrounding', function (): void {
        $source = 'The current UNIX timestamp is {{ time() }}.';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('The current UNIX timestamp is ')
            ->and($children[1])->toBeInstanceOf(EchoNode::class)
            ->and($children[1]->asEcho()->expression())->toBe('time()')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[2]->getDocumentContent())->toBe('.')
            ->and($doc->render())->toBe($source);
    });

    it('parses raw echo with text surrounding', function (): void {
        $source = 'Hello, {!! $name !!}.';
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(3)
            ->and($children[0])->toBeInstanceOf(TextNode::class)
            ->and($children[0]->getDocumentContent())->toBe('Hello, ')
            ->and($children[1])->toBeInstanceOf(EchoNode::class)
            ->and($children[1]->asEcho()->isRaw())->toBeTrue()
            ->and($children[1]->asEcho()->expression())->toBe('$name')
            ->and($children[2])->toBeInstanceOf(TextNode::class)
            ->and($children[2]->getDocumentContent())->toBe('.')
            ->and($doc->render())->toBe($source);
    });

    test('parses echo spanning multiple lines', function (): void {
        $source = <<<'EOT'
{{
         $name
 }}
EOT;
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);
        $echo = $children[0]->asEcho();

        expect($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->isEscaped())->toBeTrue()
            ->and($echo->expression())->toBe('$name')
            ->and($echo->render())->toBe($source);
    });

    it('parses echo with complex expression', function (): void {
        $source = "{{ \$attributes->merge(['class' => 'bg-'.\$color.'-200']) }}";
        $doc = $this->parse($source);
        $children = $doc->getChildren();

        expect($children)->toHaveCount(1);
        $echo = $children[0]->asEcho();

        expect($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->isEscaped())->toBeTrue()
            ->and($echo->render())->toBe($source);
    });
});
