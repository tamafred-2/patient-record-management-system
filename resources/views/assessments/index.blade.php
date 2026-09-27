<x-layout title="Initial assessment">
    <h1 class="text-3xl font-semibold">Initial assessment</h1>
    <p class="mt-2 text-slate-600">Review visits and record vital signs. Dates follow Asia/Manila.</p>
    <form method="GET" class="my-6 flex flex-wrap items-end gap-3">
        <div><label for="date" class="mb-2 block text-sm font-medium">Visit date</label><input id="date" type="date" name="date" value="{{ $date }}" required class="rounded-lg border border-slate-300 p-3"></div>
        <x-button>Filter by date</x-button>
    </form>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Visits for initial assessment</caption>
            <thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="p-4">Patient</th><th scope="col" class="p-4">Visit / service</th><th scope="col" class="p-4">Queue reference</th><th scope="col" class="p-4">Vital signs</th><th scope="col" class="p-4">Action</th></tr></thead>
            <tbody>
                @forelse($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-4"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-4">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-4">{{ $visit->queue_reference ?? 'Not recorded' }}</td>
                        <td class="p-4">{{ $visit->vitalSign ? 'Recorded' : 'Pending' }}</td>
                        <td class="p-4"><a class="font-semibold text-emerald-800 underline" href="{{ route('assessments.edit', [$visit->patient, $visit]) }}">Open assessment</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">No visits for this date.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $visits->links() }}</div>
</x-layout>
