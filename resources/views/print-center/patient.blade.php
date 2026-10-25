<x-layout title="Documents & printouts">
    <a href="{{ url()->previous() }}" class="text-emerald-800 underline">Back</a>
    <div class="mt-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="font-semibold text-emerald-800">{{ $patient->patient_number }}</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $patient->full_name }}</h1>
            <p class="mt-1 text-slate-600">Documents & printouts</p>
        </div>
        <x-button :href="route('print-center.summary', $patient)">Print patient summary</x-button>
    </div>
    <p class="mt-4 max-w-3xl text-sm text-slate-600">Every printout you can open for this patient, grouped by visit. Printouts use saved records only and are not official RHU form layouts unless stated. Service activity reports are on the Reports page.</p>
    <div class="mt-6 space-y-4">
        @forelse($visits as $item)
            @php($visit = $item['visit'])
            <article id="visit-{{ $visit->id }}" class="scroll-mt-20 rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-lg font-semibold">{{ $visit->visit_date->format('M j, Y') }} · {{ $visit->visit_number }}</h2>
                    <x-visit-priority :visit="$visit" />
                </div>
                <p class="text-sm text-slate-600">{{ $visit->service?->name ?? 'Former service' }} · Queue {{ $visit->queue_reference ?? 'not recorded' }} · {{ $visit->status }}</p>
                @if($item['documents'])
                    <x-visit-documents :visit="$visit" :documents="$item['documents']" heading="Printouts" class="mt-3 border-0 p-0" />
                @else
                    <p class="mt-3 text-sm text-slate-500">No printouts available to you for this visit.</p>
                @endif
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white p-6 text-slate-600">No visits recorded.</p>
        @endforelse
        @if($more)<p class="text-sm text-slate-600">Only the latest {{ App\Http\Controllers\PrintCenterController::VISIT_LIMIT }} visits are listed. Use the patient history timeline for older visits.</p>@endif
    </div>
</x-layout>
