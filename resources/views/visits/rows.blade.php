            @forelse($visits as $visit)
                @php($rowUrl = App\Support\VisitRowLink::for($visit, auth()->user(), ($dashboardActions ?? false) ? App\Support\VisitRowLink::TODAY : null))
                <tr @if(request()->routeIs('dashboard', 'dashboard.visits')) data-clickable-row @endif class="border-t border-slate-100">
                    <td class="p-3">@if($rowUrl)<a data-row-link class="font-semibold text-emerald-800 underline" href="{{ $rowUrl }}">{{ $visit->visit_number }}</a>@else<span class="font-semibold">{{ $visit->visit_number }}</span>@endif<span class="block text-slate-500">{{ $visit->visit_date->format('M j, Y') }}</span></td>
                    <td class="p-3">{{ $patient->full_name ?? $visit->patient->full_name }}<span class="block text-xs text-slate-500">{{ $patient->patient_number ?? $visit->patient->patient_number }}</span></td>
                    <td class="p-3">{{ $visit->service->name }}</td><td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td>@unless($hideStatus ?? false)<td class="p-3">{{ $visit->status }}</td>@endunless
                    @if($monitorActions ?? false)
                        <td class="p-3">@can('visits.monitor')<a class="font-semibold text-emerald-800 underline" href="{{ route('visits.monitor', [$visit->patient_id, $visit]) }}">View details</a>@endcan</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ 4 + (($hideStatus ?? false) ? 0 : 1) + (($monitorActions ?? false) ? 1 : 0) }}" class="p-6 text-slate-500">No matching visits.</td></tr>
            @endforelse
