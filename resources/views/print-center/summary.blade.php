<x-print-sheet title="Patient summary" :subtitle="$clinical ? 'Visit history with clinical details' : 'Visit history'" :patient="$patient" :back="url()->previous()" size="A4">
    @php
        $birth = $patient->birth_date?->format('M j, Y') ?? 'Not recorded';
    @endphp
    <dl>
        <div><dt>Birth date</dt><dd>{{ $birth }}</dd></div>
        <div><dt>Visits on record</dt><dd>{{ $total }}{{ $total > $visits->count() ? ' (latest '.$visits->count().' shown)' : '' }}</dd></div>
        @if($patient->guardian_name)<div><dt>Guardian</dt><dd>{{ $patient->guardian_name }}{{ $patient->guardian_relationship ? ' ('.$patient->guardian_relationship.')' : '' }}</dd></div>@endif
    </dl>
    <h2>Visits</h2>
    @forelse($visits as $visit)
        <section style="break-inside: avoid; border-bottom: 1px solid #ccd6d1; padding: 8px 0;">
            <p style="margin: 0;"><strong>{{ $visit->visit_date->format('M j, Y') }}</strong> · {{ $visit->visit_number }} · {{ $visit->service?->name ?? 'Former service' }} · {{ $visit->status }}@if($visit->priority !== 'REGULAR') · <strong>{{ $visit->priorityLabel() }}</strong>@endif</p>
            @if($clinical)
                @php
                    $vitals = $visit->vitalSign;
                    $measures = array_filter([
                        'BP' => $vitals?->systolic_bp && $vitals?->diastolic_bp ? $vitals->systolic_bp.'/'.$vitals->diastolic_bp : null,
                        'Temp' => $vitals?->temperature ? $vitals->temperature.' °C' : null,
                        'PR' => $vitals?->pulse_rate ? $vitals->pulse_rate : null,
                        'RR' => $vitals?->respiratory_rate ? $vitals->respiratory_rate : null,
                        'Wt' => $vitals?->weight_kg ? $vitals->weight_kg.' kg' : null,
                    ]);
                @endphp
                @if($measures)<p style="margin: 4px 0 0;">Vital signs: {{ collect($measures)->map(fn ($value, $label) => $label.' '.$value)->join(' · ') }}</p>@endif
                @if($visit->treatmentRecord?->diagnoses)<p style="margin: 4px 0 0;">Diagnosis: {{ implode('; ', $visit->treatmentRecord->diagnoses) }}</p>@endif
                @if($visit->prescription?->status === 'ISSUED')<p style="margin: 4px 0 0;">Medicines: {{ $visit->prescription->items->map(fn ($item) => trim($item->medicine_name.' '.$item->strength))->join(', ') }}</p>@endif
                @if($visit->laboratoryRecords->isNotEmpty())<p style="margin: 4px 0 0;">Laboratory: {{ $visit->laboratoryRecords->map(fn ($record) => $record->test_name.' ('.App\Models\LaboratoryRecord::STATUSES[$record->availability_status].')')->join(', ') }}</p>@endif
                @php($referrals = $visit->dispositionRecords->whereIn('type', ['FORMAL_REFERRAL', 'EMERGENCY_REFERRAL', 'ADVISED_HIGHER_FACILITY']))
                @if($referrals->isNotEmpty())<p style="margin: 4px 0 0;">Referral: {{ $referrals->map(fn ($record) => App\Models\DispositionRecord::TYPES[$record->type].($record->facility_name ? ' – '.$record->facility_name : ''))->join('; ') }}</p>@endif
                @if($visit->follow_up_on)<p style="margin: 4px 0 0;">Follow-up return: {{ $visit->follow_up_on->format('M j, Y') }}</p>@endif
            @endif
        </section>
    @empty
        <p>No visits recorded.</p>
    @endforelse
</x-print-sheet>
