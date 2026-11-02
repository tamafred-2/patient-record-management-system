@php
    // One visit in the patient file. $sections (from HistoryController) decides which department records this viewer may see.
    $time = fn ($value) => $value?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Not recorded';
    $vitals = $visit->vitalSign;
    $prescription = $visit->prescription;
    $events = collect([['at' => $visit->created_at, 'label' => 'Visit registered']]);
    if ($clinical) {
        foreach (['vitalSign' => 'Vital signs recorded', 'eligibilityCheck' => 'PhilHealth confirmation recorded', 'treatmentRecord' => 'ITR recorded'] as $relation => $label) {
            if ($visit->$relation && ($relation !== 'treatmentRecord' || $sections['itr'])) {
                $events->push(['at' => $visit->$relation->created_at, 'label' => $label]);
            }
        }
        if ($prescription && $sections['pharmacy']) {
            $events->push(['at' => $prescription->created_at, 'label' => 'Prescription drafted']);
            if ($prescription->prescribed_at) {
                $events->push(['at' => $prescription->prescribed_at, 'label' => 'Prescription issued']);
            }
            foreach ($prescription->dispensings as $release) {
                $events->push(['at' => $release->dispensed_at, 'label' => $release->items->contains(fn ($line) => $line->quantity_dispensed > 0) ? 'Medicine release recorded' : 'Pharmacy outcome recorded (not released here)']);
            }
        }
        if ($sections['laboratory']) {
            foreach ($visit->laboratoryRecords as $entry) {
                $events->push(['at' => $entry->created_at, 'label' => 'Laboratory service recorded']);
            }
        }
        if ($sections['supporting']) {
            foreach ($visit->dispositionRecords as $entry) {
                $events->push(['at' => $entry->created_at, 'label' => App\Models\DispositionRecord::TYPES[$entry->type]]);
            }
        }
    }
    $heading = 'mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500';
