@props(['patient', 'visit'])
@if(App\Support\VitalsAccess::canView(auth()->user(), $visit))
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="assessment-record-title">
        @include('followups.assessment-record', ['heading' => 'Vital signs and assessment'])
        @if($visit->status === 'OPEN' && App\Support\VitalsAccess::canRecord(auth()->user(), $visit, (bool) $visit->vitalSign))
            <a href="{{ route('assessments.edit', [$patient, $visit]) }}" class="mt-4 inline-block rounded-lg border border-emerald-800 px-4 py-2 text-sm font-semibold text-emerald-800">{{ $visit->vitalSign ? 'Update vital signs' : 'Record vital signs' }}</a>
        @endif
    </section>
@endif
