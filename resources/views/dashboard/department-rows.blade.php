@foreach($visits as $visit)
    @php($rowUrl = App\Support\VisitRowLink::for($visit, auth()->user(), $sectionType))
    <tr @if($rowUrl) data-clickable-row @endif class="border-t border-slate-100">
        <td class="p-3">@if($rowUrl)<a data-row-link class="font-semibold text-emerald-800 underline" href="{{ $rowUrl }}">{{ $visit->visit_number }}</a>@else<span class="font-semibold">{{ $visit->visit_number }}</span>@endif<span class="block text-slate-500">{{ $visit->visit_date->format('M j, Y') }}</span></td>
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
    </tr>
@endforeach
