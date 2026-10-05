<x-layout title="Initial assessment">
    <h1 class="text-3xl font-semibold">Initial assessment</h1>
    <p class="mt-2 text-slate-600">Review visits and record vital signs.</p>
    <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" />
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <x-fixed-visit-table :empty="$visits->isEmpty()">
    <table class="w-full text-left text-sm">
            <caption class="sr-only">Visits for initial assessment</caption>
            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / service</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Vital signs</th><th scope="col" class="p-3">Action</th></tr></thead>
            <tbody>
                @foreach($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-3">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}</td>
                        <td class="p-3">{{ $visit->vitalSign ? 'Recorded' : 'Pending' }}</td>
                        <td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('assessments.edit', [$visit->patient, $visit]) }}">Open assessment</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-fixed-visit-table>
    <div class="mt-6">{{ $visits->links() }}</div>
</x-layout>
