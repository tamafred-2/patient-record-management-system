            @forelse($visits as $visit)
                <tr class="border-t border-slate-100">
                    <td class="p-3">@can('view', $visit)<a class="font-semibold text-emerald-800 underline" href="{{ route('patients.visits.show', [$visit->patient_id, $visit]) }}">{{ $visit->visit_number }}</a>@else<span class="font-semibold">{{ $visit->visit_number }}</span>@endcan<span class="block text-slate-500">{{ $visit->visit_date->format('M j, Y') }}</span></td>
                    <td class="p-3">{{ $patient->full_name ?? $visit->patient->full_name }}<span class="block text-xs text-slate-500">{{ $patient->patient_number ?? $visit->patient->patient_number }}</span></td>
                    <td class="p-3">{{ $visit->service->name }}</td><td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td><td class="p-3">{{ $visit->status }}</td>
                    @if(($dashboardActions ?? false) || ($monitorActions ?? false))
                        <td class="p-3">
                            @if($monitorActions ?? false)
                                @can('visits.monitor')<a class="font-semibold text-emerald-800 underline" href="{{ route('visits.monitor', [$visit->patient_id, $visit]) }}">View details</a>@endcan
                            @else
                            @can('consultations.view')<a class="font-semibold text-emerald-800 underline" href="{{ route('consultations.show', [$visit->patient, $visit]) }}">Review visit</a>
                            @elsecan('eligibility.view')<a class="font-semibold text-emerald-800 underline" href="{{ route('eligibility.edit', [$visit->patient, $visit]) }}">Open confirmation</a>@endcan
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ (($dashboardActions ?? false) || ($monitorActions ?? false)) ? 6 : 5 }}" class="p-6 text-slate-500">No matching visits.</td></tr>
            @endforelse
