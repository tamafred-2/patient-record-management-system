@props(['patient', 'visit'])
@if(App\Support\VitalsAccess::canView(auth()->user(), $visit))
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="assessment-record-title">
        @include('followups.assessment-record', ['heading' => 'Vital signs and assessment'])
        @if($visit->status === 'OPEN' && App\Support\VitalsAccess::canRecord(auth()->user(), $visit, (bool) $visit->vitalSign))
            <x-button variant="secondary" href="{{ route('assessments.edit', [$patient, $visit]) }}" class="mt-4">{{ $visit->vitalSign ? 'Update vital signs' : 'Record vital signs' }}</x-button>
        @endif
    </section>
@endif
