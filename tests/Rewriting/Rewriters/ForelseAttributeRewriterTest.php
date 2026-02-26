<?php

declare(strict_types=1);

use Forte\Enclaves\Rewriters\ForelseAttributeRewriter;
use Forte\Rewriting\Rewriter;

describe('Forelse Attribute Rewriter', function (): void {
    describe('basic #forelse with #empty', function (): void {
        it('transforms #forelse followed by #empty', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user->name }}</li><p #empty>No users</p>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li>{{ $user->name }}</li>@empty<p>No users</p>@endforelse');
        });

        it('removes attributes from elements', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item" class="item">{{ $item }}</li><div #empty class="empty">Empty</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($items as $item)<li class="item">{{ $item }}</li>@empty<div class="empty">Empty</div>@endforelse');
        });

        it('handles key => value syntax', function (): void {
            $doc = $this->parse('<option #forelse="$options as $key => $value">{{ $value }}</option><option #empty>No options</option>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($options as $key => $value)<option>{{ $value }}</option>@empty<option>No options</option>@endforelse');
        });
    });

    describe('attribute preservation', function (): void {
        it('preserves bound attribute on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user" :class="$class">{{ $user }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li :class="$class">{{ $user }}</li>@empty<li>None</li>@endforelse');
        });

        it('preserves escaped attribute on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user" ::class="rawValue">{{ $user }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li ::class="rawValue">{{ $user }}</li>@empty<li>None</li>@endforelse');
        });

        it('preserves shorthand variable attribute on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user" :$user>{{ $user }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li :$user>{{ $user }}</li>@empty<li>None</li>@endforelse');
        });

        it('preserves bound attribute on empty element', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user }}</li><div #empty :class="$emptyClass">None</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li>{{ $user }}</li>@empty<div :class="$emptyClass">None</div>@endforelse');
        });
    });

    describe('#forelse without #empty', function (): void {
        it('transforms standalone #forelse', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user->name }}</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li>{{ $user->name }}</li>@endforelse');
        });

        it('handles forelse when next sibling is not #empty', function (): void {
            $doc = $this->parse('<li #forelse="$users as $user">{{ $user }}</li><li>Footer</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li>{{ $user }}</li>@endforelse<li>Footer</li>');
        });
    });

    describe('whitespace handling', function (): void {
        it('handles whitespace between #forelse and #empty', function (): void {
            $doc = $this->parse("<li #forelse=\"\$users as \$user\">{{ \$user }}</li>\n<p #empty>No users</p>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("@forelse(\$users as \$user)<li>{{ \$user }}</li>\n@empty<p>No users</p>@endforelse");
        });

        it('handles multiple whitespace nodes', function (): void {
            $doc = $this->parse("<li #forelse=\"\$items as \$item\">{{ \$item }}</li>\n\n\n<p #empty>Empty</p>");

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("@forelse(\$items as \$item)<li>{{ \$item }}</li>\n\n\n@empty<p>Empty</p>@endforelse");
        });
    });

    describe('nested scenarios', function (): void {
        it('handles #forelse inside container', function (): void {
            $doc = $this->parse('<ul><li #forelse="$items as $item">{{ $item }}</li><li #empty>Empty</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul>@forelse($items as $item)<li>{{ $item }}</li>@empty<li>Empty</li>@endforelse</ul>');
        });

        it('handles nested #forelse', function (): void {
            $doc = $this->parse('<div #forelse="$groups as $group"><span #forelse="$group->items as $item">{{ $item }}</span><span #empty>No items</span></div><div #empty>No groups</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($groups as $group)<div>@forelse($group->items as $item)<span>{{ $item }}</span>@empty<span>No items</span>@endforelse</div>@empty<div>No groups</div>@endforelse');
        });
    });

    describe('custom prefix', function (): void {
        it('uses custom prefix', function (): void {
            $doc = $this->parse('<li x-forelse="$users as $user">{{ $user }}</li><p x-empty>No users</p>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter('x-'));

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<li>{{ $user }}</li>@empty<p>No users</p>@endforelse');
        });
    });

    describe('complex expressions', function (): void {
        it('handles method chain as collection', function (): void {
            $doc = $this->parse('<li #forelse="$users->active()->sortBy(\'name\') as $user">{{ $user->name }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe("@forelse(\$users->active()->sortBy('name') as \$user)<li>{{ \$user->name }}</li>@empty<li>None</li>@endforelse");
        });

        it('handles array syntax', function (): void {
            $doc = $this->parse('<li #forelse="[1, 2, 3] as $num">{{ $num }}</li><li #empty>No numbers</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse([1, 2, 3] as $num)<li>{{ $num }}</li>@empty<li>No numbers</li>@endforelse');
        });
    });

    describe('edge cases', function (): void {
        it('handles empty #empty element', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item">{{ $item }}</li><span #empty></span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($items as $item)<li>{{ $item }}</li>@empty<span></span>@endforelse');
        });

        it('handles complex inner content in both branches', function (): void {
            $doc = $this->parse('<div #forelse="$users as $user"><h3>{{ $user->name }}</h3><p>{{ $user->bio }}</p></div><div #empty><h3>No Users</h3><p>Please add some users.</p></div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($users as $user)<div><h3>{{ $user->name }}</h3><p>{{ $user->bio }}</p></div>@empty<div><h3>No Users</h3><p>Please add some users.</p></div>@endforelse');
        });
    });

    describe('nested forelse loops', function (): void {
        it('handles nested #forelse inside another #forelse', function (): void {
            $doc = $this->parse('<div #forelse="$groups as $group"><span #forelse="$group->items as $item">{{ $item }}</span><span #empty>No items</span></div><div #empty>No groups</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($groups as $group)<div>@forelse($group->items as $item)<span>{{ $item }}</span>@empty<span>No items</span>@endforelse</div>@empty<div>No groups</div>@endforelse');
        });

        it('handles three levels of nested forelse', function (): void {
            $doc = $this->parse('<div #forelse="$a as $b"><div #forelse="$b as $c"><div #forelse="$c as $d">{{ $d }}</div><div #empty>No D</div></div><div #empty>No C</div></div><div #empty>No B</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($a as $b)<div>@forelse($b as $c)<div>@forelse($c as $d)<div>{{ $d }}</div>@empty<div>No D</div>@endforelse</div>@empty<div>No C</div>@endforelse</div>@empty<div>No B</div>@endforelse');
        });

        it('handles sibling forelse loops', function (): void {
            $doc = $this->parse('<ul><li #forelse="$a as $item">{{ $item }}</li><li #empty>Empty A</li></ul><ul><li #forelse="$b as $item">{{ $item }}</li><li #empty>Empty B</li></ul>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('<ul>@forelse($a as $item)<li>{{ $item }}</li>@empty<li>Empty A</li>@endforelse</ul><ul>@forelse($b as $item)<li>{{ $item }}</li>@empty<li>Empty B</li>@endforelse</ul>');
        });

        it('handles forelse without empty nested inside another forelse', function (): void {
            $doc = $this->parse('<div #forelse="$groups as $group"><span #forelse="$group->items as $item">{{ $item }}</span></div><div #empty>No groups</div>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($groups as $group)<div>@forelse($group->items as $item)<span>{{ $item }}</span>@endforelse</div>@empty<div>No groups</div>@endforelse');
        });
    });

    describe('duplicate stripped name preservation', function (): void {
        it('preserves both static and bound class on forelse element', function (): void {
            $doc = $this->parse('<li #forelse="$items as $item" class="a" :class="$b">{{ $item }}</li><li #empty>None</li>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toBe('@forelse($items as $item)<li class="a" :class="$b">{{ $item }}</li>@empty<li>None</li>@endforelse');
        });

        it('preserves bound attribute on void forelse element', function (): void {
            $doc = $this->parse('<input #forelse="$fields as $field" :value="$field" type="text"><span #empty>No fields</span>');

            $rewriter = new Rewriter;
            $rewriter->addVisitor(new ForelseAttributeRewriter);

            $result = $rewriter->rewrite($doc);

            expect($result->render())
                ->toContain('@forelse($fields as $field)')
                ->and($result->render())->toContain(':value="$field"')
                ->and($result->render())->toContain('type="text"');
        });
    });
});
