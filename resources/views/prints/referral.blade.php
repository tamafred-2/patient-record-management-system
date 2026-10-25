<x-print-sheet :title="$title" :patient="$patient" :visit="$visit" :back="url()->previous()" :urgent="$record->type === 'EMERGENCY_REFERRAL'">
    @php
        $vitals = $visit->vitalSign;
        $diagnoses = $visit->treatmentRecord?->diagnoses ?? [];
        $measurements = array_filter([
            'BP' => $vitals?->systolic_bp && $vitals?->diastolic_bp ? $vitals->systolic_bp.'/'.$vitals->diastolic_bp.' mmHg' : null,
            'Temp' => $vitals?->temperature ? $vitals->temperature.' °C' : null,
            'PR' => $vitals?->pulse_rate ? $vitals->pulse_rate.' bpm' : null,
            'RR' => $vitals?->respiratory_rate ? $vitals->respiratory_rate.' cpm' : null,
            'O2 sat' => $vitals?->oxygen_saturation ? $vitals->oxygen_saturation.' %' : null,
            'Wt' => $vitals?->weight_kg ? $vitals->weight_kg.' kg' : null,
        ]);
    @endphp
    <dl>
        <div><dt>{{ $record->type === 'ADVISED_HIGHER_FACILITY' ? 'Advised facility' : 'Referred to' }}</dt><dd>{{ $record->facility_name ?: 'Not specified' }}</dd></div>
        <div><dt>Specialty / service</dt><dd>{{ $record->specialty ?: 'Not specified' }}</dd></div>
    </dl>
    <h2>Reason</h2>
    <p class="text">{{ $record->reason }}</p>
    @if($measurements)
        <h2>Vital signs at this visit</h2>
        <p>{{ collect($measurements)->map(fn ($value, $label) => $label.' '.$value)->join(' · ') }}</p>
    @endif
    @if($diagnoses)
        <h2>Diagnosis</h2>
        <p class="text">{{ implode('; ', $diagnoses) }}</p>
    @endif
    @if($record->remarks)
        <h2>Remarks</h2>
        <p class="text">{{ $record->remarks }}</p>
    @endif
    <dl>
        <div><dt>Recorded by</dt><dd>{{ $record->creator?->name ?? 'Not recorded' }}</dd></div>
        <div><dt>Date</dt><dd>{{ $record->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
    </dl>
    <div class="signatures">
        <div></div>
        <div class="line">{{ $record->creator?->name }}<br>Signature · License No. ____________</div>
    </div>
</x-print-sheet>
