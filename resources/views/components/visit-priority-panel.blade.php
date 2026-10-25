@props(['patient', 'visit'])
<section {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5']) }} aria-labelledby="visit-priority-title">
    <div class="flex flex-wrap items-center gap-3">
        <h2 id="visit-priority-title" class="font-semibold">Visit priority:</h2>
        @if($visit->priority === 'REGULAR')<span class="text-sm text-slate-600">Regular</span>@else<x-visit-priority :visit="$visit" />@endif
    </div>
    @can('visits.prioritize')
        @if($visit->status === 'OPEN')
            <details class="mt-3" @if($errors->hasAny(['priority', 'priority_reason', 'priority_note'])) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-emerald-800">Change priority</summary>
                <form method="POST" action="{{ route('visits.priority', [$patient, $visit]) }}" class="mt-4 space-y-4">
                    @csrf @method('PUT')
                    <x-visit-priority-fields :visit="$visit" id-prefix="change_priority" />
                    <x-button>Save priority</x-button>
                </form>
            </details>
        @endif
    @endcan
</section>
