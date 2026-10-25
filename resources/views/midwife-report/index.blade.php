<x-layout title="Midwife monthly report">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold">Midwife monthly report</h1>
            <p class="mt-2 text-slate-600">Counts for {{ $start->format('F Y') }}. Totals only. No patient names or care notes are included.</p>
        </div>
        <form method="GET" action="{{ route('midwife-report.index') }}" class="flex items-end gap-2">
            <div><label for="month" class="mb-1 block text-sm font-medium">Month</label><input id="month" type="month" name="month" value="{{ $month }}" max="{{ $current }}" class="rounded-lg border border-slate-300 p-2.5"></div>
            <x-button>Show</x-button>
        </form>
    </div>
    <a href="{{ route('midwife-report.print', ['month' => $month]) }}" class="mt-5 inline-block rounded-lg border border-emerald-800 px-4 py-2 font-semibold text-emerald-800">Print report</a>
    @include('midwife-report.tables')
</x-layout>
