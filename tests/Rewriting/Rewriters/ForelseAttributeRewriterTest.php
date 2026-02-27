<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\ForelseAttributeRewriter;
use Forte\Rewriting\Rewriter;

function forelseOpen(string $variable, string $alias, int $counter = 1): string
{
    $emptyVar = '$__empty_'.$counter;

    return "<?php {$emptyVar} = true; \$__currentLoopData = {$variable}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$alias}): {$emptyVar} = false; \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ?>";
}

function forelseEmpty(int $counter = 1): string
{
    $emptyVar = '$__empty_'.$counter;

    return "<?php endforeach; \$__env->popLoop(); \$loop = \$__env->getLastLoop(); if ({$emptyVar}): ?>";
}

function forelseEndforelse(): string
{
    return '<?php endif; ?>';
}

function forelseStandaloneOpen(string $variable, string $alias): string
{
    return "<?php \$__currentLoopData = {$variable}; \$__env->addLoop(\$__currentLoopData); foreach(\$__currentLoopData as {$alias}): \$__env->incrementLoopIndices(); \$loop = \$__env->getLastLoop(); ?>";
}

function forelseStandaloneClose(): string
{
    return '<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
}

describe('Forelse Attribute Rewriter', function (): void {
    describe('basic #forelse with #empty', function (): void {
        it('transforms #forelse followed by #empty', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user->name }}</li><p #empty>No users</p>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<li>{{ $user->name }}</li>'.forelseEmpty().'<p>No users</p>'.forelseEndforelse());
        });

        it('removes attributes from elements', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item" class="item">{{ $item }}</li><div #empty class="empty">Empty</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$items', '$item').'<li class="item">{{ $item }}</li>'.forelseEmpty().'<div class="empty">Empty</div>'.forelseEndforelse());
        });

        it('handles key => value syntax', function (): void {
            $doc = $this->parse('<option #forelse="$options as $key => $value">{{ $value }}</option><option #empty>No options</option>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$options', '$key => $value').'<option>{{ $value }}</option>'.forelseEmpty().'<option>No options</option>'.forelseEndforelse());
        });
    });

    describe('attribute preservation', function (): void {
        it('preserves bound attribute on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user" :class="$class">{{ $user }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<li :class="$class">{{ $user }}</li>'.forelseEmpty().'<li>None</li>'.forelseEndforelse());
        });

        it('preserves escaped attribute on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user" ::class="rawValue">{{ $user }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<li ::class="rawValue">{{ $user }}</li>'.forelseEmpty().'<li>None</li>'.forelseEndforelse());
        });

        it('preserves shorthand variable attribute on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user" :$user>{{ $user }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<li :$user>{{ $user }}</li>'.forelseEmpty().'<li>None</li>'.forelseEndforelse());
        });

        it('preserves bound attribute on empty element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user }}</li><div #empty :class="$emptyClass">None</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<li>{{ $user }}</li>'.forelseEmpty().'<div :class="$emptyClass">None</div>'.forelseEndforelse());
        });
    });

    describe('#forelse without #empty', function (): void {
        it('transforms standalone #forelse', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user->name }}</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseStandaloneOpen('$users', '$user').'<li>{{ $user->name }}</li>'.forelseStandaloneClose());
        });

        it('handles forelse when next sibling is not #empty', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user }}</li><li>Footer</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseStandaloneOpen('$users', '$user').'<li>{{ $user }}</li>'.forelseStandaloneClose().'<li>Footer</li>');
        });
    });

    describe('whitespace handling', function (): void {
        it('handles whitespace between #forelse and #empty', function (): void {
            $doc = $this->parse("<li #forelse=\"\$users as \$user\">{{ \$user }}</li>\n<p #empty>No users</p>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user')."<li>{{ \$user }}</li>\n".forelseEmpty().'<p>No users</p>'.forelseEndforelse());
        });

        it('handles multiple whitespace nodes', function (): void {
            $doc = $this->parse("<li #forelse=\"\$items as \$item\">{{ \$item }}</li>\n\n\n<p #empty>Empty</p>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$items', '$item')."<li>{{ \$item }}</li>\n\n\n".forelseEmpty().'<p>Empty</p>'.forelseEndforelse());
        });
    });

    describe('nested scenarios', function (): void {
        it('handles #forelse inside container', function (): void {
            $doc = $this->parse('<ul><li #forelse="$items as $item">{{ $item }}</li><li #empty>Empty</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul>'.forelseOpen('$items', '$item').'<li>{{ $item }}</li>'.forelseEmpty().'<li>Empty</li>'.forelseEndforelse().'</ul>');
        });

        it('handles nested #forelse', function (): void {
            $doc = $this->parse('<div #forelse="$groups as $group"><span #forelse="$group->items as $item">{{ $item }}</span><span #empty>No items</span></div><div #empty>No groups</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$groups', '$group').'<div>'.forelseOpen('$group->items', '$item', 2).'<span>{{ $item }}</span>'.forelseEmpty(2).'<span>No items</span>'.forelseEndforelse().'</div>'.forelseEmpty(1).'<div>No groups</div>'.forelseEndforelse());
        });
    });

    describe('custom prefix', function (): void {
        it('uses custom prefix', function (): void {
            $doc = $this->parse('<li x-forelse="$users as $user">{{ $user }}</li><p x-empty>No users</p>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter('x-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<li>{{ $user }}</li>'.forelseEmpty().'<p>No users</p>'.forelseEndforelse());
        });
    });

    describe('complex expressions', function (): void {
        it('handles method chain as collection', function (): void {
            $doc = $this->parse('<li #forelse="$users->active()->sortBy(\'name\') as $user">{{ $user->name }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen("\$users->active()->sortBy('name')", '$user').'<li>{{ $user->name }}</li>'.forelseEmpty().'<li>None</li>'.forelseEndforelse());
        });

        it('handles array syntax', function (): void {
            $doc = $this->parse('<li #forelse="[1, 2, 3] as $num">{{ $num }}</li><li #empty>No numbers</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('[1, 2, 3]', '$num').'<li>{{ $num }}</li>'.forelseEmpty().'<li>No numbers</li>'.forelseEndforelse());
        });
    });

    describe('edge cases', function (): void {
        it('handles empty #empty element', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item">{{ $item }}</li><span #empty></span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$items', '$item').'<li>{{ $item }}</li>'.forelseEmpty().'<span></span>'.forelseEndforelse());
        });

        it('handles complex inner content in both branches', function (): void {
            $doc = $this->parse('<div #forelse="$users as $user"><h3>{{ $user->name }}</h3><p>{{ $user->bio }}</p></div><div #empty><h3>No Users</h3><p>Please add some users.</p></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$users', '$user').'<div><h3>{{ $user->name }}</h3><p>{{ $user->bio }}</p></div>'.forelseEmpty().'<div><h3>No Users</h3><p>Please add some users.</p></div>'.forelseEndforelse());
        });
    });

    describe('nested forelse loops', function (): void {
        it('handles nested #forelse inside another #forelse', function (): void {
            $doc = $this->parse('<div #forelse="$groups as $group"><span #forelse="$group->items as $item">{{ $item }}</span><span #empty>No items</span></div><div #empty>No groups</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$groups', '$group').'<div>'.forelseOpen('$group->items', '$item', 2).'<span>{{ $item }}</span>'.forelseEmpty(2).'<span>No items</span>'.forelseEndforelse().'</div>'.forelseEmpty(1).'<div>No groups</div>'.forelseEndforelse());
        });

        it('handles three levels of nested forelse', function (): void {
            $doc = $this->parse('<div #forelse="$a as $b"><div #forelse="$b as $c"><div #forelse="$c as $d">{{ $d }}</div><div #empty>No D</div></div><div #empty>No C</div></div><div #empty>No B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$a', '$b').'<div>'.forelseOpen('$b', '$c', 2).'<div>'.forelseOpen('$c', '$d', 3).'<div>{{ $d }}</div>'.forelseEmpty(3).'<div>No D</div>'.forelseEndforelse().'</div>'.forelseEmpty(2).'<div>No C</div>'.forelseEndforelse().'</div>'.forelseEmpty(1).'<div>No B</div>'.forelseEndforelse());
        });

        it('handles sibling forelse loops', function (): void {
            $doc = $this->parse('<ul><li #forelse="$a as $item">{{ $item }}</li><li #empty>Empty A</li></ul><ul><li #forelse="$b as $item">{{ $item }}</li><li #empty>Empty B</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul>'.forelseOpen('$a', '$item').'<li>{{ $item }}</li>'.forelseEmpty(1).'<li>Empty A</li>'.forelseEndforelse().'</ul><ul>'.forelseOpen('$b', '$item', 2).'<li>{{ $item }}</li>'.forelseEmpty(2).'<li>Empty B</li>'.forelseEndforelse().'</ul>');
        });

        it('handles forelse without empty nested inside another forelse', function (): void {
            $doc = $this->parse('<div #forelse="$groups as $group"><span #forelse="$group->items as $item">{{ $item }}</span></div><div #empty>No groups</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$groups', '$group').'<div>'.forelseStandaloneOpen('$group->items', '$item').'<span>{{ $item }}</span>'.forelseStandaloneClose().'</div>'.forelseEmpty(1).'<div>No groups</div>'.forelseEndforelse());
        });
    });

    describe('duplicate stripped name preservation', function (): void {
        it('preserves both static and bound class on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item" class="a" :class="$b">{{ $item }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe(forelseOpen('$items', '$item').'<li class="a" :class="$b">{{ $item }}</li>'.forelseEmpty().'<li>None</li>'.forelseEndforelse());
        });

        it('preserves bound attribute on void forelse element', function (): void {
            $doc = $this->parse('<input #forelse="$fields as $field" :value="$field" type="text"><span #empty>No fields</span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('$__empty_')
                ->and($result->render())->toContain(':value="$field"')
                ->and($result->render())->toContain('type="text"');
        });
    });
});
