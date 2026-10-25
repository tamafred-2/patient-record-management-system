<x-layout title="Visit summary">
    @php
        $vitals = $visit->vitalSign;
        $confirmation = $visit->eligibilityCheck;
        $fields = ['systolic_bp' => ['Systolic BP', 'mmHg'], 'diastolic_bp' => ['Diastolic BP', 'mmHg'], 'temperature' => ['Temperature', 'Celsius'], 'pulse_rate' => ['Pulse rate', 'bpm'], 'height_cm' => ['Height', 'cm'], 'weight_kg' => ['Weight', 'kg'], 'respiratory_rate' => ['Respiratory rate', 'cpm'], 'oxygen_saturation' => ['O2 saturation', '%']];
    @endphp
    <x-back-link href="{{ route('consultations.index', ['date' => $visit->visit_date->toDateString()]) }}">Back to doctor visits</x-back-link>
    @php
        $philhealth = match ($confirmation?->philhealth_confirmed) {
            true => ['With record', 'border-emerald-300 bg-emerald-50 text-emerald-900', 'M5 12.5l4.5 4.5L19 7.5'],
            false => ['No record', 'border-red-300 bg-red-50 text-red-900', 'M6 6l12 12M18 6L6 18'],
            default => ['Not checked', 'border-slate-300 bg-slate-50 text-slate-600', 'M6 12h12'],
        };
        $philhealthDetails = collect(['PhilHealth: '.$philhealth[0], $confirmation?->remarks, $confirmation ? 'Updated '.$confirmation->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') : null])->filter()->join(' · ');
    @endphp
    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-3xl font-semibold">Visit summary</h1>
        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold {{ $philhealth[1] }}" title="{{ $philhealthDetails }}">
            PhilHealth
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $philhealth[2] }}"/></svg>
            <span class="sr-only">{{ $philhealthDetails }}</span>
        </span>
    </div>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <div class="flex flex-wrap items-center gap-3"><h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2><x-visit-priority-inline :patient="$patient" :visit="$visit" /></div>
        <p class="mt-2 text-sm text-slate-600">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Visit</dt><dd>{{ $visit->visit_number }}</dd></div>
            <div><dt class="text-sm text-slate-500">Visit date</dt><dd>{{ $visit->visit_date->format('M j, Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">Requested service</dt><dd>{{ $visit->service->name }}</dd></div>
            <div><dt class="text-sm text-slate-500">Queue number</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Visit status</dt><dd>{{ $visit->status }}</dd></div>
        </dl>
    </section>
    <x-visit-follow-up-panel :patient="$patient" :visit="$visit" class="mt-6" />
    <x-visit-documents :visit="$visit" class="mt-6" />
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" x-data="{}" @if($errors->hasAny(['reassessment', 'reason', 'resolution_note', 'assessment_version'])) x-init="$nextTick(() => $refs.reassessmentDialog.showModal())" @endif>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold">Vital signs</h2>
            <x-button variant="secondary" type="button" x-on:click="$refs.reassessmentDialog.showModal()" aria-haspopup="dialog" class="inline-flex items-center gap-2">Reassessment requests @if($visit->pendingAssessmentRequest)<span class="rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-900">Pending</span>@endif</x-button>
        </div>
        @if($pendingRequest = $visit->pendingAssessmentRequest)<p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">Reassessment requested by {{ $pendingRequest->requester->name }} · waiting for nurse. The values below are the last saved assessment.</p>@endif
        @if(!$vitals)<p class="mt-3 text-slate-600">Vital signs have not been recorded for this visit.</p>@endif
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($fields as $field => [$label, $unit])
                <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $vitals?->$field !== null ? $vitals->$field.' '.$unit : 'Not recorded' }}</dd></div>
            @endforeach
        </dl>
        @if($vitals)<p class="mt-5 text-sm text-slate-500">Last updated {{ $vitals->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Recorded values are shown without clinical interpretation.</p>@endif
        @include('consultations.reassessment-dialog')
    </section>
    @include('consultations.assessment')
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="visit-actions-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="visit-actions-title" class="text-xl font-semibold">Visit actions</h2>
            @can('complete', $visit)
                <x-button href="{{ route('visits.completion', [$patient, $visit]) }}">{{ $visit->isOpen() ? 'Review and complete visit' : 'View completion details' }}</x-button>
            @endcan
        </div>
        <div class="mt-5 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Consultation</h3>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    <x-action-tile :href="route('itr.edit', [$patient, $visit])" title="Open Digital ITR" description="Screening, assessment, planning and diagnoses for this visit." />
                    @can('prescriptions.view')<x-action-tile :href="route('prescriptions.show', [$patient, $visit])" title="Open prescription" description="Draft or issue medicines for Pharmacy." />@endcan
                    @can('certificates.prepare')<x-action-tile :href="route('certificates.index', [$patient, $visit])" title="Medical certificates" description="Prepare, print or download a certificate." />@endcan
                    @can('dispositions.view')<x-action-tile :href="route('dispositions.show', [$patient, $visit])" title="Referrals / certificate requests" description="Referrals, higher-facility advice and requests." />@endcan
                </ul>
            </div>
            @if(auth()->user()->can('history.view') || auth()->user()->can('visits.export'))
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Patient records</h3>
                    <ul class="mt-3 grid gap-3">
                        @can('history.view')<x-action-tile :href="route('history.show', $patient)" title="Patient history timeline" description="Earlier visits and events for this patient." />@endcan
                        @can('visits.export')<x-action-tile :href="route('visits.pdf', [$patient, $visit])" title="Download internal visit summary PDF" description="Confidential summary file, not an official form." />@endcan
                    </ul>
                </div>
            @endif
        </div>
    </section>
</x-layout>
