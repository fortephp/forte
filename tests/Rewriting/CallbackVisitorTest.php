<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;

describe('Callback Visitors', function (): void {
    it('removes comments using enter callback', function (): void {
        $doc = $this->parse('<!-- comment --><div>keep</div>');

        $visitor = new CallbackVisitor(
            enter: fn (NodePath $path) => $path->isComment() && $path->remove()
        );

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>keep</div>');
    });

    it('creates visitor with static onEnter method', function (): void {
        $doc = $this->parse('<!-- comment --><div>keep</div>');

        $visitor = CallbackVisitor::onEnter(function (NodePath $path): void {
            if ($path->isComment()) {
                $path->remove();
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>keep</div>');
    });

    it('creates visitor with static onLeave method', function (): void {
        $doc = $this->parse('<div>content</div>');
        $leaveCount = 0;

        $visitor = CallbackVisitor::onLeave(function (NodePath $path) use (&$leaveCount): void {
            if ($path->isElement()) {
                $leaveCount++;
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $rewriter->rewrite($doc);

        expect($leaveCount)->toBe(1);
    });

    it('replaces elements using enter callback', function (): void {
        $doc = $this->parse('<div>old</div>');

        $visitor = new CallbackVisitor(
            enter: function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->replaceWith(Builder::element('span')->text('new'));
                }
            }
        );

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<span>new</span>');
    });

    it('adds classes to elements', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = new CallbackVisitor(
            enter: function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->addClass('active');
                }
            }
        );

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div class="active">content</div>');
    });

    it('can use both enter and leave callbacks', function (): void {
        $doc = $this->parse('<div>content</div>');
        $enterCalled = false;
        $leaveCalled = false;

        $visitor = new CallbackVisitor(
            enter: function (NodePath $path) use (&$enterCalled): void {
                if ($path->isTag('div')) {
                    $enterCalled = true;
                }
            },
            leave: function (NodePath $path) use (&$leaveCalled): void {
                if ($path->isTag('div')) {
                    $leaveCalled = true;
                }
            }
        );

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $rewriter->rewrite($doc);

        expect($enterCalled)->toBeTrue()
            ->and($leaveCalled)->toBeTrue();
    });

    it('works with null callbacks', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = new CallbackVisitor; // Both null

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div>content</div>');
    });

    it('inserts before and after nodes', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = new CallbackVisitor(
            enter: function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->insertBefore(Builder::comment('before'));
                    $path->insertAfter(Builder::comment('after'));
                }
            }
        );

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);

        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<!-- before --><div>content</div><!-- after -->');
    });
});
