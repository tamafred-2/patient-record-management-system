    <x-fixed-visit-table :empty="$visits->isEmpty()">
            <table class="w-full text-left text-sm">
            <caption class="sr-only">Issued prescriptions</caption>
            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Prescription</th><th scope="col" class="p-3">Action</th></tr></thead>
            <tbody>@foreach($visits as $visit)<tr class="border-t border-slate-100">
                <td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p>{{ $visit->patient->patient_number }}</p></td>
                <td class="p-3">{{ $visit->visit_number }} <span class="text-slate-500">{{ $visit->status }}</span></td>
                <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}</td>
                <td class="p-3">{{ $visit->prescription->prescription_number }}</td>
                <td class="p-3"><a href="{{ route('pharmacy.show', [$visit->patient, $visit]) }}" class="font-semibold text-emerald-800 underline">Open dispensing</a></td>
            </tr>@endforeach</tbody>
        </table>
    </x-fixed-visit-table>
