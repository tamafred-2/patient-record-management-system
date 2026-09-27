<x-layout title="Doctor visits">
    <h1 class="text-3xl font-semibold">Doctor visits</h1>
    <p class="mt-2 text-slate-600">Review existing visits and recorded measurements. This list includes all services. Dates follow Asia/Manila.</p>
    <x-visit-filters :search="$search" :date="$date" />
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Visits for doctor review</caption>
            <thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="p-4">Patient</th><th scope="col" class="p-4">Visit / service</th><th scope="col" class="p-4">Queue reference</th><th scope="col" class="p-4">Vital signs</th><th scope="col" class="p-4">Action</th></tr></thead>
            <tbody>
                @forelse($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-4"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-4">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-4">{{ $visit->queue_reference ?? 'Not recorded' }}</td>
                        <td class="p-4">{{ $visit->vitalSign ? 'Recorded' : 'Pending' }}</td>
                        <td class="p-4"><a class="font-semibold text-emerald-800 underline" href="{{ route('consultations.show', [$visit->patient, $visit]) }}">Review visit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">No matching visits for this date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $visits->links() }}</div>
</x-layout>
