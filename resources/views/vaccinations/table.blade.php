<x-scroll-queue :paginator="$visits" id="visits" label="Visits, scroll to load more">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Vaccination visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / status</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Vaccines recorded</th><th scope="col" class="p-3">Action</th></tr></thead>
        <tbody>@foreach($visits as $visit)
            <tr class="border-t border-slate-100"><td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p>{{ $visit->patient->patient_number }}</p></td><td class="p-3">{{ $visit->visit_number }}<p>{{ $visit->status }}</p></td><td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td><td class="p-3">{{ $visit->vaccination_records_count }}</td><td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('vaccinations.show', [$visit->patient, $visit]) }}">Open vaccination record</a></td></tr>
        @endforeach</tbody>
    </table>
</x-scroll-queue>
