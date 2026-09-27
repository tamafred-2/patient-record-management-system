<x-layout title="Patient profile">
    <a href="{{ route('patients.index') }}" class="text-sm text-emerald-800 underline">← Patients</a>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
        <div><p class="font-semibold text-emerald-800">{{ $patient->patient_number }}</p><h1 class="mt-2 text-3xl font-semibold">{{ $patient->full_name }}</h1></div>
        @can('update', $patient)<a href="{{ route('patients.edit', $patient) }}" class="rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white">Edit profile</a>@endcan
    </div>
    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold">Patient information</h2>
        <dl class="mt-5 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-sm text-slate-500">Date of birth</dt><dd class="mt-1">{{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
            @foreach(['sex' => 'Sex', 'civil_status' => 'Civil status', 'contact_number' => 'Contact number', 'barangay' => 'Barangay', 'municipality' => 'Municipality', 'province' => 'Province', 'emergency_contact_name' => 'Emergency contact', 'emergency_contact_number' => 'Emergency contact number'] as $field => $label)
                <div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words">{{ $patient->$field ?? 'Not recorded' }}</dd></div>
            @endforeach
        </dl>
    </section>
    @if($visits !== null)
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            <div class="mb-5 flex items-center justify-between gap-3"><h2 class="text-lg font-semibold">Visit history</h2>
                @can('create', [App\Models\Visit::class, $patient])<a href="{{ route('patients.visits.create', $patient) }}" class="rounded-lg bg-emerald-800 px-4 py-2 font-semibold text-white">Start visit</a>@endcan
            </div>
            @include('visits.table')
        </section>
    @endif
    @can('history.view')<a class="mt-6 mr-4 inline-block font-semibold text-emerald-800 underline" href="{{ route('history.show',$patient) }}">Patient history timeline</a>@endcan
</x-layout>
