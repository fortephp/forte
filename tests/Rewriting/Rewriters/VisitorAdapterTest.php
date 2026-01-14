<?php

declare(strict_types=1);

use Forte\Rewriting\Builders\Builder;
use Forte\Rewriting\NodePath;
use Forte\Rewriting\Rewriter;
use Forte\Rewriting\Visitor;

describe('Traversal Order', function (): void {

    describe('Synthetic Nodes Are Not Visited', function (): void {
        test('inserted nodes are not visited in same pass', function (): void {
            $doc = $this->parse('<div>original</div>');
            $visitedTags = [];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visitedTags) extends Visitor
            {
                public function __construct(private array &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->visited[] = $path->asElement()->tagNameText();
                    }
                    if ($path->isTag('div')) {
                        $path->insertAfter(Builder::element('span')->text('inserted'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($visitedTags)->toBe(['div'])
                ->and($result->render())->toBe('<div>original</div><span>inserted</span>');
        });

        test('replacement nodes are not visited in same pass', function (): void {
            $doc = $this->parse('<div>original</div>');
            $visitedTags = [];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($visitedTags) extends Visitor
            {
                public function __construct(private array &$visited) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->visited[] = $path->asElement()->tagNameText();
                    }
                    if ($path->isTag('div')) {
                        $path->replaceWith(Builder::element('span')->text('replaced'));
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($visitedTags)->toBe(['div'])
                ->and($result->render())->toBe('<span>replaced</span>');
        });
    });

    describe('Enter vs Leave Ordering', function (): void {
        test('calls enter before leave', function (): void {
            $doc = $this->parse('<div>text</div>');
            $calls = [];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($calls) extends Visitor
            {
                public function __construct(private array &$calls) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $this->calls[] = 'enter';
                    }
                }

                public function leave(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $this->calls[] = 'leave';
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($calls)->toBe(['enter', 'leave']);
        });

        test('enter is called on parent before child, leave in reverse', function (): void {
            $doc = $this->parse('<div><span>text</span></div>');
            $calls = [];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($calls) extends Visitor
            {
                public function __construct(private array &$calls) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->calls[] = 'enter:'.$path->asElement()->tagNameText();
                    }
                }

                public function leave(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->calls[] = 'leave:'.$path->asElement()->tagNameText();
                    }
                }
            });

            $rewriter->rewrite($doc);

            expect($calls)
                ->toBe(['enter:div', 'enter:span', 'leave:span', 'leave:div']);
        });

        test('insertAfter in leave works correctly', function (): void {
            $doc = $this->parse('<div>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class extends Visitor
            {
                public function leave(NodePath $path): void
                {
                    if ($path->isTag('div')) {
                        $path->insertAfter('<!-- added in leave -->');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<div>content</div><!-- added in leave -->');
        });

        test('operations in leave are processed after children', function (): void {
            $doc = $this->parse('<div><span>child</span></div>');
            $order = [];

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new class($order) extends Visitor
            {
                public function __construct(private array &$order) {}

                public function enter(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->order[] = 'enter:'.$path->asElement()->tagNameText();
                    }
                }

                public function leave(NodePath $path): void
                {
                    if ($path->isElement()) {
                        $this->order[] = 'leave:'.$path->asElement()->tagNameText();
                    }
                    if ($path->isTag('div')) {
                        $path->insertAfter('<!-- after div -->');
                    }
                }
            });

            $result = $rewriter->rewrite($doc);

            expect($order)->toBe(['enter:div', 'enter:span', 'leave:span', 'leave:div'])
                ->and($result->render())->toBe('<div><span>child</span></div><!-- after div -->');
        });
    });

    test('last visitor wins when both call replaceWith', function (): void {
        $doc = $this->parse('<div>original</div>');

        $v1 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceWith('<span>first</span>');
                }
            }
        };

        $v2 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceWith('<p>second</p>');
                }
            }
        };

        $rewriter = new Rewriter;
        $rewriter->addVisitor($v1)->addVisitor($v2);

        $result = $rewriter->rewrite($doc);
        expect($result->render())->toBe('<p>second</p>');
    });

    test('both insertBefore operations are merged', function (): void {
        $doc = $this->parse('<div>content</div>');

        $v1 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertBefore('<!-- first -->');
                }
            }
        };

        $v2 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertBefore('<!-- second -->');
                }
            }
        };

        $rewriter = new Rewriter;
        $rewriter->addVisitor($v1)->addVisitor($v2);

        $result = $rewriter->rewrite($doc);
        expect($result->render())->toBe('<!-- first --><!-- second --><div>content</div>');
    });

    test('both insertAfter operations are merged', function (): void {
        $doc = $this->parse('<div>content</div>');

        $v1 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertAfter('<!-- first -->');
                }
            }
        };

        $v2 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertAfter('<!-- second -->');
                }
            }
        };

        $rewriter = new Rewriter;
        $rewriter->addVisitor($v1)->addVisitor($v2);

        $result = $rewriter->rewrite($doc);
        expect($result->render())->toBe('<div>content</div><!-- first --><!-- second -->');
    });

    test('insertBefore is preserved when replaceWith is called later', function (): void {
        $doc = $this->parse('<div>content</div>');

        $v1 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->insertBefore('<!-- before -->');
                    $path->insertAfter('<!-- after -->');
                }
            }
        };

        $v2 = new class extends Visitor
        {
            public function enter(NodePath $path): void
            {
                if ($path->isTag('div')) {
                    $path->replaceWith('<span>replaced</span>');
                }
            }
        };

        $rewriter = new Rewriter;
        $rewriter->addVisitor($v1)->addVisitor($v2);

        $result = $rewriter->rewrite($doc);
        expect($result->render())
            ->toBe('<!-- before --><span>replaced</span><!-- after -->');
    });
});
