<x-layout title="Dashboard">
    @php
        $roleDescriptions = [
            'System Admin' => 'Review system activity, reports and user access.',
            'Information Staff' => 'Register patients and organize patient visits.',
            'Nurse' => 'Review patient visits and record initial assessments.',
            'Doctor / Medical Officer' => 'Review consultations, treatment records and prescriptions.',
            'MedTech' => 'Track laboratory requests and record test results.',
            'Pharmacist' => 'Review prescriptions and record medicine releases.',
            'Midwife' => 'Review midwife care visits and immunization records.',
        ];
        $assignedRoles = auth()->user()->getRoleNames();
        $dashboardDescription = $assignedRoles->count() === 1
            ? ($roleDescriptions[$assignedRoles->first()] ?? 'Review your assigned services and daily work.')
            : 'Review your assigned services and daily work.';
    @endphp
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-3xl font-semibold">Dashboard</h1><p class="mt-2 text-sm text-slate-500">{{ $dashboardDescription }}</p></div>
        @if(auth()->user()->can('create', App\Models\Patient::class))
            <a href="{{ route('patients.create') }}" class="whitespace-nowrap rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Register patient</a>
        @elseif(auth()->user()->can('viewAny', App\Models\Visit::class) || auth()->user()->can('analytics.overview'))
            <a href="{{ route('visits.index', ['from' => $today, 'to' => $today]) }}" class="whitespace-nowrap rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-emerald-700">Patient Visits</a>
        @elseif(auth()->user()->can('consultations.view'))
            <a href="{{ route('consultations.index', ['date' => $today]) }}" class="whitespace-nowrap rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-emerald-700">Open doctor visits</a>
        @endif
    </div>
    @if(($followUps ?? collect())->isNotEmpty())
        @php($followUpDay = now('Asia/Manila')->startOfDay())
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-5" aria-labelledby="follow-ups-title" id="follow-ups">
            <h2 id="follow-ups-title" class="text-lg font-semibold">Follow-up return visits</h2>
            <p class="mt-1 text-sm text-slate-600">Due, overdue or within the next 7 days, for patients who have not returned yet.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-600"><tr><th scope="col" class="p-2">Return date</th><th scope="col" class="p-2">Patient</th><th scope="col" class="p-2">Reason</th><th scope="col" class="p-2">Set by</th><th scope="col" class="p-2"><span class="sr-only">Action</span></th></tr></thead>
                    <tbody>
                        @foreach($followUps as $followUp)
                            @php([$tag, $tone] = $followUp->follow_up_on->lt($followUpDay) ? ['Overdue', 'border-amber-300 bg-amber-50 text-amber-900'] : ($followUp->follow_up_on->isSameDay($followUpDay) ? ['Today', 'border-emerald-300 bg-emerald-50 text-emerald-900'] : ['Upcoming', 'border-slate-300 bg-slate-50 text-slate-700']))
                            <tr class="border-t border-slate-100 align-top">
                                <td class="p-2 whitespace-nowrap">{{ $followUp->follow_up_on->format('M j, Y') }} <span class="ml-1 rounded-full border px-2 py-0.5 text-xs font-bold {{ $tone }}">{{ $tag }}</span></td>
                                <td class="p-2"><strong>{{ $followUp->patient->full_name }}</strong><br><span class="text-slate-500">{{ $followUp->patient->patient_number }} &middot; from {{ $followUp->visit_number }}</span></td>
                                <td class="p-2">{{ $followUp->follow_up_reason }}</td>
                                <td class="p-2">{{ $followUp->followUpSetter?->name ?? '—' }}</td>
                                <td class="p-2 text-right">
                                    @can('viewAny', App\Models\Visit::class)
                                        <a class="font-semibold text-emerald-800 underline" href="{{ route('patients.show', $followUp->patient) }}">Open patient</a>
                                    @else
                                        <a class="font-semibold text-emerald-800 underline" href="{{ route('consultations.show', [$followUp->patient, $followUp]) }}">Open visit</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <section class="mt-8" aria-labelledby="dashboard-summary-title">
        <h2 id="dashboard-summary-title" class="sr-only">Today's summary</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($summaryCards as $card)
                @continue(in_array($card[0], ['Open today', 'Completed today'], true))
                @php([$label, $value, $description] = $card)
                <article class="relative cursor-pointer rounded-xl border border-slate-200 bg-white p-5 {{ isset($card[3]) ? 'transition-colors hover:border-emerald-600 hover:bg-emerald-50' : '' }}">@if(isset($card[3]))<a href="{{ $card[3] }}" class="absolute inset-0 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" aria-label="{{ $label }}: {{ number_format($value) }}. Open related visits"></a>@endif<div class="flex items-center justify-between gap-4"><p class="text-xs font-semibold text-slate-700">{{ $label }}</p><p class="text-3xl font-semibold tracking-tight tabular-nums text-emerald-900">{{ number_format($value) }}</p></div><p class="mt-3 text-[11px] leading-4 text-slate-500">{{ $description }}@if(isset($card[3])) <span aria-hidden="true">&rarr;</span>@endif</p></article>
            @empty
                <p class="text-slate-600">No summary is assigned to your account yet. Contact your administrator for the confirmed responsibilities of your role.</p>
            @endforelse
        </div>
    </section>
    @if($errors->any())<x-alert type="error" class="mt-5">{{ $errors->first() }}</x-alert>@endif
    @if($todayVisits !== null)
        <section id="today-visits" tabindex="-1" class="scroll-mt-6 mt-6 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="today-visits-title">
            <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-center gap-3"><h2 id="today-visits-title" class="text-lg font-semibold">{{ auth()->user()->can('consultations.view') ? "Today's doctor visits" : "Today's visits" }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $todayVisitsTotal }} {{ $todayVisitsTotal === 1 ? 'visit' : 'visits' }}</span></div>
                @include('dashboard.visit-search', ['primary' => true])
            </div>
            @include('visits.table', ['visits' => $todayVisits, 'scrollAfterTen' => true, 'lazyVisits' => true, 'nextVisitsUrl' => $nextVisitsUrl, 'dashboardActions' => auth()->user()->can('consultations.view') || auth()->user()->can('eligibility.view')])
        </section>
    @endif
    @foreach([
        'assessments' => ["Today's assessment visits", 'today-assessments', 'Service', 'assessments.index', 'Open assessments'],
        'laboratory' => ["Today's laboratory visits", 'today-laboratory', 'Service', 'laboratory.index', 'Open laboratory'],
        'pharmacy' => ["Today's pharmacy", 'today-pharmacy', 'Prescription', 'pharmacy.index', 'Open pharmacy'],
        'midwife' => ["Today's Midwife care visits", 'today-midwife', 'Care entries', 'midwife-care.index', 'Open Midwife care'],
        'vaccinations' => ["Today's vaccination visits", 'today-vaccinations', 'Vaccines recorded', 'vaccinations.index', 'Open vaccination'],
    ] as $sectionType => $config)
        @if(isset($departmentBatches[$sectionType]))
            <section id="{{ $config[1] }}" tabindex="-1" class="scroll-mt-6 mt-6 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="{{ $config[1] }}-title">
                <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-3"><h2 id="{{ $config[1] }}-title" class="text-lg font-semibold">{{ $config[0] }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ $departmentTotals[$sectionType] }} visits</span><a href="{{ route($config[3], ['date' => $today]) }}" class="text-sm text-emerald-800 underline">{{ $config[4] }}</a></div>
                    @include('dashboard.visit-search')
                </div>
                @include('visits.table', ['visits' => $departmentBatches[$sectionType], 'scrollAfterTen' => true, 'lazyVisits' => true, 'nextVisitsUrl' => $departmentNext[$sectionType], 'dashboardActions' => true, 'rowsView' => 'dashboard.department-rows', 'serviceHeading' => $config[2], 'scrollLabel' => $config[0].', scroll to load more', 'emptyTitle' => $sectionType === 'pharmacy' ? 'No issued prescriptions today' : 'No visits today'])
            </section>
        @endif
    @endforeach
</x-layout>
