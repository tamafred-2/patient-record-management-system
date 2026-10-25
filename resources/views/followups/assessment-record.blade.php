@php
    $vitals = $visit->vitalSign;
    $measurements = [
        'Blood pressure' => $vitals?->systolic_bp && $vitals?->diastolic_bp ? $vitals->systolic_bp.'/'.$vitals->diastolic_bp.' mmHg' : null,
        'Temperature' => $vitals?->temperature ? $vitals->temperature.' °C' : null,
        'Pulse rate' => $vitals?->pulse_rate ? $vitals->pulse_rate.' bpm' : null,
        'Respiratory rate' => $vitals?->respiratory_rate ? $vitals->respiratory_rate.' cpm' : null,
        'O2 saturation' => $vitals?->oxygen_saturation ? $vitals->oxygen_saturation.' %' : null,
        'Height' => $vitals?->height_cm ? $vitals->height_cm.' cm' : null,
        'Weight' => $vitals?->weight_kg ? $vitals->weight_kg.' kg' : null,
    ];
@endphp
<h2 id="assessment-record-title" class="text-xl font-semibold">{{ $heading ?? 'Nurse assessment' }}</h2>
@if($vitals)
    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
        @foreach($measurements as $label => $value)
            <div><dt class="text-xs text-slate-500">{{ $label }}</dt><dd class="font-semibold">{{ $value ?? 'Not taken' }}</dd></div>
        @endforeach
    </dl>
    <h3 class="mt-5 text-sm font-semibold text-slate-700">Assessment summary</h3>
    <p class="mt-1 whitespace-pre-wrap break-words">{{ $vitals->assessment_summary ?: 'No summary recorded.' }}</p>
    <p class="mt-4 text-sm text-slate-500">
        Recorded by {{ $vitals->recorder?->name ?? 'Not recorded' }} &middot; {{ $vitals->recorded_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}
        @if($vitals->updated_by !== $vitals->recorded_by || $vitals->lock_version > 1)<br>Last updated by {{ $vitals->updater?->name ?? 'Not recorded' }} &middot; {{ $vitals->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}@endif
    </p>
@else
    <p class="mt-3 text-slate-600">No assessment has been saved for this visit yet.</p>
@endif
