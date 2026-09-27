<x-layout title="Dashboard">
    <h1 class="text-3xl font-semibold">Welcome, {{ auth()->user()->name }}</h1>
    <p class="mt-3 text-slate-600">{{ auth()->user()->getRoleNames()->join(' / ') }}</p>
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($cards as [$permission,$label,$route,$description])
            <a href="{{ route($route) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm hover:border-emerald-500 focus-visible:outline-2 focus-visible:outline-emerald-700"><h2 class="text-lg font-semibold text-emerald-900">{{ $label }}</h2><p class="mt-2 text-sm text-slate-600">{{ $description }}</p></a>
        @empty
            <p class="text-slate-600">No operational module is assigned yet. Contact your administrator for the confirmed responsibilities of your role.</p>
        @endforelse
    </div>
    @if($todayVisits !== null)
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="today-visits-title">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="today-visits-title" class="text-xl font-semibold">{{ auth()->user()->can('consultations.view') ? "Today's doctor visits" : "Today's visits" }}</h2><p class="mt-1 text-sm text-slate-500">{{ $today }} &middot; Asia/Manila &middot; {{ $todayVisits->count() }} visits</p></div>
                @can('consultations.view')
                <a href="{{ route('consultations.index', ['date' => $today]) }}" class="text-sm font-semibold text-emerald-800 underline underline-offset-4">Open doctor visits</a>
                @else
                <a href="{{ route('visits.index', ['from' => $today, 'to' => $today]) }}" class="text-sm font-semibold text-emerald-800 underline underline-offset-4">Open patient visits</a>
                @endcan
            </div>
            @include('visits.table', ['visits' => $todayVisits, 'scrollAfterTen' => true, 'dashboardActions' => auth()->user()->can('consultations.view') || auth()->user()->can('eligibility.view')])
        </section>
    @endif
    @if($todayPharmacy !== null)
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="today-pharmacy-title">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="today-pharmacy-title" class="text-xl font-semibold">Today's pharmacy</h2><p class="mt-1 text-sm text-slate-500">{{ $today }} &middot; Asia/Manila &middot; {{ $todayPharmacy->total() }} issued prescriptions for today's visits</p></div>
                <a href="{{ route('pharmacy.index', ['date' => $today]) }}" class="text-sm font-semibold text-emerald-800 underline underline-offset-4">Open pharmacy</a>
            </div>
            @include('pharmacy.table', ['visits' => $todayPharmacy])
            <div class="mt-4">{{ $todayPharmacy->links() }}</div>
        </section>
    @endif
    @if($todayMidwifeCare !== null)
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-semibold">Today's Midwife care visits</h2><p class="mt-1 text-sm text-slate-500">{{ $today }} &middot; Asia/Manila &middot; {{ $todayMidwifeCare->total() }} visits</p></div><a href="{{ route('midwife-care.index') }}" class="font-semibold text-emerald-800 underline">Open Midwife care</a></div>
            @include('midwife-care.table', ['visits' => $todayMidwifeCare])
            <div class="mt-4">{{ $todayMidwifeCare->links() }}</div>
        </section>
    @endif
    @if($todayVaccinations !== null)
        <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-semibold">Today's vaccination visits</h2><p class="mt-1 text-sm text-slate-500">{{ $today }} &middot; Asia/Manila &middot; {{ $todayVaccinations->total() }} visits</p></div><a href="{{ route('vaccinations.index') }}" class="font-semibold text-emerald-800 underline">Open vaccination</a></div>
            @include('vaccinations.table', ['visits' => $todayVaccinations])
            <div class="mt-4">{{ $todayVaccinations->links() }}</div>
        </section>
    @endif
</x-layout>
