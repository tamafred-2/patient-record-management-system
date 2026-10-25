<x-layout title="Patient history">
    @can('view', $patient)
        <x-back-link :href="route('patients.show', $patient)">Back to patient profile</x-back-link>
    @else
        {{-- Doctors reach the timeline from a visit and may not have registry access. --}}
        <x-back-link :href="url()->previous() !== url()->current() ? url()->previous() : route('dashboard')">Back</x-back-link>
    @endcan
    <h1 class="mt-5 text-3xl font-semibold">Patient history</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-2 text-sm text-slate-500">Registered {{ $patient->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Events show original recording times.</p>
    <div class="mt-4 flex flex-wrap gap-3">
        <x-button variant="secondary" :href="route('print-center.patient', $patient)">Documents &amp; printouts</x-button>
        <x-button :href="route('print-center.summary', $patient)">Print patient summary</x-button>
    </div>
    <div class="mt-6 rounded-xl border border-slate-200 bg-white">
        <x-visit-history-filters embedded class="p-4" />
        <x-scroll-queue flush :paginator="$visits" id="visits" kind="cards" label="Record history, scroll to load more"><div data-queue-items>@forelse($visits as $visit)
            <section class="rounded-xl border border-slate-200 bg-white p-6">
                <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-semibold">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</h2><a href="{{ route('print-center.patient', [$patient, 'visit' => $visit->id]) }}#visit-{{ $visit->id }}" class="text-sm font-semibold text-emerald-800 underline">Printouts</a></div>
                <p class="mt-2 text-sm">{{ $visit->service->name }} &middot; {{ $visit->status }}</p>
                @php
                    $events=collect([['at'=>$visit->created_at,'label'=>'Visit registered']]);
                    if ($clinical) {
                        foreach (['vitalSign'=>'Vital signs recorded','eligibilityCheck'=>'PhilHealth confirmation recorded','treatmentRecord'=>'ITR recorded'] as $relation=>$label) { if ($visit->$relation) $events->push(['at'=>$visit->$relation->created_at,'label'=>$label]); }
                        if ($visit->prescription) {
                            $events->push(['at'=>$visit->prescription->created_at,'label'=>'Prescription drafted']);
                            if ($visit->prescription->prescribed_at) $events->push(['at'=>$visit->prescription->prescribed_at,'label'=>'Prescription issued']);
                            foreach ($visit->prescription->dispensings as $release) $events->push(['at'=>$release->dispensed_at,'label'=>$release->items->contains(fn ($line) => $line->quantity_dispensed > 0) ? 'Medicine release recorded' : 'Pharmacy outcome recorded (not released here)']);
                        }
                        foreach ($visit->laboratoryRecords as $entry) $events->push(['at'=>$entry->created_at,'label'=>'Laboratory service recorded']);
                        foreach ($visit->dispositionRecords as $entry) $events->push(['at'=>$entry->created_at,'label'=>App\Models\DispositionRecord::TYPES[$entry->type]]);
                    }
                @endphp
                <ol class="mt-4 space-y-3 border-l-2 border-emerald-100 pl-4">@foreach($events->sortBy('at') as $event)<li><time class="text-xs text-slate-500">{{ $event['at']->timezone('Asia/Manila')->format('M j, Y g:i A') }}</time><p class="text-sm">{{ $event['label'] }}</p></li>@endforeach</ol>
                <a class="mt-5 inline-block font-semibold text-emerald-800 underline" href="{{ $clinical ? route('consultations.show',[$patient,$visit]) : route('patients.visits.show',[$patient,$visit]) }}">Open visit</a>
            </section>
        @empty<p>No visits recorded.</p>@endforelse</div></x-scroll-queue>
    </div>
</x-layout>
