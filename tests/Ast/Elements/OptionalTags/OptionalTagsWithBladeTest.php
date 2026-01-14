<?php

declare(strict_types=1);

use Forte\Ast\DirectiveBlockNode;
use Forte\Ast\DirectiveNode;
use Forte\Ast\EchoNode;
use Forte\Ast\Elements\ElementNode;
use Forte\Ast\TextNode;

describe('HTML5 Optional Tags - Blade Integration', function (): void {

    dataset('blade_parse_render', [
        'li with @foreach' => ["<ul>\n@foreach(\$items as \$item)\n<li>{{ \$item }}\n@endforeach\n</ul>"],
        'li with @if inside' => ["<ul>\n<li>@if(\$condition)Active@endif\n<li>@if(\$other)Other@endif\n</ul>"],
        'nested @foreach with list items' => ["<ul>\n@foreach(\$categories as \$category)\n<li>{{ \$category }}\n<ul>\n@foreach(\$items as \$item)\n<li>{{ \$item }}\n@endforeach\n</ul>\n@endforeach\n</ul>"],
        '@each with list' => ["<ul>\n@each('item', \$items, 'item')\n</ul>"],

        'dt/dd with @foreach' => ["<dl>\n@foreach(\$definitions as \$term => \$def)\n<dt>{{ \$term }}\n<dd>{{ \$def }}\n@endforeach\n</dl>"],
        'dt/dd with @if' => ["<dl>\n@if(\$showFirst)\n<dt>First term\n<dd>First definition\n@endif\n@if(\$showSecond)\n<dt>Second term\n<dd>Second definition\n@endif\n</dl>"],

        '@forelse with list items' => ["<ul>\n@forelse(\$items as \$item)\n<li>{{ \$item }}\n@empty\n<li>No items found\n@endforelse\n</ul>"],
        'nested @if with admin menu' => ["<ul>\n@foreach(\$users as \$user)\n<li>{{ \$user->name }}\n@if(\$user->hasPermission('admin'))\n<ul>\n<li>Admin panel\n<li>User management\n</ul>\n@endif\n@endforeach\n</ul>"],
        '@switch inside list items' => ["<ul>\n@foreach(\$items as \$item)\n<li>@switch(\$item->type)\n@case('a')\nType A\n@break\n@case('b')\nType B\n@break\n@endswitch\n@endforeach\n</ul>"],
        'echo statements in list items' => ["<ul>\n<li>{{ \$first }}\n<li>{!! \$second !!}\n<li>{{{ \$third }}}\n</ul>"],
        'Blade comments in lists' => ["<ul>\n<li>First item\n{{-- This is a comment --}}\n<li>Second item\n</ul>"],
        'whitespace with Blade' => ["<ul>\n  @foreach(\$items as \$item)\n  <li>{{ \$item }}\n  @endforeach\n</ul>"],
        'empty @foreach with list' => ["<ul>\n@foreach(\$items as \$item)\n@endforeach\n</ul>"],
        '@php blocks in list items' => ["<ul>\n<li>@php \$count = 0; @endphp{{ \$count }}\n<li>Second item\n</ul>"],
        'component tags in list items' => ["<ul>\n<li><x-alert>Message</x-alert>\n<li>Normal item\n</ul>"],

        'p with @foreach' => ["<div>\n@foreach(\$paragraphs as \$text)\n<p>{{ \$text }}\n@endforeach\n</div>"],
        'p with @if' => ["<div>\n@if(\$showIntro)\n<p>Introduction text\n@endif\n<p>Main content\n</div>"],
        'p with @forelse' => ["<article>\n@forelse(\$posts as \$post)\n<p>{{ \$post->title }}\n<p>{{ \$post->excerpt }}\n@empty\n<p>No posts found\n@endforelse\n</article>"],
        'nested @if with p' => ["<div>\n<p>Start\n@if(\$hasContent)\n<div>\n<p>Nested paragraph\n</div>\n@endif\n<p>End\n</div>"],

        'option with @foreach' => ["<select>\n@foreach(\$items as \$item)\n<option value=\"{{ \$item->id }}\">{{ \$item->name }}\n@endforeach\n</select>"],
        'option with @forelse' => ["<select>\n@forelse(\$options as \$opt)\n<option value=\"{{ \$opt }}\">{{ \$opt }}\n@empty\n<option>No options available\n@endforelse\n</select>"],
        '@if with option groups' => ["<select>\n@if(\$hasGroups)\n<optgroup label=\"Group 1\">\n<option>Item 1\n<option>Item 2\n<optgroup label=\"Group 2\">\n<option>Item 3\n@else\n<option>Default option\n@endif\n</select>"],
        'nested @foreach with optgroups' => ["<select>\n@foreach(\$groups as \$group)\n<optgroup label=\"{{ \$group->name }}\">\n@foreach(\$group->items as \$item)\n<option value=\"{{ \$item->id }}\">{{ \$item->name }}\n@endforeach\n@endforeach\n</select>"],
        'complex nested Blade' => ["<div>\n@foreach(\$sections as \$section)\n<p>{{ \$section->title }}\n<ul>\n@foreach(\$section->items as \$item)\n<li>{{ \$item }}\n@endforeach\n</ul>\n@endforeach\n<select>\n@foreach(\$options as \$opt)\n<option>{{ \$opt }}\n@endforeach\n</select>\n</div>"],
        'echo in p' => ["<div>\n<p>{{ \$text1 }}\n<p>{!! \$text2 !!}\n<p>{{{ \$text3 }}}\n</div>"],
        'Blade comments with p' => ["<div>\n<p>First paragraph\n{{-- Comment here --}}\n<p>Second paragraph\n</div>"],
        'whitespace with p' => ["<div>\n  @foreach(\$items as \$item)\n  <p>{{ \$item }}\n  @endforeach\n</div>"],
        '@php with p' => ["<div>\n<p>@php \$count = 0; @endphp{{ \$count }}\n<p>Next paragraph\n</div>"],
        'components with p' => ["<div>\n<p>Before component\n<x-alert>Message</x-alert>\n<p>After component\n</div>"],
        '@switch with p' => ["<div>\n@foreach(\$items as \$item)\n<p>@switch(\$item->type)\n@case('a')\nType A\n@break\n@case('b')\nType B\n@break\n@endswitch\n@endforeach\n</div>"],
        '@switch with options' => ["<select>\n@foreach(\$items as \$item)\n<option>@switch(\$item)\n@case(1)\nOne\n@break\n@case(2)\nTwo\n@break\n@endswitch\n@endforeach\n</select>"],

        'tr with @foreach' => ["<table>\n@foreach(\$rows as \$row)\n<tr>\n<td>{{ \$row }}\n@endforeach\n</table>"],
        'td with @foreach' => ["<table>\n<tr>\n@foreach(\$cells as \$cell)\n<td>{{ \$cell }}\n@endforeach\n</table>"],
        'table with @if header' => ["<table>\n@if(\$showHeader)\n<thead>\n<tr>\n<th>Name\n<th>Age\n@endif\n<tbody>\n@foreach(\$users as \$user)\n<tr>\n<td>{{ \$user->name }}\n<td>{{ \$user->age }}\n@endforeach\n</table>"],
        'table with @forelse' => ["<table>\n<tbody>\n@forelse(\$items as \$item)\n<tr>\n<td>{{ \$item }}\n@empty\n<tr>\n<td>No items found\n@endforelse\n</table>"],
        'nested @foreach in tables' => ["<table>\n@foreach(\$categories as \$category)\n<thead>\n<tr>\n<th colspan=\"2\">{{ \$category->name }}\n<tbody>\n@foreach(\$category->items as \$item)\n<tr>\n<td>{{ \$item->name }}\n<td>{{ \$item->value }}\n@endforeach\n@endforeach\n</table>"],
        'thead/tbody with Blade' => ["<table>\n<thead>\n@foreach(\$headers as \$header)\n<th>{{ \$header }}\n@endforeach\n<tbody>\n@foreach(\$rows as \$row)\n<tr>\n@foreach(\$row as \$cell)\n<td>{{ \$cell }}\n@endforeach\n@endforeach\n</table>"],
        '@if with tfoot' => ["<table>\n@if(\$hasFooter)\n<tfoot>\n<tr>\n<td>Footer content\n@endif\n</table>"],
        '@switch in td' => ["<table>\n@foreach(\$items as \$item)\n<tr>\n<td>@switch(\$item->type)\n@case('a')\nType A\n@break\n@case('b')\nType B\n@break\n@endswitch\n@endforeach\n</table>"],
        'complete data table' => ["<table>\n<colgroup>\n<col>\n<col>\n<thead>\n<tr>\n@foreach(\$columns as \$column)\n<th>{{ \$column }}\n@endforeach\n<tbody>\n@foreach(\$data as \$row)\n<tr>\n@foreach(\$row as \$cell)\n<td>{{ \$cell }}\n@endforeach\n@endforeach\n<tfoot>\n<tr>\n<td colspan=\"2\">Total: {{ count(\$data) }} rows\n</table>"],
        'echo in td' => ["<table>\n<tr>\n<td>{{ \$value1 }}\n<td>{!! \$value2 !!}\n<td>{{{ \$value3 }}}\n</table>"],
        'Blade comments in tables' => ["<table>\n<tr>\n<td>Cell 1\n{{-- Comment between cells --}}\n<td>Cell 2\n</table>"],
        'whitespace with tables' => ["<table>\n  @foreach(\$rows as \$row)\n  <tr>\n    @foreach(\$row as \$cell)\n    <td>{{ \$cell }}\n    @endforeach\n  @endforeach\n</table>"],
        '@php in tables' => ["<table>\n<tr>\n<td>@php \$total = 0; @endphp{{ \$total }}\n<td>Next cell\n</table>"],
        'components in tables' => ["<table>\n<tr>\n<td><x-badge>Status</x-badge>\n<td>Normal content\n</table>"],
    ]);

    test('parses and renders correctly', function (string $input): void {
        expect($this->parse($input)->render())->toBe($input);
    })->with('blade_parse_render');

    it('validates full AST structure for @foreach with list items', function (): void {
        $input = "<ul>\n@foreach(\$items as \$item)\n<li>{{ \$item }}\n@endforeach\n</ul>";

        $doc = $this->parse($input);
        $docChildren = $doc->getChildren();

        expect($docChildren)->toHaveCount(1);

        $ul = $docChildren[0]->asElement();
        expect($ul)->toBeInstanceOf(ElementNode::class)
            ->and($ul->tagNameText())->toBe('ul');

        $ulChildren = $ul->getChildren();
        expect($ulChildren)->toHaveCount(3)
            ->and($ulChildren[0])->toBeInstanceOf(TextNode::class);

        $foreachBlock = $ulChildren[1]->asDirectiveBlock();
        expect($foreachBlock)->toBeInstanceOf(DirectiveBlockNode::class)
            ->and($foreachBlock->nameText())->toBe('foreach')
            ->and($ulChildren[2])->toBeInstanceOf(TextNode::class);

        $blockChildren = $foreachBlock->getChildren();
        expect($blockChildren)->toHaveCount(2);

        $foreachDirective = $blockChildren[0]->asDirective();
        expect($foreachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($foreachDirective->nameText())->toBe('foreach');

        $endforeachDirective = $blockChildren[1]->asDirective();
        expect($endforeachDirective)->toBeInstanceOf(DirectiveNode::class)
            ->and($endforeachDirective->nameText())->toBe('endforeach');

        $bodyChildren = $foreachDirective->getChildren();
        expect($bodyChildren)->toHaveCount(2)
            ->and($bodyChildren[0])->toBeInstanceOf(TextNode::class);

        $li = $bodyChildren[1]->asElement();
        expect($li)->toBeInstanceOf(ElementNode::class)
            ->and($li->tagNameText())->toBe('li');

        $liChildren = $li->getChildren();
        expect($liChildren)->toHaveCount(2);

        $echo = $liChildren[0]->asEcho();
        expect($echo)->toBeInstanceOf(EchoNode::class)
            ->and($echo->content())->toBe(' $item ')
            ->and($liChildren[1])->toBeInstanceOf(TextNode::class)
            ->and($doc->render())->toBe($input);
    });
});
