<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Passes\Elements\AddClass;
use Forte\Rewriting\Passes\Elements\RemoveAttributes;
use Forte\Rewriting\Passes\Elements\RemoveClass;
use Forte\Rewriting\Passes\Elements\RenameTag;
use Forte\Rewriting\Passes\Elements\SetAttribute;
use Forte\Rewriting\RewritePipeline;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Rewriter Pipelines', function (): void {
    it('applies single rewriter', function (): void {
        $doc = $this->parse('<!-- remove -->keep');

        $rewriter = new Rewriter;
        $rewriter->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isComment()) {
                    $path->remove();
                }
            }
        });

        $pipeline = new RewritePipeline($rewriter);
        $result = $pipeline->rewrite($doc);

        expect($result->render())->toBe('keep');
    });

    it('chains multiple rewriters in order', function (): void {
        $doc = $this->parse('<div>text</div>');

        $step1 = new Rewriter;
        $step1->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceChildren(Builder::element('span')->text('wrapped'));
                }
            }
        });

        $step2 = new Rewriter;
        $step2->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('span')) {
                    $path->replaceWith(Builder::element('span')->attr('class', 'added')->text('wrapped'));
                }
            }
        });

        $pipeline = new RewritePipeline($step1, $step2);
        $result = $pipeline->rewrite($doc);

        expect($result->render())->toBe('<div><span class="added">wrapped</span></div>');
    });

    it('passes output of each step as input to next', function (): void {
        $doc = $this->parse('a');

        $transformer = function (string $char) {
            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($char) extends Visitor
            {
                public function __construct(private readonly string $char) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isText()) {
                        $path->insertAfter(Builder::text($this->char));
                    }
                }
            });

            return $rewriter;
        };

        $pipeline = new RewritePipeline(
            $transformer('b'),
            $transformer('c'),
            $transformer('d')
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())->toBe('adcdbdcd');
    });

    it('can add steps dynamically', function (): void {
        $doc = $this->parse('<!-- 1 --><!-- 2 -->keep');

        $pipeline = new RewritePipeline;

        $remover = new Rewriter;
        $remover->addVisitor(new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isComment()) {
                    $path->remove();
                }
            }
        });

        $pipeline->add($remover);

        $result = $pipeline->rewrite($doc);

        expect($result->render())->toBe('keep');
    });

    it('counts steps', function (): void {
        $step1 = new Rewriter;
        $step2 = new Rewriter;

        $pipeline = new RewritePipeline($step1);
        expect($pipeline->count())->toBe(1);

        $pipeline->add($step2);
        expect($pipeline->count())->toBe(2);
    });

    it('returns unchanged document with empty pipeline', function (): void {
        $doc = $this->parse('<div>unchanged</div>');

        $pipeline = new RewritePipeline;

        $result = $pipeline->rewrite($doc);

        expect($result->render())->toBe('<div>unchanged</div>');
    });

    test('RemoveAttributes works after RenameTag', function (): void {
        $doc = $this->parse('<center style="color: red">content</center>');

        $pipeline = new RewritePipeline(
            new RenameTag('center', 'div'),
            new RemoveAttributes('*', ['style', 'bgcolor']),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div>content</div>');
    });

    test('RemoveAttributes selectively removes attributes after RenameTag', function (): void {
        $doc = $this->parse('<center style="color: red" id="main" bgcolor="#fff">content</center>');

        $pipeline = new RewritePipeline(
            new RenameTag('center', 'div'),
            new RemoveAttributes('*', ['style', 'bgcolor']),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div id="main">content</div>');
    });

    test('RemoveAttributes before RenameTag still works', function (): void {
        $doc = $this->parse('<center style="color: red">content</center>');

        $pipeline = new RewritePipeline(
            new RemoveAttributes('center', ['style', 'bgcolor']),
            new RenameTag('center', 'div'),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div>content</div>');
    });

    test('AddClass after RenameTag', function (): void {
        $doc = $this->parse('<center>content</center>');

        $pipeline = new RewritePipeline(
            new RenameTag('center', 'div'),
            new AddClass('div', 'centered'),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div class="centered">content</div>');
    });

    test('three-step pipeline: RenameTag then SetAttribute then RemoveAttributes', function (): void {
        $doc = $this->parse('<center style="color: red">content</center>');

        $pipeline = new RewritePipeline(
            new RenameTag('center', 'div'),
            new SetAttribute('div', 'role', 'main'),
            new RemoveAttributes('*', ['style']),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div role="main">content</div>');
    });

    test('SetAttribute after RenameTag', function (): void {
        $doc = $this->parse('<b>bold</b>');

        $pipeline = new RewritePipeline(
            new RenameTag('b', 'strong'),
            new SetAttribute('strong', 'class', 'emphasis'),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<strong class="emphasis">bold</strong>');
    });

    test('RemoveClass after RenameTag on element with existing class', function (): void {
        $doc = $this->parse('<span class="old new">text</span>');

        $pipeline = new RewritePipeline(
            new RenameTag('span', 'em'),
            new RemoveClass('em', 'old'),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<em class="new">text</em>');
    });

    test('RemoveAttributes with specific tag pattern after RenameTag', function (): void {
        $doc = $this->parse('<center style="x" id="y">content</center><div style="z">other</div>');

        $pipeline = new RewritePipeline(
            new RenameTag('center', 'div'),
            new RemoveAttributes('div', ['style']),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div id="y">content</div><div>other</div>');
    });

    test('void element through multi-step pipeline', function (): void {
        $doc = $this->parse('<input type="text" style="width:100px">');

        $pipeline = new RewritePipeline(
            new SetAttribute('input', 'class', 'form-control'),
            new RemoveAttributes('input', ['style']),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<input type="text" class="form-control">');
    });

    test('AddClass after RenameTag on element with existing class appends', function (): void {
        $doc = $this->parse('<span class="original">text</span>');

        $pipeline = new RewritePipeline(
            new RenameTag('span', 'div'),
            new AddClass('div', 'added'),
        );

        $result = $pipeline->rewrite($doc);

        expect($result->render())
            ->toBe('<div class="original added">text</div>');
    });
});
