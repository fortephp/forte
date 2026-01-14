<?php

declare(strict_types=1);

describe('Nested Construct Recovery Test', function (): void {
    it('recovers in nested contexts gracefully', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '@if($x){{ $y }}@endif',
        '@foreach($items as $item){{ $item }}@endforeach',
        '@if($a)@if($b)inner@endif@endif',
        '@section("x")@yield("y")@endsection',
        '{{ $a ?? @include("default") }}',
        '@if($x)<div>content</div>@endif',
        '@foreach($items as $item)<li>{{ $item }}</li>@endforeach',
        '@section("nav")<nav><a href="#">Link</a></nav>@endsection',
        '@if($show)<span class="icon"></span>@endif',
        '<div>@if($x)yes@endif</div>',
        '<ul>@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ul>',
        '<nav>@include("partials.nav")</nav>',
        '<script>var x = {{ json_encode($data) }};</script>',
        '@if($x)<?php echo "yes"; ?>@endif',
        '@foreach($items as $item)<?= $item ?>@endforeach',
        '@php <?php nested(); ?> @endphp',
        '<?php echo "{{ $x }}"; ?>',
        '<?php if($x): ?>{{ $y }}<?php endif; ?>',
        '<?php /* @if($x) */ ?>',
        '@if($a)<div>@if($b){{ $c }}@endif</div>@endif',
        '<div>@foreach($items as $item)<span>{{ $item }}</span>@endforeach</div>',
        '@section("x")<nav>@foreach($links as $link)<a>{{ $link }}</a>@endforeach</nav>@endsection',
        '<ul>@foreach($categories as $cat)<li>@foreach($cat->items as $item){{ $item }}@endforeach</li>@endforeach</ul>',
        '@if($a)@foreach($items as $item)@if($item->show){{ $item }}@endif@endforeach@endif',
        '@if($x)<div>@endif</div>',
        '<div>@if($x)</div>@endif',
        '@foreach($items as $item)<li>@endforeach</li>',
        '<ul>@foreach($items as $item)</ul>@endforeach',
        '@if($a)<div>@else</div><span>@endif</span>',
        '<div>{{ $x </div> }}',
        '{{ $a <span> $b }}',
        '<p>{{ $text }}</p><p>{{ $more',
        '<?php if($x): ?><div><?php endif; ?></div>',
        '<div><?php if($x): ?></div><?php endif; ?>',
        '<?php echo "<div>"; ?></div>',
        '{{-- <div> --}}</div>',
        '<div>{{-- </div> --}}',
        '<!-- <span> --></span>',
        '<p><!-- </p> -->',
    ]);
});
