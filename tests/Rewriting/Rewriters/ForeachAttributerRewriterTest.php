<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\ForeachAttributeRewriter;
use Forte\Rewriting\Rewriter;

describe('Foreach Attribute Rewriter', function (): void {
    describe('basic #foreach', function (): void {
        it('transforms simple #foreach', function (): void {
            $doc = $this->parse('<div #foreach="$users as $user">{{ $user->name }}</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($users as $user)<div>{{ $user->name }}</div>@endforeach');
        });

        it('removes #foreach attribute from element', function (): void {
            $doc = $this->parse('<li #foreach="$items as $item" class="item">{{ $item }}</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($items as $item)<li class="item">{{ $item }}</li>@endforeach');
        });

        it('handles key => value syntax', function (): void {
            $doc = $this->parse('<option #foreach="$options as $key => $value">{{ $value }}</option>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($options as $key => $value)<option>{{ $value }}</option>@endforeach');
        });

        it('handles method call as collection', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($users->active() as $user)<tr><td>{{ $user->name }}</td></tr>@endforeach');
        });
    });

    describe('nested foreach', function (): void {
        it('handles nested #foreach', function (): void {
            $doc = $this->parse('<ul #foreach="$categories as $cat"><li #foreach="$cat->items as $item">{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($categories as $cat)<ul>@foreach($cat->items as $item)<li>{{ $item }}</li>@endforeach</ul>@endforeach');
        });

        it('handles deeply nested #foreach', function (): void {
            $doc = $this->parse('<div #foreach="$a as $x"><div #foreach="$b as $y"><div #foreach="$c as $z">{{ $z }}</div></div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($a as $x)<div>@foreach($b as $y)<div>@foreach($c as $z)<div>{{ $z }}</div>@endforeach</div>@endforeach</div>@endforeach');
        });
    });

    describe('with other content', function (): void {
        it('preserves siblings', function (): void {
            $doc = $this->parse('<h1>Title</h1><p #foreach="$items as $item">{{ $item }}</p><footer>End</footer>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<h1>Title</h1>@foreach($items as $item)<p>{{ $item }}</p>@endforeach<footer>End</footer>');
        });

        it('works inside other elements', function (): void {
            $doc = $this->parse('<table><tbody><tr #foreach="$rows as $row"><td>{{ $row }}</td></tr></tbody></table>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<table><tbody>@foreach($rows as $row)<tr><td>{{ $row }}</td></tr>@endforeach</tbody></table>');
        });
    });

    describe('custom prefix', function (): void {
        it('uses custom prefix', function (): void {
            $doc = $this->parse('<div v-for="$users as $user">{{ $user }}</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter('v-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<div v-for="$users as $user">{{ $user }}</div>');
        });

        it('supports x- prefix for Alpine style', function (): void {
            $doc = $this->parse('<div x-foreach="$users as $user">{{ $user }}</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter('x-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($users as $user)<div>{{ $user }}</div>@endforeach');
        });
    });

    describe('edge cases', function (): void {
        it('handles empty element', function (): void {
            $doc = $this->parse('<div #foreach="$items as $item"></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($items as $item)<div></div>@endforeach');
        });

        it('handles self-closing element', function (): void {
            $doc = $this->parse('<input #foreach="$fields as $field" type="text" />');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($fields as $field)<input type="text" />@endforeach');
        });

        it('preserves complex inner content', function (): void {
            $doc = $this->parse('<div #foreach="$users as $user"><span>{{ $user->name }}</span><small>{{ $user->email }}</small></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($users as $user)<div><span>{{ $user->name }}</span><small>{{ $user->email }}</small></div>@endforeach');
        });
    });

    describe('nested loops', function (): void {
        it('handles nested #foreach loops', function (): void {
            $doc = $this->parse('<div #foreach="$groups as $group"><span #foreach="$group->items as $item">{{ $item }}</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($groups as $group)<div>@foreach($group->items as $item)<span>{{ $item }}</span>@endforeach</div>@endforeach');
        });

        it('handles three levels of nesting', function (): void {
            $doc = $this->parse('<div #foreach="$a as $b"><div #foreach="$b as $c"><div #foreach="$c as $d">{{ $d }}</div></div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($a as $b)<div>@foreach($b as $c)<div>@foreach($c as $d)<div>{{ $d }}</div>@endforeach</div>@endforeach</div>@endforeach');
        });

        it('handles sibling foreach loops', function (): void {
            $doc = $this->parse('<ul><li #foreach="$a as $item">{{ $item }}</li></ul><ul><li #foreach="$b as $item">{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul>@foreach($a as $item)<li>{{ $item }}</li>@endforeach</ul><ul>@foreach($b as $item)<li>{{ $item }}</li>@endforeach</ul>');
        });

        it('handles nested foreach with conditionals', function (): void {
            $doc = $this->parse('<div #foreach="$users as $user"><span>{{ $user->name }}</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@foreach($users as $user)<div><span>{{ $user->name }}</span></div>@endforeach');
        });
    });
});
