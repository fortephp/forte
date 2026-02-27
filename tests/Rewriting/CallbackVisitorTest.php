<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\CallbackVisitor;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

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

    it('applies addClass in onLeave callback', function (): void {
        $doc = $this->parse('<ul><li>One</li><li>Two</li></ul>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('li')) {
                $path->addClass('item');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<ul><li class="item">One</li><li class="item">Two</li></ul>');
    });

    it('applies setAttribute in onLeave callback', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->setAttribute('data-visited', 'true');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div data-visited="true">content</div>');
    });

    it('applies removeAttribute in onLeave callback', function (): void {
        $doc = $this->parse('<div style="color: red" class="box">content</div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->removeAttribute('style');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())->toBe('<div class="box">content</div>');
    });

    it('applies renameTag in onLeave callback', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->renameTag('section');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<section>content</section>');
    });

    it('applies combined enter and leave modifications', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = new CallbackVisitor(
            enter: function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->addClass('entered');
                }
            },
            leave: function (NodePath $path): void {
                if ($path->isTag('div')) {
                    $path->setAttribute('data-left', 'true');
                }
            }
        );

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div class="entered" data-left="true">content</div>');
    });

    it('supports insertAfter in leave callback', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->insertAfter('<span>after</span>');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div>content</div><span>after</span>');
    });

    it('leave addClass appends to existing class attribute', function (): void {
        $doc = $this->parse('<div class="existing">content</div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->addClass('added');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div class="existing added">content</div>');
    });

    it('leave modification on void element', function (): void {
        $doc = $this->parse('<br>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('br')) {
                $path->setAttribute('class', 'spacer');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<br class="spacer">');
    });

    it('leave modification on self-closing element', function (): void {
        $doc = $this->parse('<img src="a.png" />');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('img')) {
                $path->setAttribute('alt', 'photo');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<img src="a.png" alt="photo" />');
    });

    it('leave modification on deeply nested element', function (): void {
        $doc = $this->parse('<div><section><p><span>deep</span></p></section></div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('span')) {
                $path->addClass('found');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div><section><p><span class="found">deep</span></p></section></div>');
    });

    it('separate visitors can modify in enter and leave respectively', function (): void {
        $doc = $this->parse('<div>content</div>');

        $visitorA = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->addClass('from-enter');
                }
            }
        };

        $visitorB = new class extends Visitor
        {
            public function leave(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->setAttribute('data-from', 'leave');
                }
            }
        };

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitorA);
        $rewriter->addVisitor($visitorB);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div class="from-enter" data-from="leave">content</div>');
    });

    it('leave renameTag and setAttribute combined in same callback', function (): void {
        $doc = $this->parse('<b>bold</b>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('b')) {
                $path->renameTag('strong');
                $path->setAttribute('role', 'text');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<strong role="text">bold</strong>');
    });

    it('leave removeClass removes existing class', function (): void {
        $doc = $this->parse('<div class="remove-me keep-me">content</div>');

        $visitor = CallbackVisitor::onLeave(function (NodePath $path): void {
            if ($path->isTag('div')) {
                $path->removeClass('remove-me');
            }
        });

        $rewriter = new Rewriter;
        $rewriter->addVisitor($visitor);
        $result = $rewriter->rewrite($doc);

        expect($result->render())
            ->toBe('<div class="keep-me">content</div>');
    });
});
