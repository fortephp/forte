<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\ForeachAttributeRewriter;
use Forte\Rewriting\Rewriter;

function foreachOpen(string $variable, string $alias): string
{
    return "<?php \$__currentLoopData = {$variable}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$alias}): \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ?>";
}

function foreachClose(): string
{
    return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
}

describe('Foreach Attribute Rewriter', function (): void {
    describe('basic #foreach', function (): void {
        it('transforms simple #foreach', function (): void {
            $doc = $this->parse('<div #foreach="$users as $user">{{ $user->name }}</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users', '$user').'<div>{{ $user->name }}</div>'.foreachClose());
        });

        it('removes #foreach attribute from element', function (): void {
            $doc = $this->parse('<li #foreach="$items as $item" class="item">{{ $item }}</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$items', '$item').'<li class="item">{{ $item }}</li>'.foreachClose());
        });

        it('handles key => value syntax', function (): void {
            $doc = $this->parse('<option #foreach="$options as $key => $value">{{ $value }}</option>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$options', '$key => $value').'<option>{{ $value }}</option>'.foreachClose());
        });

        it('handles method call as collection', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('preserves other attribute syntax', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" :user="$user"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr :user="$user"><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('does not leak prefixes into other attribute types', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" class="one" :class="two" class="three"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr class="one" :class="two" class="three"><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('preserves shorthand variable attribute syntax', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" :$user><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr :$user><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('preserves boolean attribute syntax', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" attribute><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr attribute><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('preserves static attribute syntax', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" attribute="value"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr attribute="value"><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('preserves escaped attribute syntax', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" ::attribute="value"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr ::attribute="value"><td>{{ $user->name }}</td></tr>'.foreachClose());
        });

        it('preserves triple-colon attribute syntax', function (): void {
            $doc = $this->parse('<tr #foreach="$users->active() as $user" :::attribute="value"><td>{{ $user->name }}</td></tr>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users->active()', '$user').'<tr :::attribute="value"><td>{{ $user->name }}</td></tr>'.foreachClose());
        });
    });

    describe('nested foreach', function (): void {
        it('handles nested #foreach', function (): void {
            $doc = $this->parse('<ul #foreach="$categories as $cat"><li #foreach="$cat->items as $item">{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$categories', '$cat').'<ul>'.foreachOpen('$cat->items', '$item').'<li>{{ $item }}</li>'.foreachClose().'</ul>'.foreachClose());
        });

        it('handles deeply nested #foreach', function (): void {
            $doc = $this->parse('<div #foreach="$a as $x"><div #foreach="$b as $y"><div #foreach="$c as $z">{{ $z }}</div></div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$a', '$x').'<div>'.foreachOpen('$b', '$y').'<div>'.foreachOpen('$c', '$z').'<div>{{ $z }}</div>'.foreachClose().'</div>'.foreachClose().'</div>'.foreachClose());
        });
    });

    describe('with other content', function (): void {
        it('preserves siblings', function (): void {
            $doc = $this->parse('<h1>Title</h1><p #foreach="$items as $item">{{ $item }}</p><footer>End</footer>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<h1>Title</h1>'.foreachOpen('$items', '$item').'<p>{{ $item }}</p>'.foreachClose().'<footer>End</footer>');
        });

        it('works inside other elements', function (): void {
            $doc = $this->parse('<table><tbody><tr #foreach="$rows as $row"><td>{{ $row }}</td></tr></tbody></table>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<table><tbody>'.foreachOpen('$rows', '$row').'<tr><td>{{ $row }}</td></tr>'.foreachClose().'</tbody></table>');
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
                ->toBe(foreachOpen('$users', '$user').'<div>{{ $user }}</div>'.foreachClose());
        });
    });

    describe('edge cases', function (): void {
        it('handles empty element', function (): void {
            $doc = $this->parse('<div #foreach="$items as $item"></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$items', '$item').'<div></div>'.foreachClose());
        });

        it('handles self-closing element', function (): void {
            $doc = $this->parse('<input #foreach="$fields as $field" type="text" />');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$fields', '$field').'<input type="text" />'.foreachClose());
        });

        it('preserves complex inner content', function (): void {
            $doc = $this->parse('<div #foreach="$users as $user"><span>{{ $user->name }}</span><small>{{ $user->email }}</small></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users', '$user').'<div><span>{{ $user->name }}</span><small>{{ $user->email }}</small></div>'.foreachClose());
        });
    });

    describe('nested loops', function (): void {
        it('handles nested #foreach loops', function (): void {
            $doc = $this->parse('<div #foreach="$groups as $group"><span #foreach="$group->items as $item">{{ $item }}</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$groups', '$group').'<div>'.foreachOpen('$group->items', '$item').'<span>{{ $item }}</span>'.foreachClose().'</div>'.foreachClose());
        });

        it('handles three levels of nesting', function (): void {
            $doc = $this->parse('<div #foreach="$a as $b"><div #foreach="$b as $c"><div #foreach="$c as $d">{{ $d }}</div></div></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$a', '$b').'<div>'.foreachOpen('$b', '$c').'<div>'.foreachOpen('$c', '$d').'<div>{{ $d }}</div>'.foreachClose().'</div>'.foreachClose().'</div>'.foreachClose());
        });

        it('handles sibling foreach loops', function (): void {
            $doc = $this->parse('<ul><li #foreach="$a as $item">{{ $item }}</li></ul><ul><li #foreach="$b as $item">{{ $item }}</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul>'.foreachOpen('$a', '$item').'<li>{{ $item }}</li>'.foreachClose().'</ul><ul>'.foreachOpen('$b', '$item').'<li>{{ $item }}</li>'.foreachClose().'</ul>');
        });

        it('handles nested foreach with conditionals', function (): void {
            $doc = $this->parse('<div #foreach="$users as $user"><span>{{ $user->name }}</span></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForeachAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(foreachOpen('$users', '$user').'<div><span>{{ $user->name }}</span></div>'.foreachClose());
        });
    });
});
