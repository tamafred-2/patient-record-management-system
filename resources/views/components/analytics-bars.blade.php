@props(['rows', 'label'])
@php
    $maximum = max([1, ...array_map(fn ($value) => abs($value), array_values($rows))]);
    $signed = count(array_filter($rows, fn ($value) => $value < 0)) > 0;
@endphp
<div role="group" aria-label="{{ $label }} chart" class="space-y-4">
    @forelse($rows as $name => $value)
        <div>
            <div class="mb-2 flex items-start justify-between gap-3 text-sm"><span class="text-slate-600">{{ $name }}</span><span class="font-semibold tabular-nums text-slate-900">{{ number_format($value) }}</span></div>
            <div aria-hidden="true" class="relative h-3 overflow-hidden rounded-full bg-slate-100">
                @if($signed)<span class="absolute inset-y-0 left-1/2 z-10 w-px bg-slate-400"></span>@endif
                <span class="absolute inset-y-0 rounded-full {{ $value < 0 ? 'bg-amber-600' : 'bg-emerald-600' }}" style="width: {{ abs($value) / $maximum * ($signed ? 50 : 100) }}%; left: {{ $signed ? ($value < 0 ? 50 - abs($value) / $maximum * 50 : 50) : 0 }}%"></span>
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">No records in this date range.</p>
    @endforelse
    @if($rows && !array_filter($rows))<p class="text-xs text-slate-500">All counts are zero for this date range.</p>@endif
    <p class="text-xs text-slate-500">Bar length shows count; each chart uses its own scale.{{ $signed ? ' Negative changes extend left of zero.' : '' }}</p>
</div>
