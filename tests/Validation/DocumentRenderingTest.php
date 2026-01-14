<?php

declare(strict_types=1);

describe('Document Rendering Test', function (): void {
    dataset('document rendering tests', [
        '5-level nested lists' => ['<ul><li>L1<ul><li>L2<ul><li>L3<ul><li>L4<ul><li>L5</ul></ul></ul></ul></ul>'],
        'deeply nested table sections' => ["<table>\n<tbody>\n<tr>\n<td>Data\n<table>\n<tbody>\n<tr>\n<td>Nested\n</table>\n</table>"],

        'preserves whitespace before elements' => ["<ul>\n  <li>Indented\n  <li>Also indented\n</ul>"],
        'preserves whitespace between elements' => ["<ul>\n<li>First\n\n<li>Second\n</ul>"],
        'handles tabs and mixed whitespace' => ["<ul>\n\t<li>Tab\n\t<li>Tab\n</ul>"],

        'preserves attributes on li' => ['<ul><li class="item" id="item-1">A<li data-value="2" class="special">B</ul>'],
        'preserves attributes on option' => ['<select><option value="1" selected>One<option value="2">Two</select>'],
        'preserves attributes on table cells' => ['<table><tr><td colspan="2" class="wide">A<td rowspan="3">B</table>'],
        'preserves attributes on table sections' => ['<table><thead class="header"><tr><th>H<tbody id="body"><tr><td>D</table>'],

        'handles unicode characters' => ['<ul><li>你好<li>مرحبا<li>Здравствуйте</ul>'],
        'handles emoji' => ['<ul><li>🎉<li>🚀<li>✨</ul>'],
        'handles HTML entities' => ['<ul><li>&lt;tag&gt;<li>&amp;<li>&quot;</ul>'],
        'handles special HTML characters' => ['<table><tr><td>&nbsp;<td>&copy;<td>&reg;</table>'],

        'mix in table' => ['<table><tr><td>Omitted<td>Omitted</tr><tr><td>Explicit</td><td>Explicit</td></tr></table>'],

        'context boundary in nested lists' => ['<ul><li>Outer<ul><li>Inner</ul></ul>'],

        'malformed nested lists' => ['<div><li>Invalid<li>Also invalid</div>'],
        'malformed table structures' => ['<table><td>Invalid<td>Also invalid</table>'],
        'mixed valid and invalid nesting' => ['<div><p>Valid<li>Invalid<dt>Invalid<ul><li>Valid</ul></div>'],
        'deeply malformed structures' => ['<table><li><option><dt><td>Chaos</table>'],
        'interleaved closing tags' => ['<ul><ol><li>A</ul></ol>'],
        'mismatched closing tags' => ['<ul><li>Item</td></ul>'],

        'p followed by p' => ["<div>\n<p>First paragraph\n<p>Second paragraph\n</div>"],
        'p followed by ul' => ["<div>\n<p>Some text\n<ul>\n<li>Item\n</ul>\n</div>"],
        'paragraphs in article' => ["<article>\n<p>First paragraph\n<p>Second paragraph\n<div>\n<p>Nested paragraph\n</div>\n</article>"],
        'multiple block elements' => ["<div>\n<p>Paragraph one\n<blockquote>Quote</blockquote>\n<p>Paragraph two\n<section>Section content</section>\n</div>"],
        'option with optgroup' => ["<select>\n<option>Ungrouped\n<optgroup label=\"Group 1\">\n<option>Option 1\n</optgroup>\n</select>"],
        'select with omitted tags' => ["<select>\n<optgroup label=\"Fruits\">\n<option>Apple\n<option>Banana\n<optgroup label=\"Vegetables\">\n<option>Carrot\n<option>Lettuce\n</select>"],
        'datalist options' => ["<datalist id=\"browsers\">\n<option value=\"Chrome\">\n<option value=\"Firefox\">\n<option value=\"Safari\">\n</datalist>"],
        'mixed omitted explicit' => ["<div>\n<p>First (omitted)\n<p>Second (explicit)</p>\n<p>Third (omitted)\n<div>End</div>\n</div>"],
        'nested structures' => ["<div>\n<p>Intro text\n<ul>\n<li>Item one\n<li>Item two\n</ul>\n<p>More text\n<select>\n<option>Choice 1\n<option>Choice 2\n</select>\n</div>"],

        'td followed by th' => ["<table>\n<tr>\n<td>Data\n<th>Header\n</table>"],
        'th followed by td' => ["<table>\n<tr>\n<th>Header\n<td>Data\n</table>"],
        'thead followed by tfoot' => ["<table>\n<thead>\n<tr>\n<th>Header\n<tfoot>\n<tr>\n<td>Footer\n</table>"],
        'tbody followed by tbody' => ["<table>\n<tbody>\n<tr>\n<td>First section\n<tbody>\n<tr>\n<td>Second section\n</table>"],
        'tbody followed by tfoot' => ["<table>\n<tbody>\n<tr>\n<td>Data\n<tfoot>\n<tr>\n<td>Footer\n</table>"],
        'colgroup followed by thead' => ["<table>\n<colgroup>\n<col>\n<thead>\n<tr>\n<th>Header\n</table>"],
        'colgroup followed by tbody' => ["<table>\n<colgroup>\n<col>\n<tbody>\n<tr>\n<td>Data\n</table>"],
        'colgroup followed by tr' => ["<table>\n<colgroup>\n<col>\n<tr>\n<td>Data\n</table>"],
        'mixed omitted explicit#2' => ["<table>\n<thead>\n<tr>\n<th>Name</th>\n<th>Age\n</thead>\n<tbody>\n<tr>\n<td>John\n<td>30</td>\n</tr>\n<tr>\n<td>Jane</td>\n<td>25\n</tbody>\n</table>"],

        'verbatim' => ['@verbatim{{ $raw }}@endverbatim'],

        'escaped raw echo with @ prefix' => ['@{!! $escaped !!}'],
        'escaped echo with @ prefix parses correctly' => ['@{{ $escaped }}'],
        'section directive' => ["@section('content') Hello @endsection"],
    ]);

    test('parses and renders correctly', function (string $input): void {
        expect($this->parse($input)->render())->toBe($input);
    })->with('document rendering tests');
});
