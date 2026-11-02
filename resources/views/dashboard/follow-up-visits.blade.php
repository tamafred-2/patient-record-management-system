    @if(($followUps ?? collect())->isNotEmpty() || ($splitVisits ?? false) || $followupSearch !== '')
        @php($followUpDay = now('Asia/Manila')->startOfDay())
        @php($followUpTones = ['Overdue' => 'border-amber-300 bg-amber-50 text-amber-900', 'Today' => 'border-emerald-300 bg-emerald-50 text-emerald-900', 'Upcoming' => 'border-slate-300 bg-slate-50 text-slate-700'])
        <section class="mt-6 min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="follow-ups-title" id="follow-ups">
            <div class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-3">
                <div class="flex shrink-0 items-center gap-3"><h2 id="follow-ups-title" class="text-lg font-semibold">Follow-up return visits</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Overdue or due in 7 days</span></div>
                <form method="GET" action="{{ route('dashboard') }}#follow-ups" role="search" aria-label="Follow-up return visits" class="dashboard-search flex min-w-0 flex-[1_1_18rem] flex-wrap items-center gap-2">
                    @if($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
                    @if($serviceId ?? null)<input type="hidden" name="service_id" value="{{ $serviceId }}">@endif
                    <div class="min-w-0 flex-1"><label for="followup-search" class="sr-only">Search follow-up return visits</label><input id="followup-search" type="search" name="followup_search" value="{{ $followupSearch }}" maxlength="100" placeholder="Name, patient number or queue" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700"></div>
                    <x-button>Search visits</x-button>
                </form>
            </div>
            @if($followUps instanceof Illuminate\Contracts\Pagination\CursorPaginator)
            <x-scroll-queue :paginator="$followUps" id="followups" label="Follow-up return visits, scroll to load more">
                <table data-server-filtered class="dashboard-visits-table w-full table-fixed text-left text-sm">
                    <caption class="sr-only">Follow-up return visits</caption>
                    <colgroup><col style="width:23%"><col style="width:29%"><col style="width:27%"><col style="width:21%"></colgroup>
                    <thead class="sticky top-0 z-10 bg-slate-100"><tr><th scope="col" class="p-3">Return date</th><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Reason</th><th scope="col" class="p-3">Set by</th></tr></thead>
                    <tbody>
                        @forelse($followUps as $followUp)
                            @php($tag = $followUp->followUpStatus($followUpDay))
                            <tr data-clickable-row class="border-t border-slate-100 align-top">
                                <td class="p-3">{{ $followUp->follow_up_on->format('M j, Y') }}<br><span class="mt-1 inline-block rounded-full border px-2 py-0.5 text-xs font-bold {{ $followUpTones[$tag] }}">{{ $tag }}</span></td>
                                <td class="p-3">@can('viewAny', App\Models\Visit::class)
                                    <a data-row-link class="font-semibold text-emerald-800 underline" href="{{ route('patients.show', $followUp->patient) }}">{{ $followUp->patient->full_name }}</a>
                                    @else
                                    <a data-row-link class="font-semibold text-emerald-800 underline" href="{{ route('consultations.show', [$followUp->patient, $followUp]) }}">{{ $followUp->patient->full_name }}</a>
                                    @endcan
                                    <br><span class="text-slate-500">{{ $followUp->patient->patient_number }} &middot; from {{ $followUp->queue_reference ? 'queue '.$followUp->queue_reference : $followUp->visit_number }}</span></td>
                                <td class="p-3">{{ $followUp->follow_up_reason }}</td>
                                <td class="p-3">{{ $followUp->followUpSetter?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-slate-500">{{ $followupSearch !== '' ? 'No matching follow-up return visits.' : 'No follow-up return visits due within the next 7 days.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-scroll-queue>
            @endif
        </section>
    @endif
