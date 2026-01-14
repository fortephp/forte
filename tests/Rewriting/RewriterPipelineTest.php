<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
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
});
