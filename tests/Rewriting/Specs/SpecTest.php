<?php

declare(strict_types=1);

use Forte\Parser\NodeKind;
use Forte\Rewriting\Builders\BladeCommentBuilder;
use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\Builders\CommentBuilder;
use Forte\Rewriting\Builders\DirectiveBuilder;
use Forte\Rewriting\Builders\EchoBuilder;
use Forte\Rewriting\Builders\ElementBuilder;
use Forte\Rewriting\Builders\PhpTagBuilder;
use Forte\Rewriting\Builders\RawBuilder;
use Forte\Rewriting\Builders\TextBuilder;

describe('Node Builder Specs', function (): void {
    describe('TextBuilder', function (): void {
        it('renders text content', function (): void {
            $spec = new TextBuilder('Hello World');

            expect($spec->toSource())->toBe('Hello World')
                ->and($spec->kind())->toBe(NodeKind::Text);
        });

        it('is created via factory', function (): void {
            $spec = Builder::text('Hello');
            expect($spec)->toBeInstanceOf(TextBuilder::class)
                ->and($spec->toSource())->toBe('Hello');
        });
    });

    describe('EchoBuilder', function (): void {
        it('renders escaped echo', function (): void {
            $spec = new EchoBuilder('$name', EchoBuilder::TYPE_ESCAPED);
            expect($spec->toSource())->toBe('{{ $name }}')
                ->and($spec->kind())->toBe(NodeKind::Echo);
        });

        it('renders raw echo', function (): void {
            $spec = new EchoBuilder('$html', EchoBuilder::TYPE_RAW);
            expect($spec->toSource())->toBe('{!! $html !!}')
                ->and($spec->kind())->toBe(NodeKind::RawEcho);
        });

        it('renders triple echo', function (): void {
            $spec = new EchoBuilder('$value', EchoBuilder::TYPE_TRIPLE);
            expect($spec->toSource())->toBe('{{{ $value }}}')
                ->and($spec->kind())->toBe(NodeKind::TripleEcho);
        });

        it('is created via factory methods', function (): void {
            expect(Builder::echo('$x')->toSource())->toBe('{{ $x }}')
                ->and(Builder::rawEcho('$x')->toSource())->toBe('{!! $x !!}')
                ->and(Builder::tripleEcho('$x')->toSource())->toBe('{{{ $x }}}');
        });
    });

    describe('DirectiveBuilder', function (): void {
        it('renders directive without arguments', function (): void {
            $spec = new DirectiveBuilder('extends');
            expect($spec->toSource())->toBe('@extends')
                ->and($spec->kind())->toBe(NodeKind::Directive);
        });

        it('renders directive with arguments', function (): void {
            $spec = new DirectiveBuilder('include', '("header")');
            expect($spec->toSource())->toBe('@include("header")');
        });

        it('preserves whitespace before arguments', function (): void {
            $spec = new DirectiveBuilder('if', '($condition)', ' ');
            expect($spec->toSource())->toBe('@if ($condition)');
        });

        it('is created via factory', function (): void {
            $spec = Builder::directive('foreach', '($items as $item)');
            expect($spec)->toBeInstanceOf(DirectiveBuilder::class)
                ->and($spec->toSource())->toBe('@foreach($items as $item)');
        });

        it('normal directive does not need leading separator', function (): void {
            $spec = Builder::directive('if', '($condition)');
            expect($spec->needsLeadingSeparator())->toBeFalse();
        });
    });

    describe('PhpTagBuilder', function (): void {
        it('renders standard PHP tag', function (): void {
            $spec = PhpTagBuilder::php(' $x = 1; ');
            expect($spec->toSource())->toBe('<?php $x = 1; ?>')
                ->and($spec->kind())->toBe(NodeKind::PhpTag);
        });

        it('renders echo PHP tag', function (): void {
            $spec = PhpTagBuilder::echo(' $value ');
            expect($spec->toSource())->toBe('<?= $value ?>');
        });

        it('renders without closing tag', function (): void {
            $spec = PhpTagBuilder::php(' exit; ', false);
            expect($spec->toSource())->toBe('<?php exit; ');
        });

        it('is created via factory', function (): void {
            expect(Builder::phpTag(' $x = 1; ')->toSource())->toBe('<?php $x = 1; ?>')
                ->and(Builder::phpEchoTag(' $value ')->toSource())->toBe('<?= $value ?>');
        });

        it('adds spaces when code has no whitespace', function (): void {
            $spec = PhpTagBuilder::php('$x = 1;');
            expect($spec->toSource())->toBe('<?php $x = 1; ?>');
        });
    });

    describe('CommentBuilder', function (): void {
        it('renders HTML comment', function (): void {
            $spec = new CommentBuilder('This is a comment');
            expect($spec->toSource())->toBe('<!-- This is a comment -->')
                ->and($spec->kind())->toBe(NodeKind::Comment);
        });

        it('is created via factory', function (): void {
            $spec = Builder::comment('test');
            expect($spec)->toBeInstanceOf(CommentBuilder::class);
        });
    });

    describe('BladeCommentBuilder', function (): void {
        it('renders Blade comment', function (): void {
            $spec = new BladeCommentBuilder('Hidden content');
            expect($spec->toSource())->toBe('{{-- Hidden content --}}')
                ->and($spec->kind())->toBe(NodeKind::BladeComment);
        });

        it('is created via factory', function (): void {
            $spec = Builder::bladeComment('test');
            expect($spec)->toBeInstanceOf(BladeCommentBuilder::class);
        });
    });

    describe('ElementBuilder', function (): void {
        it('renders simple element', function (): void {
            $spec = new ElementBuilder('div');
            expect($spec->toSource())->toBe('<div></div>')
                ->and($spec->kind())->toBe(NodeKind::Element);
        });

        it('renders element with attributes', function (): void {
            $spec = (new ElementBuilder('div'))
                ->attr('class', 'container')
                ->attr('id', 'main');
            expect($spec->toSource())->toBe('<div class="container" id="main"></div>');
        });

        it('renders element with boolean attribute', function (): void {
            $spec = (new ElementBuilder('input'))
                ->attr('type', 'checkbox')
                ->boolAttr('checked');
            expect($spec->toSource())->toBe('<input type="checkbox" checked></input>');
        });

        it('renders element with children', function (): void {
            $spec = (new ElementBuilder('div'))
                ->children(new TextBuilder('Hello'));
            expect($spec->toSource())->toBe('<div>Hello</div>');
        });

        it('renders element with text shorthand', function (): void {
            $spec = (new ElementBuilder('p'))->text('Paragraph');
            expect($spec->toSource())->toBe('<p>Paragraph</p>');
        });

        it('renders self-closing element', function (): void {
            $spec = (new ElementBuilder('br'))->selfClosing();
            expect($spec->toSource())->toBe('<br />');
        });

        it('renders void element', function (): void {
            $spec = (new ElementBuilder('br'))->void();
            expect($spec->toSource())->toBe('<br>');
        });

        it('renders nested elements', function (): void {
            $spec = Builder::element('div')
                ->attr('class', 'wrapper')
                ->children(
                    Builder::element('span')->text('inner'),
                );
            expect($spec->toSource())->toBe('<div class="wrapper"><span>inner</span></div>');
        });

        it('escapes attribute values', function (): void {
            $spec = Builder::element('div')->attr('title', 'Say "Hello"');
            expect($spec->toSource())->toBe('<div title="Say "Hello""></div>');
        });

        it('adds class with addClass', function (): void {
            $spec = Builder::element('div')
                ->class('a')
                ->addClass('b')
                ->addClass('c');
            expect($spec->toSource())->toBe('<div class="a b c"></div>');
        });
    });

    describe('RawBuilder', function (): void {
        it('renders raw content', function (): void {
            $spec = new RawBuilder('<custom>content</custom>');
            expect($spec->toSource())->toBe('<custom>content</custom>')
                ->and($spec->kind())->toBe(NodeKind::Text);
        });

        it('is created via factory', function (): void {
            $spec = Builder::raw('@custom directive');
            expect($spec)->toBeInstanceOf(RawBuilder::class);
        });
    });

    describe('Builder::normalize', function (): void {
        it('returns NodeBuilder unchanged', function (): void {
            $original = Builder::text('hello');
            $normalized = Builder::normalize($original);
            expect($normalized)->toBe($original);
        });

        it('wraps string in RawBuilder', function (): void {
            $normalized = Builder::normalize('<div>test</div>');
            expect($normalized)->toBeInstanceOf(RawBuilder::class)
                ->and($normalized->toSource())->toBe('<div>test</div>');
        });
    });
});
