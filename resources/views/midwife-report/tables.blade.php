<div class="report-grid mt-6 grid gap-6 lg:grid-cols-2">
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold">Visits</h2>
        <table class="mt-3 w-full text-left text-sm">
            <tbody>
                @foreach($visits as $label => $count)
                    <tr class="border-t border-slate-100"><th scope="row" class="py-2 font-medium">{{ $label }}</th><td class="py-2 text-right tabular-nums">{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold">Midwife care by type</h2>
        <table class="mt-3 w-full text-left text-sm">
            <thead class="text-slate-500"><tr><th scope="col" class="py-2">Care type</th><th scope="col" class="py-2 text-right">Entries</th><th scope="col" class="py-2 text-right">Patients</th></tr></thead>
            <tbody>
                @foreach($careRows as $row)
                    <tr class="border-t border-slate-100"><th scope="row" class="py-2 font-medium">{{ $row['label'] }}</th><td class="py-2 text-right tabular-nums">{{ $row['entries'] }}</td><td class="py-2 text-right tabular-nums">{{ $row['patients'] }}</td></tr>
                @endforeach
                <tr class="border-t-2 border-slate-200 font-semibold"><th scope="row" class="py-2">Total</th><td class="py-2 text-right tabular-nums">{{ $careRows->sum('entries') }}</td><td class="py-2 text-right text-slate-400">—</td></tr>
            </tbody>
        </table>
        <p class="mt-2 text-xs text-slate-500">Prenatal entries with LMP recorded: {{ $prenatalWithLmp }}. A patient may appear under several care types.</p>
    </section>
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
        <h2 class="text-lg font-semibold">Vaccines administered</h2>
        @if($vaccines->isEmpty())
            <p class="mt-3 text-sm text-slate-500">No vaccines recorded for this month.</p>
        @else
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-slate-500"><tr><th scope="col" class="py-2">Vaccine</th><th scope="col" class="py-2">Dose</th><th scope="col" class="py-2 text-right">Doses given</th></tr></thead>
                <tbody>
                    @foreach($vaccines as $row)
                        <tr class="border-t border-slate-100"><td class="py-2">{{ $row->vaccine_name }}</td><td class="py-2">{{ $row->dose }}</td><td class="py-2 text-right tabular-nums">{{ $row->doses }}</td></tr>
                    @endforeach
                    <tr class="border-t-2 border-slate-200 font-semibold"><th scope="row" colspan="2" class="py-2">Total</th><td class="py-2 text-right tabular-nums">{{ $vaccines->sum('doses') }}</td></tr>
                </tbody>
            </table>
        @endif
    </section>
</div>
