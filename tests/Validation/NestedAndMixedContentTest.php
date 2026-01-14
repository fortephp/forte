<?php

declare(strict_types=1);

describe('Nested and Mixed Content Tests', function (): void {
    it('handles many consecutive constructs', function (): void {
        $template = str_repeat('{{ $x }}', 50);
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles alternating construct types', function (): void {
        $template = '';
        for ($i = 0; $i < 20; $i++) {
            $template .= match ($i % 4) {
                0 => '{{ $x }}',
                1 => '@if($y)',
                2 => '<div>',
                3 => '<?php ?>',
            };
        }
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles deeply nested mixed constructs', function (): void {
        $template = '@if($a)<div>@foreach($items as $item)<span>{{ $item }}</span>@endforeach</div>@endif';
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles all construct types together', function (): void {
        $template = <<<'BLADE'
<!DOCTYPE html>
<html {{ $attrs }}>
<head>
    <?php echo $meta; ?>
    <title>{{ $title }}</title>
    <style>.{{ $class }} { color: {{ $color }}; }</style>
</head>
<body @class(['dark' => $dark])>
    {{-- Main content --}}
    @if($show)
        <div id="{{ $id }}">
            @foreach($items as $item)
                <span>{{ $item->name }}</span>
                @if($item->active)
                    {!! $item->html !!}
                @endif
            @endforeach
        </div>
    @else
        <p>{{ $fallback }}</p>
    @endif
    <!-- HTML comment with {{ $blade }} -->
    <script>
        var data = {!! json_encode($data) !!};
        if (data.count > {{ $threshold }}) {
            console.log("{{ $message }}");
        }
    </script>
    <?php if ($legacy): ?>
        <div class="legacy">{{ $legacyContent }}</div>
    <?php endif; ?>
</body>
</html>
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles worst-case interrupted constructs', function (): void {
        $template = '{{ @if(<div <?php {{-- {!! }}} @end ?> --}} !!} </div>) $x }}';
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles rapid context switching', function (): void {
        $template = '<div>{{ $a }}</div>@if($b)<span>{!! $c !!}</span>@endif<?php echo $d; ?><p>{{-- $e --}}</p>';
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles form tags', function (): void {
        $template = <<<'BLADE'
<form action="{{ route('submit') }}" method="POST">
    @csrf
    <input type="text" name="email" value="{{ old('email') }}" class="@error('email') is-invalid @enderror">
    @error('email')
        <span class="error">{{ $message }}</span>
    @enderror
    <button type="submit">{{ __('Submit') }}</button>
</form>
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles component with slots', function (): void {
        $template = <<<'BLADE'
<x-card :title="$title" {{ $attributes }}>
    <x-slot:header>
        <h2>{{ $heading }}</h2>
    </x-slot:header>

    @foreach($items as $item)
        <x-card-item :item="$item" />
    @endforeach

    <x-slot:footer>
        {{ $footer }}
    </x-slot:footer>
</x-card>
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });

    it('parses alpinejs', function (): void {
        $template = <<<'BLADE'
<div x-data="{ open: false, count: {{ $initialCount }} }"
     @click.outside="open = false"
     :class="{ 'active': open }">
    <button @click="open = !open">
        {{ $buttonText }}
    </button>
    <div x-show="open" x-transition>
        @foreach($options as $option)
            <a @click="count++" href="{{ $option->url }}">
                {{ $option->label }}
            </a>
        @endforeach
    </div>
</div>
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });

    it('parses Livewire', function (): void {
        $template = <<<'BLADE'
<div>
    <input type="text" wire:model.live="search" class="{{ $inputClass }}">

    @if($results->isNotEmpty())
        <ul>
            @foreach($results as $result)
                <li wire:key="{{ $result->id }}" wire:click="select({{ $result->id }})">
                    {{ $result->name }}
                </li>
            @endforeach
        </ul>
    @else
        <p>{{ __('No results found') }}</p>
    @endif

    {{ $results->links() }}
</div>
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles complex conditional rendering', function (): void {
        $template = <<<'BLADE'
@auth
    @if($user->isAdmin())
        <x-admin-panel :user="$user" />
    @elseif($user->isModerator())
        <x-moderator-panel :user="$user" />
    @else
        <x-user-panel :user="$user" />
    @endif
@else
    @guest
        <x-login-prompt />
    @endguest
@endauth
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });

    it('handles JSON in script tags', function (): void {
        $template = <<<'BLADE'
<script type="application/json" id="app-data">
{!! json_encode([
    'user' => $user,
    'config' => [
        'debug' => config('app.debug'),
        'url' => config('app.url'),
    ],
    'translations' => $translations,
]) !!}
</script>
<script>
    window.App = JSON.parse(document.getElementById('app-data').textContent);
    console.log('Loaded {{ count($translations) }} translations');
</script>
BLADE;
        expect($this->parse($template)->render())->toBe($template);
    });
});
