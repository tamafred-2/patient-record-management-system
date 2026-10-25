@props(['variant' => 'primary', 'href' => null])
@php
    $classes = 'inline-block rounded-lg px-4 py-2.5 text-center text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:opacity-50 '.match ($variant) {
        'secondary' => 'border border-emerald-800 text-emerald-800 hover:bg-emerald-50',
        'neutral' => 'border border-slate-300 text-slate-900 hover:bg-slate-50',
        'danger' => 'border border-red-300 text-red-800 hover:bg-red-50',
        default => 'border border-transparent bg-emerald-800 text-white hover:bg-emerald-900',
    };
@endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
<button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
