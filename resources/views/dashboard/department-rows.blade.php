@foreach($visits as $visit)
    <tr class="border-t border-slate-100">
        <td class="p-3"><span class="font-semibold">{{ $visit->visit_number }}</span><span class="block text-slate-500">{{ $visit->visit_date->format('M j, Y') }}</span></td>
        <td class="p-3">{{ $visit->patient->full_name }}<span class="block text-xs text-slate-500">{{ $visit->patient->patient_number }}</span></td>
        <td class="p-3">
            @switch($sectionType)
                @case('pharmacy') {{ $visit->prescription->prescription_number }} @break
                @case('midwife') {{ $visit->midwife_care_records_count }} @break
                @case('vaccinations') {{ $visit->vaccination_records_count }} @break
                @default {{ $visit->service?->name ?? 'Former service' }}
            @endswitch
        </td>
        <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td>
        <td class="p-3">{{ $visit->status }}@if($sectionType === 'assessments' && $visit->pendingAssessmentRequest)<span class="block text-amber-800">Reassessment requested</span>@endif</td>
        <td class="p-3">
            @switch($sectionType)
                @case('assessments') <a class="font-semibold text-emerald-800 underline" href="{{ route('assessments.edit', [$visit->patient, $visit]) }}">Open assessment</a> @break
                @case('laboratory') <a class="font-semibold text-emerald-800 underline" href="{{ route('laboratory.show', [$visit->patient, $visit]) }}">Open lab records</a> @break
                @case('pharmacy') <a class="font-semibold text-emerald-800 underline" href="{{ route('pharmacy.show', [$visit->patient, $visit]) }}">Open dispensing</a> @break
                @case('midwife') <a class="font-semibold text-emerald-800 underline" href="{{ route('midwife-care.show', [$visit->patient, $visit]) }}">Open Midwife care record</a> @break
                @case('vaccinations') <a class="font-semibold text-emerald-800 underline" href="{{ route('vaccinations.show', [$visit->patient, $visit]) }}">Open vaccination record</a> @break
            @endswitch
        </td>
    </tr>
@endforeach
