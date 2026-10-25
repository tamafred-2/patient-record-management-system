<x-layout :title="$patient->exists ? 'Edit patient profile' : 'Register patient'">
    <x-back-link :href="$patient->exists ? route('patients.show', $patient) : route('patients.index')">Back to {{ $patient->exists ? 'patient profile' : 'patient search' }}</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">{{ $patient->exists ? 'Edit patient profile' : 'Register patient' }}</h1>
    @if($patient->exists)<p class="mt-2 font-semibold text-emerald-800">{{ $patient->patient_number }} · {{ $patient->full_name }}</p>@endif
    <p class="mt-3 text-slate-600">First name, last name, date of birth, sex and address are required. Leave unknown optional details blank.</p>
    @if($errors->any())
        <x-alert type="error" title="Please review the following" class="mt-5"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>
    @endif
    @if($exactDuplicate ?? false)
        @include('patients.exact-duplicate-modal', ['match' => $duplicates->first()])
    @endif
    <form method="POST" action="{{ $patient->exists ? route('patients.update', $patient) : route('patients.store') }}" class="mt-8 max-w-4xl space-y-8 rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        @csrf
        @if($patient->exists)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $patient->lock_version) }}">
        @endif
        @if($duplicates->isNotEmpty() && !($exactDuplicate ?? false))
            <section aria-labelledby="duplicates-heading" class="rounded-lg border border-amber-300 bg-amber-50 p-5">
                <h2 id="duplicates-heading" class="font-semibold">Possible duplicate profiles — review before saving</h2>
                <p class="mt-2 text-sm">These patients have the same first and last names. Compare their details. Up to 10 matches are shown; search the registry for more.</p>
                <ul class="my-4 space-y-2">
                    @foreach($duplicates as $match)
                        <li><a href="{{ route('patients.show', $match) }}" target="_blank" rel="noopener" class="text-emerald-900 underline">{{ $match->patient_number }} · {{ $match->full_name }}</a> — {{ $match->birth_date?->format('M j, Y') ?? 'Birth date not recorded' }} · {{ $match->barangay ?? 'Barangay not recorded' }} (opens in new tab)</li>
                    @endforeach
                </ul>
                @unless($exactDuplicate ?? false)
                    <label class="flex items-start gap-3 text-sm font-semibold"><input type="checkbox" name="confirm_distinct" value="1" required class="mt-1 h-4 w-4">I reviewed the matches and confirm this is a different person.</label>
                @endunless
            </section>
        @endif
        <fieldset><legend class="mb-5 text-lg font-semibold">Patient identity</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input no-numbers name="first_name" label="First name" :value="$patient->first_name" maxlength="100" required />
                <x-input no-numbers name="last_name" label="Last name" :value="$patient->last_name" maxlength="100" required />
                <x-input no-numbers name="middle_name" label="Middle name (optional)" :value="$patient->middle_name" maxlength="100" />
                <x-patient-select name="suffix" label="Suffix (optional)" :value="$patient->suffix" :options="['Jr.', 'Sr.', 'II', 'III', 'IV', 'V']" />
                <x-input name="birth_date" label="Date of birth" type="date" :value="$patient->birth_date?->format('Y-m-d')" :max="now()->format('Y-m-d')" required />
                <x-patient-select name="sex" label="Sex" :value="$patient->sex" :options="App\Models\Patient::SEX_OPTIONS" required />
                <x-patient-select name="civil_status" label="Civil status (optional)" :value="$patient->civil_status" :options="['Single', 'Married', 'Widowed', 'Separated', 'Divorced', 'Other']" />
            </div>
        </fieldset>
        <fieldset><legend class="mb-5 text-lg font-semibold">Address and contact</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="barangay" label="Barangay" :value="$patient->barangay" maxlength="150" required />
                <x-input name="municipality" label="Municipality" :value="$patient->municipality" maxlength="150" required />
                <x-input name="province" label="Province" :value="$patient->province" maxlength="150" required />
                <x-input contact-number name="contact_number" label="Contact number (optional)" type="tel" :value="$patient->contact_number" maxlength="40" />
            </div>
        </fieldset>
        <fieldset><legend class="mb-5 text-lg font-semibold">Emergency contact</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input no-numbers name="emergency_contact_name" label="Name (optional)" :value="$patient->emergency_contact_name" maxlength="255" />
                <x-input contact-number name="emergency_contact_number" label="Contact number (optional)" type="tel" :value="$patient->emergency_contact_number" maxlength="40" />
            </div>
        </fieldset>
        <fieldset>
            <legend class="mb-3 text-lg font-semibold">Guardian / contact person (optional)</legend>
            <p class="mb-4 text-sm text-slate-600">Optional. If added, provide a name and relationship.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><x-input no-numbers name="guardian_name" label="Guardian / contact name" :value="$patient->guardian_name" maxlength="255" /></div>
                <x-input no-numbers name="guardian_relationship" label="Relationship to patient" :value="$patient->guardian_relationship" maxlength="100" placeholder="For example: mother, father, sibling" />
                <div><x-input contact-number name="guardian_phone" label="Guardian contact number (optional)" type="tel" :value="$patient->guardian_phone" maxlength="40" /></div>
            </div>
        </fieldset>
        <x-button>{{ $patient->exists ? 'Save profile' : 'Register patient' }}</x-button>
    </form>
</x-layout>
