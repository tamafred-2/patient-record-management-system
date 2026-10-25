@props(['visit'])
@if(($visit->priority ?? 'REGULAR') !== 'REGULAR')
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-bold uppercase tracking-wide '.($visit->priority === 'EMERGENCY' ? 'border-red-300 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-900')]) }}>{{ $visit->priorityLabel() }}</span>
@endif
