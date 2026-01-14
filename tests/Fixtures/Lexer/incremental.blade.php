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