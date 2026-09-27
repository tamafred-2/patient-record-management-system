<x-layout title="Visit summary">
    @php
        $vitals = $visit->vitalSign;
        $confirmation = $visit->eligibilityCheck;
        $fields = ['systolic_bp' => ['Systolic BP', 'mmHg'], 'diastolic_bp' => ['Diastolic BP', 'mmHg'], 'temperature' => ['Temperature', 'Celsius'], 'pulse_rate' => ['Pulse rate', 'bpm'], 'height_cm' => ['Height', 'cm'], 'weight_kg' => ['Weight', 'kg'], 'respiratory_rate' => ['Respiratory rate', 'cpm'], 'oxygen_saturation' => ['O2 saturation', '%']];
    @endphp
    <a href="{{ route('consultations.index', ['date' => $visit->visit_date->toDateString()]) }}" class="text-emerald-800 underline">Back to doctor visits</a>
    <h1 class="mt-5 text-3xl font-semibold">Visit summary</h1>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Visit</dt><dd>{{ $visit->visit_number }}</dd></div>
            <div><dt class="text-sm text-slate-500">Visit date</dt><dd>{{ $visit->visit_date->format('M j, Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">Requested service</dt><dd>{{ $visit->service->name }}</dd></div>
            <div><dt class="text-sm text-slate-500">Existing queue reference</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Visit status</dt><dd>{{ $visit->status }}</dd></div>
        </dl>
    </section>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Vital signs</h2>
        @if(!$vitals)<p class="mt-3 text-slate-600">Vital signs have not been recorded for this visit.</p>@endif
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($fields as $field => [$label, $unit])
                <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $vitals?->$field !== null ? $vitals->$field.' '.$unit : 'Not recorded' }}</dd></div>
            @endforeach
        </dl>
        @if($vitals)<p class="mt-5 text-sm text-slate-500">Last updated {{ $vitals->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila). Recorded values are shown without clinical interpretation.</p>@endif
    </section>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">PhilHealth confirmation</h2>
        <p class="mt-3 font-medium">{{ $confirmation?->philhealth_confirmed === true ? 'With record' : ($confirmation?->philhealth_confirmed === false ? 'No record' : 'Not checked') }}</p>
        <p class="mt-2 text-sm text-slate-600">Confirmation records whether the patient record was found in the existing system. It does not determine coverage or free services.</p>
        @if($confirmation?->remarks)<p class="mt-4 whitespace-pre-wrap break-words text-sm">{{ $confirmation->remarks }}</p>@endif
        @if($confirmation)<p class="mt-4 text-sm text-slate-500">Last updated {{ $confirmation->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila)</p>@endif
    </section>
    <a href="{{ route('itr.edit', [$patient, $visit]) }}" class="mt-6 inline-block rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white">Open Digital ITR</a>
    @can('prescriptions.view')
        <a href="{{ route('prescriptions.show', [$patient, $visit]) }}" class="mt-6 inline-block rounded-lg border border-emerald-800 px-5 py-3 font-semibold text-emerald-800">Open prescription</a>
    @endcan
    @can('history.view')<a class="mt-6 mr-4 inline-block font-semibold text-emerald-800 underline" href="{{ route('history.show',$patient) }}">Patient history timeline</a>@endcan
    @can('dispositions.view')<a class="mt-6 mr-4 inline-block font-semibold text-emerald-800 underline" href="{{ route('dispositions.show',[$patient,$visit]) }}">Referrals / certificate requests</a>@endcan
    @can('visits.export')<a class="mt-6 inline-block font-semibold text-emerald-800 underline" href="{{ route('visits.pdf',[$patient,$visit]) }}">Download internal visit summary PDF</a>@endcan
    @can('complete', $visit)
        <a class="mt-6 inline-block rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white" href="{{ route('visits.completion', [$patient, $visit]) }}">{{ $visit->status === 'OPEN' ? 'Review and complete visit' : 'View completion details' }}</a>
    @endcan
</x-layout>
