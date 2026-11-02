    @if($todayVisits !== null)
        <section id="today-visits" tabindex="-1" class="min-w-0 scroll-mt-6 mt-6 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="today-visits-title">
            <div class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-3">
                <div class="flex shrink-0 items-center gap-3"><h2 id="today-visits-title" class="text-lg font-semibold">{{ auth()->user()->can('consultations.view') ? "Today's doctor visits" : "Today's visits" }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $todayVisitsTotal }} {{ $todayVisitsTotal === 1 ? 'visit' : 'visits' }}</span></div>
                @include('dashboard.visit-search', ['primary' => true])
            </div>
            @include('visits.table', ['visits' => $todayVisits, 'hideStatus' => true, 'scrollAfterTen' => true, 'lazyVisits' => true, 'nextVisitsUrl' => $nextVisitsUrl, 'dashboardActions' => auth()->user()->can('consultations.view') || auth()->user()->can('eligibility.view')])
        </section>
    @endif
