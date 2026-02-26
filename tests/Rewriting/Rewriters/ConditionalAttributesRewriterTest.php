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
                ->toBe('@if($show)<div>content</div>@endif');
        });

        it('removes #if attribute from element', function (): void {
            $doc = $this->parse('<div #if="$show" class="container">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div class="container">content</div>@endif');
        });

        it('handles complex conditions', function (): void {
            $doc = $this->parse('<div #if="$count > 5 && $active">visible</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($count > 5 && $active)<div>visible</div>@endif');
        });

        it('normalizes conditions with outer parentheses', function (): void {
            $doc = $this->parse('<div #if="($show)">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div>content</div>@endif');
        });

        it('preserves bound attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" :class="$classes">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div :class="$classes">content</div>@endif');
        });

        it('preserves escaped attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" ::class="rawValue">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div ::class="rawValue">content</div>@endif');
        });

        it('preserves shorthand variable attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" :$user>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div :$user>content</div>@endif');
        });

        it('preserves boolean attribute syntax', function (): void {
            $doc = $this->parse('<div #if="$show" disabled>content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div disabled>content</div>@endif');
        });

        it('works with nested elements', function (): void {
            $doc = $this->parse('<div #if="$outer"><span #if="$inner">nested</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($outer)<div>@if($inner)<span>nested</span>@endif</div>@endif');
        });
    });

    describe('#else-if attribute', function (): void {
        it('transforms #if followed by #else-if', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>A</div>@elseif($b)<div>B</div>@endif');
        });

        it('handles multiple #else-if branches', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div><div #else-if="$c">C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>A</div>@elseif($b)<div>B</div>@elseif($c)<div>C</div>@endif');
        });
    });

    describe('#else attribute', function (): void {
        it('transforms #if followed by #else', function (): void {
            $doc = $this->parse('<div #if="$show">visible</div><div #else>hidden</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div>visible</div>@else<div>hidden</div>@endif');
        });

        it('handles full conditional chain', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>A</div>@elseif($b)<div>B</div>@else<div>C</div>@endif');
        });

        it('handles #else with content', function (): void {
            $doc = $this->parse('<span #if="$show">yes</span><span #else>no</span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<span>yes</span>@else<span>no</span>@endif');
        });
    });

    describe('whitespace handling', function (): void {
        it('handles whitespace between #if and #else', function (): void {
            $doc = $this->parse("<div #if=\"\$show\">visible</div>\n<div #else>hidden</div>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("@if(\$show)<div>visible</div>\n@else<div>hidden</div>@endif");
        });

        it('handles whitespace in full chain', function (): void {
            $doc = $this->parse("<div #if=\"\$a\">A</div>\n<div #else-if=\"\$b\">B</div>\n<div #else>C</div>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("@if(\$a)<div>A</div>\n@elseif(\$b)<div>B</div>\n@else<div>C</div>@endif");
        });
    });

    describe('custom prefix', function (): void {
        it('uses custom prefix', function (): void {
            $doc = $this->parse('<div v-if="$show">content</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter('v-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($show)<div>content</div>@endif');
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
                ->toBe('<ul><li>@if($active)<span>active</span>@endif</li></ul>');
        });

        it('handles sibling conditionals independently', function (): void {
            $doc = $this->parse('<div #if="$a">A</div><p>separator</p><div #if="$b">B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>A</div>@endif<p>separator</p>@if($b)<div>B</div>@endif');
        });
    });

    describe('nested conditional chains', function (): void {
        it('handles nested #if with full conditional chain inside', function (): void {
            $doc = $this->parse('<div #if="$outer"><div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($outer)<div>@if($a)<div>A</div>@elseif($b)<div>B</div>@else<div>C</div>@endif</div>@endif');
        });

        it('handles outer conditional chain with nested chain inside first branch', function (): void {
            $doc = $this->parse('<div #if="$a"><div #if="$a">A</div><div #else-if="$b">B</div><div #else>C</div></div><div #else-if="$b">B</div><div #else>C</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>@if($a)<div>A</div>@elseif($b)<div>B</div>@else<div>C</div>@endif</div>@elseif($b)<div>B</div>@else<div>C</div>@endif');
        });

        it('handles deeply nested conditionals', function (): void {
            $doc = $this->parse('<div #if="$a"><div #if="$b"><span #if="$c">deep</span></div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>@if($b)<div>@if($c)<span>deep</span>@endif</div>@endif</div>@endif');
        });

        it('handles nested conditionals with multiple branches at each level', function (): void {
            $doc = $this->parse('<div #if="$a"><span #if="$x">X</span><span #else>Y</span></div><div #else><span #if="$y">Y</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($a)<div>@if($x)<span>X</span>@else<span>Y</span>@endif</div>@else<div>@if($y)<span>Y</span>@endif</div>@endif');
        });

        it('handles conditional inside each branch of outer conditional', function (): void {
            $doc = $this->parse('<div #if="$outer"><p #if="$a">A</p></div><div #else-if="$middle"><p #if="$b">B</p></div><div #else><p #if="$c">C</p></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@if($outer)<div>@if($a)<p>A</p>@endif</div>@elseif($middle)<div>@if($b)<p>B</p>@endif</div>@else<div>@if($c)<p>C</p>@endif</div>@endif');
        });

        it('handles sibling nested conditionals', function (): void {
            $doc = $this->parse('<div><span #if="$a">A</span><span #else>B</span></div><div><span #if="$c">C</span><span #else>D</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ConditionalAttributesRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<div>@if($a)<span>A</span>@else<span>B</span>@endif</div><div>@if($c)<span>C</span>@else<span>D</span>@endif</div>');
        });
    });
});
