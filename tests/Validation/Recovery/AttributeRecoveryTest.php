<?php

declare(strict_types=1);

describe('Attribute Recovery Tests', function (): void {
    it('handles various attribute sequences', function (string $template): void {
        $doc = $this->parse($template);
        expect($doc->render())->toBe($template);
    })->with([
        '<div {{ $attrName }}="value">',
        '<div @if($x)disabled@endif>',
        '<div :{{ $binding }}="value">',
        '<div @{{ $event }}="handler">',
        '<div data-{{ $key }}="value">',
        '<div class="{{ $class }}">',
        '<div class="@if($x)active@endif">',
        '<div class="<?= $class ?>">',
        '<div class="{!! $class !!}">',
        '<div class="{{-- ignored --}}visible">',
        '<div class="{{ $a }} {{ $b }}">',
        '<div class="@if($x)a @else b @endif">',
        '<div class="{{ $base }} @if($active)active@endif">',
        '<div style="color: {{ $color }}; size: {{ $size }}">',
        '<div class="{{ $x">',
        '<div class="@if($x)">',
        '<div class="<?php echo">',
        '<div class="{{--">',
        '<div class="{{ $class }}" id="{{ $id }}">',
        '<div @if($x)class="a"@endif @if($y)id="b"@endif>',
        '<input type="{{ $type }}" value="{{ $value }}" {{ $attrs }}>',
        '<a href="{{ $url }}" title="{{ $title }}" @class([$active])>',
    ]);
});
