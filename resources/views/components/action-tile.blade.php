@props(['href', 'title', 'description' => null, 'highlight' => false])
{{-- The whole tile is clickable, but only the title forms the link's accessible name. --}}
<li {{ $attributes->class(['relative rounded-lg border p-4 transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-emerald-700 hover:border-emerald-700 hover:bg-emerald-50', 'border-emerald-700 bg-emerald-50' => $highlight, 'border-slate-200 bg-white' => !$highlight]) }}>
    <a href="{{ $href }}" class="font-semibold text-emerald-900 after:absolute after:inset-0 after:content-[''] focus:outline-none">{{ $title }}</a>
    @if($description)<p class="mt-1 text-sm text-slate-600">{{ $description }}</p>@endif
</li>