@endphp
<section class="rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="visit-{{ $visit->id }}-title">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="visit-{{ $visit->id }}-title" class="text-lg font-semibold">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</h2>
        <a href="{{ route('print-center.patient', [$patient, 'visit' => $visit->id]) }}#visit-{{ $visit->id }}" class="text-sm font-semibold text-emerald-800 underline">Printouts</a>
    </div>
    <p class="mt-2 text-sm">{{ $visit->service->name }} &middot; {{ $visit->status }} &middot; Queue {{ $visit->queue_reference ?? 'not recorded' }}<x-visit-priority :visit="$visit" class="ml-2 inline-block" /></p>

    @if($clinical)
        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            @if($sections['vitals'])
                <div>
                    <h3 class="{{ $heading }}">Triage / vital signs</h3>
                    @if($vitals)
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <dt class="text-slate-500">Blood pressure</dt><dd>{{ $vitals->systolic_bp && $vitals->diastolic_bp ? $vitals->systolic_bp.'/'.$vitals->diastolic_bp.' mmHg' : 'Not recorded' }}</dd>
                            @foreach(['temperature' => ['Temperature', '°C'], 'pulse_rate' => ['Pulse', 'bpm'], 'respiratory_rate' => ['Respiratory rate', 'cpm'], 'oxygen_saturation' => ['O2 saturation', '%'], 'height_cm' => ['Height', 'cm'], 'weight_kg' => ['Weight', 'kg']] as $field => [$label, $unit])
                                <dt class="text-slate-500">{{ $label }}</dt><dd>{{ $vitals->$field !== null ? $vitals->$field.' '.$unit : 'Not recorded' }}</dd>
                            @endforeach
                        </dl>
                        @if($vitals->assessment_summary)<p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $vitals->assessment_summary }}</p>@endif
                        <p class="mt-1 text-xs text-slate-500">Recorded by {{ $vitals->recorder?->name ?? 'Not recorded' }} &middot; {{ $time($vitals->recorded_at) }}</p>
                    @else
                        <p class="text-sm text-slate-500">No vital signs recorded.</p>
                    @endif
                </div>
            @endif
            @if($sections['itr'])
                <div>
                    <h3 class="{{ $heading }}">Doctor's record (ITR)</h3>
                    @if($itr = $visit->treatmentRecord)
                        @if($itr->diagnoses)<p class="text-sm"><strong>Diagnoses:</strong> {{ implode(', ', $itr->diagnoses) }}</p>@endif
                        @foreach(['assessment' => 'Assessment', 'planning' => 'Planning', 'remarks' => 'Remarks'] as $field => $label)
                            @if($itr->$field)<p class="mt-1 whitespace-pre-wrap break-words text-sm"><strong>{{ $label }}:</strong> {{ $itr->$field }}</p>@endif
                        @endforeach
                    @else
                        <p class="text-sm text-slate-500">No ITR recorded.</p>
                    @endif
                    @if($sections['eligibility'])<p class="mt-2 text-sm"><strong>PhilHealth:</strong> {{ $visit->eligibilityCheck?->philhealth_confirmed === true ? 'With record' : ($visit->eligibilityCheck?->philhealth_confirmed === false ? 'No record' : 'Not checked') }}</p>@endif
                </div>
            @endif
            @if($sections['laboratory'])
                <div>
                    <h3 class="{{ $heading }}">Laboratory</h3>
                    @forelse($visit->laboratoryRecords as $entry)
                        <div class="mb-3 rounded-lg border border-slate-100 p-3 text-sm">
                            <p class="font-semibold">{{ $entry->test_name }} &middot; {{ App\Models\LaboratoryRecord::STATUSES[$entry->availability_status] }}</p>
                            @if($entry->isOrdered())<p class="text-xs text-slate-500">{{ $entry->orderedByNurse() ? 'Ordered by nurse' : 'Ordered by doctor' }} {{ $entry->requester?->name }} &middot; {{ $time($entry->created_at) }}</p>@endif
                            @if($sections['lab_results'] && $entry->result_rows)
                                <table class="mt-2 w-full text-xs"><tbody>@foreach($entry->result_rows as $row)<tr class="border-t border-slate-100"><td class="py-1 pr-2">{{ $row['measurement'] ?? '' }}</td><td class="py-1 pr-2 font-semibold">{{ $row['value'] ?? '' }} {{ $row['unit'] ?? '' }}</td><td class="py-1 pr-2 text-slate-500">{{ $row['reference_range'] ?? '' }}</td><td class="py-1 font-semibold text-red-800">{{ $row['flag'] ?? '' }}</td></tr>@endforeach</tbody></table>
                            @elseif($sections['lab_results'] && filled($entry->result))
                                <p class="mt-1 whitespace-pre-wrap break-words">{{ $entry->result }}</p>
                            @endif
                            @if($entry->external_advice)<p class="mt-1 whitespace-pre-wrap break-words text-slate-700">{{ $entry->external_advice }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No laboratory records.</p>
                    @endforelse
                </div>
            @endif
            @if($sections['pharmacy'])
                <div>
                    <h3 class="{{ $heading }}">Prescription and medicines given</h3>
                    @if($prescription)
                        @php
                            $lines = $prescription->status === App\Models\Prescription::ISSUED ? App\Support\DispensingQuantities::forPrescription($prescription) : [];
                        @endphp
                        <p class="text-sm"><strong>{{ $prescription->prescription_number }}</strong> &middot; {{ $prescription->status === App\Models\Prescription::ISSUED ? 'Issued '.$time($prescription->prescribed_at) : 'Draft (not sent to Pharmacy)' }}</p>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach($prescription->items as $item)
                                <li class="rounded-lg border border-slate-100 p-3">
                                    <p class="font-semibold">{{ $item->medicine_name }} {{ $item->strength }} &middot; {{ App\Support\DispensingQuantities::display((string) $item->quantity_prescribed) }}</p>
                                    <p class="text-slate-600">{{ $item->dosage }}, {{ $item->frequency }}@if($item->duration), {{ $item->duration }}@endif</p>
                                    @if(isset($lines[$item->id]))
                                        <p class="mt-1 text-xs text-slate-600">Released {{ App\Support\DispensingQuantities::display($lines[$item->id]['released']) }}@foreach($lines[$item->id]['outcomes'] as $outcome => $amount) &middot; {{ App\Models\DispensingItem::OUTCOMES[$outcome] }} {{ App\Support\DispensingQuantities::display($amount) }}@endforeach &middot; Remaining {{ App\Support\DispensingQuantities::display($lines[$item->id]['remaining']) }}</p>
                                        @foreach($prescription->dispensings->whereNull('voided_at') as $release)
                                            @foreach($release->items->where('prescription_item_id', $item->id)->whereNotNull('released_as') as $line)<p class="text-xs text-slate-600">Given as {{ $line->released_as }}</p>@endforeach
                                        @endforeach
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500">No prescription.</p>
                    @endif
                </div>
            @endif
            @if($sections['supporting'] && ($visit->dispositionRecords->isNotEmpty() || $visit->medicalCertificates->isNotEmpty()))
                <div>
                    <h3 class="{{ $heading }}">Referrals and certificates</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach($visit->dispositionRecords as $entry)<li><strong>{{ App\Models\DispositionRecord::TYPES[$entry->type] }}:</strong> {{ $entry->reason }}@if($entry->facility_name) &middot; {{ $entry->facility_name }}@endif</li>@endforeach
                        @foreach($visit->medicalCertificates as $certificate)<li><strong>Medical certificate #{{ $certificate->id }}</strong> &middot; {{ $time($certificate->created_at) }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @if($sections['midwife'] && ($visit->midwifeCareRecords->isNotEmpty() || $visit->vaccinationRecords->isNotEmpty()))
                <div>
                    <h3 class="{{ $heading }}">Midwife care and vaccination</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach($visit->midwifeCareRecords as $care)<li><strong>{{ App\Models\MidwifeCareRecord::TYPES[$care->care_type] ?? $care->care_type }}:</strong> {{ $care->purpose }}</li>@endforeach
                        @foreach($visit->vaccinationRecords as $vaccination)<li><strong>{{ $vaccination->vaccine_name }}</strong> &middot; {{ $vaccination->dose }} &middot; {{ $vaccination->administered_on->format('M j, Y') }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </div>
    @else
        <p class="mt-3 text-sm text-slate-500">Clinical details are visible to clinical staff only.</p>
    @endif

    <details class="mt-5"><summary class="cursor-pointer text-sm font-semibold text-emerald-800">Timeline</summary>
        <ol class="mt-3 space-y-3 border-l-2 border-emerald-100 pl-4">@foreach($events->sortBy('at') as $event)<li><time class="text-xs text-slate-500">{{ $time($event['at']) }}</time><p class="text-sm">{{ $event['label'] }}</p></li>@endforeach</ol>
    </details>
    @php
        $user = auth()->user();
        $open = match (true) {
            $user->can('consultations.view') => route('consultations.show', [$patient, $visit]),
            $user->can('visits.view') => route('patients.visits.show', [$patient, $visit]),
            $user->can('laboratory.view') && $visit->laboratoryRecords->isNotEmpty() => route('laboratory.show', [$patient, $visit]),
            $user->can('pharmacy.view') && $prescription?->status === App\Models\Prescription::ISSUED => route('pharmacy.show', [$patient, $visit]),
            $user->can('vitals.view') => route('assessments.edit', [$patient, $visit]),
            default => null,
        };
    @endphp
    @if($open)<a class="mt-4 inline-block font-semibold text-emerald-800 underline" href="{{ $open }}">Open visit</a>@endif
</section>
