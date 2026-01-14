<?php

declare(strict_types=1);

$template = <<<'BLADE'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Untitled' }}</title>
    <style>
        body { background: #fff; }
        /* CSS comment */
    </style>
    @php($meta = ['author' => 'Forte'])
    <!--[if lt IE 9]><script src="legacy.js"></script><![endif]-->
</head>
<body>
    <!-- An HTML comment -->
    <!--[if IE]><p>You are using Internet Explorer</p><![endif]-->
    <div class="card" data-id="{{ $id }}" @if($active) data-active @endif>
        @if($user)
            <h1>Hello, {{ $user->name }}</h1>
        @else
            <h1>Guest</h1>
        @endif

        <button onclick="doThing('{{ $foo }}')">Click</button>

        {{-- Blade comment --}}

        @foreach($items as $item)
            <span>{{ $item }}</span>
        @endforeach

        <?php if ($raw): ?>
            <pre>{!! $raw !!}</pre>
        <?php endif; ?>
<!DOCTYPE html>
<div id="root" :class="{ open: isOpen }" @click.prevent="fn()" data-{{ $dyn }}="x" {{ $node }}={{ $other }}>
  <!-- html comment -->
  {{-- blade comment --}}
  <script type="text/javascript">
    if (a < b) { console.log(">>"); }
    {!! $raw !!}
    {{ $safe }}
  </script>
  <Map<Record<string, Array<Foo>>>> data="ok"></Map>
  @if(true)
    <span>ok</span>
  @else
    </p>
  @endif
  <?php echo "<b>php</b>"; ?>
  <![CDATA[ some <cdata> & weird ]]>
</div>
        <script>
            // raw element content should not be tokenized as HTML
            const x = "{{ jsVar }}"; /* ensure nested braces */
        </script>

        <@myDirective @class([])></@myDirective>
        <@if ($cond)div @endif>Content</@if ($cond)div @endif>
    </div>
</body>
</html>
BLADE;

describe('Incremental Parsing/Fault Tolerance', function () use ($template): void {
    it('tolerates incomplete documents during parsing', function () use ($template): void {
        $len = strlen($template);
        for ($i = 0; $i <= $len; $i += 1) {
            $snippet = substr($template, 0, $i);

            expect($this->parse($snippet)->render())->toBe($snippet);
        }

        $rev = strrev($template);

        for ($i = 0; $i <= $len; $i += 1) {
            $snippet = substr($rev, 0, $i);

            expect($this->parse($snippet)->render())->toBe($snippet);
        }
    });

    it('parses common patterns without falling over', function (string $snippet): void {
        expect($this->parse($snippet)->render())->toBe($snippet);
    })->with([
        '{{',
        '{{{',
        '{{--',
        '{!!',
        '@',
        '@@',
        '<?',
        '<?php',
        '<!--',
        '<![CDATA[',
        '<!--[if',
        '<!--[if IE',
        '<!--[if IE 9]>',
        '<![endif]-->',
        '<!--[if lt IE',
        '<div',
        '<div ',
        '<div class=',
        '<div class="',
        '<div class="foo',
        '</div',
        '</div ',
        '</',
        '<Map<Rec',
        '<Map<Rec>',
        '<Map<<Rec<Rec<<>',
        '<div {{ $x',
        '<div @if(',
        '<script>{{',
        '<!-- {{ $x }}',
        '{{-- @if($x) --',
        '{{ {{ $x }} }}',
        '{!! {!! $x !!} !!}',
        '@if(@if($x))',
        '<!-- <!-- -->',
        '<div class="{{ $a ? "b" : \'c\' }}">',
        "<div onclick=\"alert('{{ \$x }}')\">",
        '<div data-json=\'{"a":"{{ $b }}"}\'/>',
        '<div :class="{ active: isActive }" @click="handle()">',
        '<div x-data="{ open: false }" @click.prevent="open = !open">',
        '<div :class @click :disabled>',
        '<div {{ $attrs }}>',
        '<div {{ $a }}="{{ $b }}">',
        '<div {{ $a }}={{ $b }}>',
        '<div {!! $attrs !!}>',
        '@if($x)<p @endif>',
        '@foreach($x as $y',
        '@if @else @endif',
        '@@if($escaped)',
        '<script>if(a<b){console.log("{{ $x }}")}</script>',
        '<style>.a { color: {{ $color }}; }</style>',
        '<script>const x = {!! json_encode($data) !!};</script>',
        '<?php echo $x; ?>',
        '<?= $x ?>',
        '<?php if ($x): ?><div><?php endif; ?>',
        '{!! $x !!}',
        '{!! "<script>" !!}',
        '{!! $a ? $b : $c !!}',
        '{{-- }} --}}',
        '{{-- {!! $x !!} --}}',
        '<!-- @if($x) -->',
        '/* {{ $x }} */',
        '<!--[if IE 9]>IE 9<![endif]-->',
        '<!--[if lt IE 9]><html class="legacy"><![endif]-->',
        '<!--[if gte mso 9]><table><![endif]-->',
        '<!--[if !IE]><!--><p>Non-IE</p><!--<![endif]-->',
        '<!--[if (gte IE 5.5)&(lt IE 7)]><p>IE 5.5-6</p><![endif]-->',
        '<!--[if !(IE 7)]><p>Not IE 7</p><![endif]-->',
        '<!--[if IE]>{{ $blade }}<![endif]-->',
        '<!--[if IE]><div @if($x)>content</div><![endif]-->',
        "{{\n\$x\n}}",
        "@if(\$x)\n@endif",
        "<div\nclass=\"foo\"\n>",
        '',
        ' ',
        "\n",
        "\t",
        '<div>{{ $émoji }}</div>',
        '<div class="日本語">{{ $中文 }}</div>',
        '&amp;',
        '&#123;',
        '&{{ $entity }};',
        '<!DOCTYPE html>',
        '<?xml version="1.0"?>',
        '<!DOCTYPE html><html {{ $attrs }}>',
        '<![CDATA[ {{ $x }} ]]>',
        '<![CDATA[ <div> ]]>',
        '<br/>',
        '<br />',
        '<input type="{{ $type }}"/>',
        '<img src="{{ $src }}" />',
        '<div><div><div>{{ $deep }}</div></div></div>',
        '@if($a)@if($b)@if($c)x@endif@endif@endif',
        '<x-component :prop="$value"/>',
        '<x-slot name="header">{{ $title }}</x-slot>',
        '<div @if($x) class="a" @else class="b" @endif>',
        '{{ $a ?? $b ?? $c }}',
        '{{ $x->y->z() }}',
        '{!! $html ?? "<default>" !!}',
        '# {{ $title }}',
        '## Header with {!! $html !!}',
        '### @if($show) Conditional @endif',
        '[{{ $text }}]({{ $url }})',
        '![alt]({{ $src }} "{{ $title }}")',
        '[link][{{ $ref }}]',
        '[![img]({{ $src }})]({{ $href }})',
        '`{{ $code }}`',
        '```php{{ $lang }}',
        "```\n{{ \$code }}\n```",
        '`` `nested` {{ $x }} ``',
        "~~~\n@if(\$x)\ncode\n@endif\n~~~",
        '*{{ $italic }}*',
        '**{{ $bold }}**',
        '***{{ $boldItalic }}***',
        '_{{ $under }}_',
        '__{{ $double }}__',
        '~~{{ $strike }}~~',
        '- {{ $item }}',
        '* @foreach($items as $item){{ $item }}@endforeach',
        '1. {{ $first }}',
        '   - {{ $nested }}',
        '> {{ $quote }}',
        '> > {{ $nested }}',
        '> @if($x) quoted @endif',
        '| {{ $col1 }} | {{ $col2 }} |',
        '|---|---|',
        '| {!! $html !!} | text |',
        '---',
        '***',
        '___',
        '[{{ $ref }}]: {{ $url }}',
        '[^{{ $note }}]: {{ $footnote }}',
        '<div>{{ $x }}</div> text **bold**',
        '<!-- comment --> # Header',
        '\*{{ $notItalic }}\*',
        '\{{ $escaped }}',
        '\\\\{{ $backslash }}',
        '{"key": "{{ $value }}"}',
        '{"{{ $key }}": "value"}',
        '[{{ $a }}, {{ $b }}, {{ $c }}]',
        '{"nested": {"deep": "{{ $x }}"}}',
        '{"quote": "{{ $x }}\"escaped\""}',
        '{"newline": "{{ $x }}\\n"}',
        '{"unicode": "{{ $x }}\\u0000"}',
        '{"path": "C:\\\\{{ $path }}"}',
        '{"num": {{ $number }}}',
        '{"bool": {{ $condition ? "true" : "false" }}}',
        '{"null": {{ $x ?? "null" }}}',
        '[@foreach($items as $item)"{{ $item }}"@if(!$loop->last),@endif@endforeach]',
        '{"incomplete": "{{ $x',
        '[{{ $a }},',
        '{"key":',
        'key: {{ $value }}',
        '{{ $key }}: value',
        'key: "{{ $quoted }}"',
        "key: '{{ \$single }}'",
        "parent:\n  child: {{ \$x }}",
        "list:\n  - {{ \$item }}",
        "deep:\n  nested:\n    value: {{ \$x }}",
        'items: [{{ $a }}, {{ $b }}]',
        'flow: {key: {{ $value }}}',
        "text: |\n  {{ \$multiline }}",
        "text: >\n  {{ \$folded }}",
        "literal: |+\n  {{ \$keep }}",
        'anchor: &{{ $name }} value',
        'alias: *{{ $ref }}',
        '<<: *{{ $merge }}',
        'bool: {{ $x ? "yes" : "no" }}',
        'null: {{ $x ?? "~" }}',
        'date: {{ $date }}',
        'key: value # {{ $comment }}',
        '# @if($x) comment @endif',
        "---\nkey: {{ \$x }}",
        "...\n{{ \$next }}",
        '.{{ $class }} { color: red; }',
        '#{{ $id }} { }',
        '[data-{{ $attr }}] { }',
        '{{ $selector }} { }',
        'div { color: {{ $color }}; }',
        'div { background: url("{{ $url }}"); }',
        'div { content: "{{ $text }}"; }',
        'div { font-family: {{ $font }}, sans-serif; }',
        ':root { --{{ $name }}: {{ $value }}; }',
        'div { color: var(--{{ $var }}); }',
        '@media (min-width: {{ $breakpoint }}) { }',
        '@media {{ $query }} { .class { } }',
        'div { rewrite: rotate({{ $deg }}deg); }',
        'div { calc({{ $a }} + {{ $b }}); }',
        'div { rgba({{ $r }}, {{ $g }}, {{ $b }}, {{ $a }}); }',
        '.{{ $class }}:hover { }',
        '.item:nth-child({{ $n }}) { }',
        '::{{ $pseudo }} { }',
        '/* {{ $comment }} */',
        '/* @if($x) conditional @endif */',
        '@keyframes {{ $name }} { from { } to { } }',
        '@keyframes anim { {{ $percent }}% { } }',
        '@import "{{ $url }}";',
        '@charset "{{ $encoding }}";',
        '.parent { .{{ $child }} { } }',
        '&.{{ $modifier }} { }',
        '`${{{ $jsVar }}}`',
        '`text {{ $blade }} ${js}`',
        'const { {{ $prop }} } = obj;',
        'const [{{ $first }}, ...{{ $rest }}] = arr;',
        '({{ $param }}) => {{ $body }}',
        '() => { return {{ $value }}; }',
        '/{{ $pattern }}/g',
        '/[{{ $chars }}]+/',
        'const el = <div class="{{ $class }}"></div>;',
        'return <{{ $component }} />;',
        '{{ urlencode($param) }}',
        '?param={{ $value }}&other={{ $other }}',
        'https://{{ $domain }}/{{ $path }}?{{ $query }}',
        '{{ $name }} <{{ $email }}>',
        'To: {{ $to }}\nSubject: {{ $subject }}',
        '"SELECT * FROM {{ $table }}"',
        '"WHERE id = {{ $id }}"',
        '{{ $command }} --flag={{ $value }}',
        '${{ $envVar }}',
        '$({{ $subshell }})',
        '<ns:{{ $tag }} xmlns:ns="{{ $uri }}">',
        '<{{ $ns }}:element />',
        '<svg viewBox="{{ $viewBox }}"><path d="{{ $d }}"/></svg>',
        '<circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $r }}"/>',
        str_repeat('{{ $x }}', 50),
        '<div>'.str_repeat('a', 1000).'{{ $x }}</div>',
        '{{{{{{ $x }}}}}}',
        '{{{ $x }}}',
        '{{ "{$x}" }}',
        '@media @if($x) {{ $y }} @endif',
        'email@{{ $domain }}',
        '@{{ $escaped }} vs {{ $normal }}',
        "{{ \$x }}\u{200B}{{ \$y }}",  // zero-width space
        "{{ \$x }}\u{FEFF}{{ \$y }}",  // BOM
        '{{ $emoji }}🎉{{ $more }}',
    ]);
});
