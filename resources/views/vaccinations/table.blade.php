<div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Vaccination visits</caption>
        <thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="p-4">Patient</th><th scope="col" class="p-4">Visit / status</th><th scope="col" class="p-4">Queue reference</th><th scope="col" class="p-4">Vaccines recorded</th><th scope="col" class="p-4">Action</th></tr></thead>
        <tbody>@forelse($visits as $visit)
            <tr class="border-t border-slate-100"><td class="p-4"><strong>{{ $visit->patient->full_name }}</strong><p>{{ $visit->patient->patient_number }}</p></td><td class="p-4">{{ $visit->visit_number }}<p>{{ $visit->status }}</p></td><td class="p-4">{{ $visit->queue_reference ?? 'Not recorded' }}</td><td class="p-4">{{ $visit->vaccination_records_count }}</td><td class="p-4"><a class="font-semibold text-emerald-800 underline" href="{{ route('vaccinations.show', [$visit->patient, $visit]) }}">Open vaccination record</a></td></tr>
        @empty<tr><td colspan="5" class="p-6 text-slate-500">No matching vaccination visits for this date.</td></tr>@endforelse</tbody>
    </table>
</div>
