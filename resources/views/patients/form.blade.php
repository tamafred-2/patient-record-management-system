<x-layout :title="$patient->exists ? 'Edit patient profile' : 'Register patient'">
    <a href="{{ $patient->exists ? route('patients.show', $patient) : route('patients.index') }}" class="text-sm text-emerald-800 underline">← {{ $patient->exists ? 'Patient profile' : 'Search patients' }}</a>
    <h1 class="mt-5 text-3xl font-semibold">{{ $patient->exists ? 'Edit patient profile' : 'Register patient' }}</h1>
    @if($patient->exists)<p class="mt-2 font-semibold text-emerald-800">{{ $patient->patient_number }} · {{ $patient->full_name }}</p>@endif
    <p class="mt-3 text-slate-600">First and last names are required. Leave unknown optional details blank.</p>
    @if($errors->any())
        <div role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
            <p class="font-semibold">Please review the following:</p>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="POST" action="{{ $patient->exists ? route('patients.update', $patient) : route('patients.store') }}" class="mt-8 max-w-4xl space-y-8 rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        @csrf
        @if($patient->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $patient->lock_version) }}">
        @endif
        @if($duplicates->isNotEmpty())
            <section aria-labelledby="duplicates-heading" class="rounded-lg border border-amber-300 bg-amber-50 p-5">
                <h2 id="duplicates-heading" class="font-semibold">Possible duplicate profiles — review before saving</h2>
                <p class="mt-2 text-sm">These patients have the same first and last names. Compare their details. Up to 10 matches are shown; search the registry for more.</p>
                <ul class="my-4 space-y-2">
                    @foreach($duplicates as $match)
                        <li><a href="{{ route('patients.show', $match) }}" target="_blank" rel="noopener" class="text-emerald-900 underline">{{ $match->patient_number }} · {{ $match->full_name }}</a> — {{ $match->birth_date?->format('M j, Y') ?? 'Birth date not recorded' }} · {{ $match->barangay ?? 'Barangay not recorded' }} (opens in new tab)</li>
                    @endforeach
                </ul>
                <label class="flex items-start gap-3 text-sm font-semibold"><input type="checkbox" name="confirm_distinct" value="1" required class="mt-1 h-4 w-4">I reviewed the matches and confirm this is a different person.</label>
            </section>
        @endif
        <fieldset><legend class="mb-5 text-lg font-semibold">Patient identity</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="first_name" label="First name (required)" :value="$patient->first_name" maxlength="100" required />
                <x-input name="last_name" label="Last name (required)" :value="$patient->last_name" maxlength="100" required />
                <x-input name="middle_name" label="Middle name (optional)" :value="$patient->middle_name" maxlength="100" />
                <x-input name="suffix" label="Suffix (optional)" :value="$patient->suffix" maxlength="30" />
                <x-input name="birth_date" label="Date of birth (optional)" type="date" :value="$patient->birth_date?->format('Y-m-d')" :max="now()->format('Y-m-d')" />
                <x-input name="sex" label="Sex (optional)" :value="$patient->sex" maxlength="50" />
                <x-input name="civil_status" label="Civil status (optional)" :value="$patient->civil_status" maxlength="50" />
            </div>
        </fieldset>
        <fieldset><legend class="mb-5 text-lg font-semibold">Address and contact</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="barangay" label="Barangay (optional)" :value="$patient->barangay" maxlength="150" />
                <x-input name="municipality" label="Municipality (optional)" :value="$patient->municipality" maxlength="150" />
                <x-input name="province" label="Province (optional)" :value="$patient->province" maxlength="150" />
                <x-input name="contact_number" label="Contact number (optional)" type="tel" :value="$patient->contact_number" maxlength="40" />
            </div>
        </fieldset>
        <fieldset><legend class="mb-5 text-lg font-semibold">Emergency contact</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="emergency_contact_name" label="Emergency contact name (optional)" :value="$patient->emergency_contact_name" maxlength="255" />
                <x-input name="emergency_contact_number" label="Emergency contact number (optional)" type="tel" :value="$patient->emergency_contact_number" maxlength="40" />
            </div>
        </fieldset>
        <x-button>{{ $patient->exists ? 'Save profile' : 'Register patient' }}</x-button>
    </form>
</x-layout>
