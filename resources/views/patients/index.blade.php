<x-layout title="Patients">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-sm font-semibold text-emerald-800">Patient registry</p><h1 class="mt-2 text-3xl font-semibold">Find a patient</h1></div>
        @can('create', App\Models\Patient::class)
            <a href="{{ route('patients.create') }}" class="rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white">Register patient</a>
        @endcan
    </div>
    <form method="GET" action="{{ route('patients.index') }}" class="my-8 flex flex-wrap items-end gap-3">
        <div class="w-full sm:max-w-md"><x-input name="q" label="Patient number or name" :value="$search" maxlength="150" placeholder="Search before registering a new patient" /></div>
        <x-button>Search</x-button>
        @if($search !== '')<a href="{{ route('patients.index') }}" class="px-3 py-3 text-sm text-emerald-800 underline">Clear search</a>@endif
    </form>
    <p class="mb-3 text-sm text-slate-600">{{ $patients->total() }} {{ Str::plural('patient', $patients->total()) }} found</p>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Patient search results</caption>
            <thead class="bg-slate-100"><tr><th scope="col" class="p-4">Patient number</th><th scope="col" class="p-4">Name</th><th scope="col" class="p-4">Date of birth</th><th scope="col" class="p-4">Barangay</th></tr></thead>
            <tbody>
                @forelse($patients as $patient)
                    <tr class="border-t border-slate-100"><td class="p-4"><a href="{{ route('patients.show', $patient) }}" class="font-semibold text-emerald-800 underline">{{ $patient->patient_number }}</a></td><td class="p-4">{{ $patient->full_name }}</td><td class="p-4">{{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</td><td class="p-4">{{ $patient->barangay ?? 'Not recorded' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-slate-600">No matching patients. Check the spelling or search by patient number before registering.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $patients->links() }}</div>
</x-layout>
