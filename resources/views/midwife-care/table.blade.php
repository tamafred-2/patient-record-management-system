<x-fixed-visit-table :empty="$visits->isEmpty()">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Midwife care visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / status</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Care entries</th><th scope="col" class="p-3">Action</th></tr></thead>
        <tbody>@foreach($visits as $visit)
            <tr class="border-t border-slate-100"><td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p>{{ $visit->patient->patient_number }}</p></td><td class="p-3">{{ $visit->visit_number }}<p>{{ $visit->status }}</p></td><td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}</td><td class="p-3">{{ $visit->midwife_care_records_count }}</td><td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('midwife-care.show', [$visit->patient, $visit]) }}">Open Midwife care record</a></td></tr>
        @endforeach</tbody>
    </table>
</x-fixed-visit-table>
