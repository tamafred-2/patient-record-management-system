@props(['href'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-sm text-emerald-800 underline hover:text-emerald-950']) }}><span aria-hidden="true">←</span>{{ $slot }}</a>
