@blaze(fold: true)

@props(['title' => ''])

<div class="card">
    @if($title)<h2>{{ $title }}</h2>@endif
    {{ $slot }}
</div>
