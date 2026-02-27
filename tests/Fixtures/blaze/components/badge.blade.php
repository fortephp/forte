@blaze(fold: true)

@props(['color' => 'gray'])

<span class="badge-{{ $color }}">{{ $slot }}</span>
