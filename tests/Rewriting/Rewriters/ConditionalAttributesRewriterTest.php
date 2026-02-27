<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\ConditionalAttributesRewriter;
use Forte\Rewriting\Rewriter;

describe('Conditional Attributes Rewriter', function (): void {
    describe('#if attribute', function (): void {
        it('transforms simple #if', function (): void {
            $doc = $this->parse('<div #if="$show">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div>content</div><?php endif; ?>');
        });

        it('removes #if attribute from element', function (): void {
            $doc = $this->parse('<div #if="$show" class="container">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div class="container">content</div><?php endif; ?>');
        });

        it('handles complex conditions', function (): void {
            $doc = $this->parse('<div #if="$count > 5 && $active">visible</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($count > 5 && $active): ?><div>visible</div><?php endif; ?>');
        });

        it('normalizes conditions with outer parentheses', function (): void {
            $doc = $this->parse('<div #if="($show)">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div>content</div><?php endif; ?>');
        });

        it('preserves bound attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" :class="$classes">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div :class="$classes">content</div><?php endif; ?>');
        });

        it('preserves escaped attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" ::class="rawValue">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div ::class="rawValue">content</div><?php endif; ?>');
        });

        it('preserves shorthand variable attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" :$user>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div :$user>content</div><?php endif; ?>');
        });

        it('preserves boolean attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" disabled>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div disabled>content</div><?php endif; ?>');
        });

        it('works with nested elements', function (): void {
            $doc = $this->parse('<div #if="$outer"><span #if="$inner">nested</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($outer): ?><div><?php if($inner): ?><span>nested</span><?php endif; ?></div><?php endif; ?>');
        });
    });

    describe('#else-if attribute', function (): void {
        it('transforms #if followed by #else-if', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div>A</div><?php elseif($b): ?><div>B</div><?php endif; ?>');
        });

        it('handles multiple #else-if branches', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div><div #else-if="$c">C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div>A</div><?php elseif($b): ?><div>B</div><?php elseif($c): ?><div>C</div><?php endif; ?>');
        });
    });

    describe('#else attribute', function (): void {
        it('transforms #if followed by #else', function (): void {
            $doc = $this->parse('<div #if="$show">visible</div><div #else>hidden</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div>visible</div><?php else: ?><div>hidden</div><?php endif; ?>');
        });

        it('handles full conditional chain', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div>A</div><?php elseif($b): ?><div>B</div><?php else: ?><div>C</div><?php endif; ?>');
        });

        it('handles #else with content', function (): void {
            $doc = $this->parse('<span #if="$show">yes</span><span #else>no</span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><span>yes</span><?php else: ?><span>no</span><?php endif; ?>');
        });
    });

    describe('whitespace handling', function (): void {
        it('handles whitespace between #if and #else', function (): void {
            $doc = $this->parse("<div #if=\"\$show\">visible</div>\n<div #else>hidden</div>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("<?php if(\$show): ?><div>visible</div>\n<?php else: ?><div>hidden</div><?php endif; ?>");
        });

        it('handles whitespace in full chain', function (): void {
            $doc = $this->parse("<div #if=\"\$a\">A</div>\n<div #else-if=\"\$b\">B</div>\n<div #else>C</div>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("<?php if(\$a): ?><div>A</div>\n<?php elseif(\$b): ?><div>B</div>\n<?php else: ?><div>C</div><?php endif; ?>");
        });
    });

    describe('custom prefix', function (): void {
        it('uses custom prefix', function (): void {
            $doc = $this->parse('<div v-if="$show">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter('v-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div>content</div><?php endif; ?>');
        });

        it('ignores default prefix when custom is set', function (): void {
            $doc = $this->parse('<div #if="$show">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter('v-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<div #if="$show">content</div>');
        });
    });

    describe('complex scenarios', function (): void {
        it('handles conditionals inside a loop', function (): void {
            $doc = $this->parse('<ul><li><span #if="$active">active</span></li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul><li><?php if($active): ?><span>active</span><?php endif; ?></li></ul>');
        });

        it('handles sibling conditionals independently', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><p>separator</p><div #if="$b">B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div>A</div><?php endif; ?><p>separator</p><?php if($b): ?><div>B</div><?php endif; ?>');
        });
    });

    describe('nested conditional chains', function (): void {
        it('handles nested #if with full conditional chain inside', function (): void {
            $doc = $this->parse('<div #if="$outer"><div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($outer): ?><div><?php if($a): ?><div>A</div><?php elseif($b): ?><div>B</div><?php else: ?><div>C</div><?php endif; ?></div><?php endif; ?>');
        });

        it('handles outer conditional chain with nested chain inside first branch', function (): void {
            $doc = $this->parse('<div #if="$a"><div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div></div><div #else-if="$b">B</div><div #else>C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div><?php if($a): ?><div>A</div><?php elseif($b): ?><div>B</div><?php else: ?><div>C</div><?php endif; ?></div><?php elseif($b): ?><div>B</div><?php else: ?><div>C</div><?php endif; ?>');
        });

        it('handles deeply nested conditionals', function (): void {
            $doc = $this->parse('<div #if="$a"><div #if="$b"><span #if="$c">deep</span></div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div><?php if($b): ?><div><?php if($c): ?><span>deep</span><?php endif; ?></div><?php endif; ?></div><?php endif; ?>');
        });

        it('handles nested conditionals with multiple branches at each level', function (): void {
            $doc = $this->parse('<div #if="$a"><span #if="$x">X</span><span #else>Y</span></div><div #else><span #if="$y">Y</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div><?php if($x): ?><span>X</span><?php else: ?><span>Y</span><?php endif; ?></div><?php else: ?><div><?php if($y): ?><span>Y</span><?php endif; ?></div><?php endif; ?>');
        });

        it('handles conditional inside each branch of outer conditional', function (): void {
            $doc = $this->parse('<div #if="$outer"><p #if="$a">A</p></div><div #else-if="$middle"><p #if="$b">B</p></div><div #else><p #if="$c">C</p></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($outer): ?><div><?php if($a): ?><p>A</p><?php endif; ?></div><?php elseif($middle): ?><div><?php if($b): ?><p>B</p><?php endif; ?></div><?php else: ?><div><?php if($c): ?><p>C</p><?php endif; ?></div><?php endif; ?>');
        });

        it('handles sibling nested conditionals', function (): void {
            $doc = $this->parse('<div><span #if="$a">A</span><span #else>B</span></div><div><span #if="$c">C</span><span #else>D</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<div><?php if($a): ?><span>A</span><?php else: ?><span>B</span><?php endif; ?></div><div><?php if($c): ?><span>C</span><?php else: ?><span>D</span><?php endif; ?></div>');
        });
    });

    describe('duplicate stripped name preservation', function (): void {
        it('preserves both static and bound class on #if element', function (): void {
            $doc = $this->parse('<div #if="$show" class="a" :class="$b">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($show): ?><div class="a" :class="$b">content</div><?php endif; ?>');
        });

        it('preserves bound attribute on #else element', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else :class="$cls">B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div>A</div><?php else: ?><div :class="$cls">B</div><?php endif; ?>');
        });

        it('preserves multiple prefix types on #else-if element', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b" class="x" :class="$y" ::style="raw">B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<?php if($a): ?><div>A</div><?php elseif($b): ?><div class="x" :class="$y" ::style="raw">B</div><?php endif; ?>');
        });
    });
});
