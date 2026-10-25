<x-layout title="Individual Treatment Record">
    @php
        $record = $visit->treatmentRecord;
        $vitals = $visit->vitalSign;
        $canEdit = $visit->status === 'OPEN' && auth()->user()->can($record ? 'itr.update' : 'itr.create');
        $objectiveFields = ['temperature' => 'T (Celsius)', 'pulse_rate' => 'P (bpm)', 'respiratory_rate' => 'R (cpm)', 'systolic_bp' => 'Systolic BP (mmHg)', 'diastolic_bp' => 'Diastolic BP (mmHg)', 'oxygen_saturation' => 'O2 Sat (%)', 'height_cm' => 'H (cm)', 'weight_kg' => 'W (kg)'];
    @endphp
    <x-back-link :href="route('consultations.show', [$patient, $visit])">Back to visit summary</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">Individual Treatment Record</h1>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-2 text-sm">{{ $patient->patient_number }} &middot; {{ $visit->visit_number }} &middot; Date: {{ $visit->visit_date->format('M j, Y') }}</p>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Address</dt><dd>{{ implode(', ', array_filter([$patient->barangay, $patient->municipality, $patient->province])) ?: 'Not recorded' }}</dd></div>
            <div><dt class="text-slate-500">Birthday / Age at visit</dt><dd>{{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }} / {{ $patient->birth_date ? (int) $patient->birth_date->diffInYears($visit->visit_date) : 'Not recorded' }}</dd></div>
            <div><dt class="text-slate-500">Sex</dt><dd>{{ $patient->sex ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-slate-500">Cellphone No.</dt><dd>{{ $patient->contact_number ?? 'Not recorded' }}</dd></div>
        </dl>
    </section>
    @if($errors->any())
        <x-alert type="error" class="mb-6">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            @error('lock_version')<a class="underline" href="{{ route('itr.edit', [$patient, $visit]) }}">Reload latest ITR and vital signs</a>@enderror
        </x-alert>
    @endif
    <form method="POST" action="{{ route('itr.save', [$patient, $visit]) }}" class="space-y-6">
        @csrf @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
        <input type="hidden" name="vitals_version" value="{{ old('vitals_version', $vitals?->lock_version ?? 0) }}">
        <fieldset @disabled(!$canEdit) class="rounded-xl border border-slate-200 bg-white p-6">
            <legend class="sr-only">Subjective</legend>
            <h2 aria-hidden="true" class="text-xl font-semibold">Subjective</h2>
            <p class="mb-5 mt-2 text-sm text-slate-600">Select Yes or No when known. Leave unanswered questions as Not recorded.</p>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach(App\Models\TreatmentRecord::QUESTIONS as $field => $label)
                    @php($answer = old($field, $record?->$field === null ? '' : ($record->$field ? '1' : '0')))
                    <div><label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                        <select id="{{ $field }}" name="{{ $field }}" class="w-full rounded-lg border border-slate-300 p-3">
                            <option value="" @selected((string) $answer === '')>Not recorded</option>
                            <option value="1" @selected((string) $answer === '1')>Yes</option>
                            <option value="0" @selected((string) $answer === '0')>No</option>
                        </select>
                        @error($field)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div><label for="medicine_details" class="mb-2 block text-sm font-medium">Currently taking medicine: details (optional)</label><input id="medicine_details" name="medicine_details" maxlength="1000" value="{{ old('medicine_details', $record?->medicine_details) }}" class="w-full rounded-lg border border-slate-300 p-3">@error('medicine_details')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="lmp" class="mb-2 block text-sm font-medium">LMP (optional)</label><input type="date" id="lmp" name="lmp" max="{{ $visit->visit_date->toDateString() }}" value="{{ old('lmp', $record?->lmp?->toDateString()) }}" class="w-full rounded-lg border border-slate-300 p-3">@error('lmp')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            </div>
        </fieldset>
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold">Objective</h2>
            <p class="mt-2 text-sm text-slate-600">Current nurse measurements. Saving the ITR stores a copy of these values.</p>
            <p class="mt-2 text-sm text-slate-500">Current measurements last saved: {{ $vitals?->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'Not recorded' }}</p>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($objectiveFields as $field => $label)<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd>{{ $vitals?->$field ?? 'Not recorded' }}</dd></div>@endforeach
            </dl>
            @if($record)
                <details class="mt-5 rounded-lg bg-slate-50 p-4"><summary class="cursor-pointer font-medium">Objective values at last ITR save<span class="mt-1 block text-sm font-normal text-slate-500">ITR last saved: {{ $record->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'Not recorded' }}</span></summary>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($objectiveFields as $field => $label)<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd>{{ $record->objective_snapshot[$field] ?? 'Not recorded' }}</dd></div>@endforeach</dl>
                </details>
            @endif
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-6" x-data="{ diagnosisText: '', customDiagnosis: false, diagnosisError: '', addDiagnosis() {
            const selected = (this.customDiagnosis ? this.$refs.customDiagnosis.value : this.$refs.suggestedDiagnosis.value).trim().replace(/\s+/g, ' ');
            this.diagnosisError = '';
            if (!selected) { this.diagnosisError = 'Choose a diagnosis or enter a custom diagnosis first.'; return; }
            if (selected.length > 150) { this.diagnosisError = 'Use no more than 150 characters per diagnosis.'; return; }
            const field = this.$refs.diagnoses;
            const lines = field.value.split(/\r?\n/).map(v => v.trim()).filter(Boolean);
            const normalize = value => value.replace(/\s+/g, ' ').toLowerCase();
            if (selected &amp;&amp; !lines.some(v => normalize(v) === normalize(selected))) {
                if (lines.length >= 10) { this.diagnosisError = 'You can add up to 10 diagnoses.'; return; }
                field.value = [...lines, selected].join('\n');
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
            field.focus();
        } }" x-init="diagnosisText = $refs.diagnoses.value; @if($errors->has('diagnoses')) $nextTick(() => $refs.diagnosisEditor.showModal()) @endif">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="text-xl font-semibold">Diagnoses recorded for this visit</h2><p class="mt-2 text-sm text-slate-600" x-text="diagnosisText.trim() ? diagnosisText.split(/\r?\n/).filter(line => line.trim()).length + ' diagnosis label(s) in this ITR' : 'No diagnosis labels entered.'"></p></div>
                <x-button variant="secondary" type="button" x-on:click="$refs.diagnosisEditor.showModal()" aria-haspopup="dialog">{{ $canEdit ? 'Edit diagnoses' : 'View diagnoses' }}</x-button>
            </div>
            <x-dialog reference="diagnosisEditor" labelledby="diagnosis-editor-title">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5"><h2 id="diagnosis-editor-title" class="text-xl font-semibold">Diagnoses recorded for this visit</h2><button type="button" @click="$refs.diagnosisEditor.close()" aria-label="Close diagnoses" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100">&times;</button></div>
                <div class="p-6">
                <fieldset @disabled(!$canEdit)>
                <label for="diagnoses" class="mb-2 block text-xl font-semibold">Diagnosis list (optional)</label><p id="diagnoses-help" class="mb-3 text-sm text-slate-600">Record only diagnoses assigned by the doctor for this visit. These entries are counted in the diagnosis charts. Choose a previous entry or select Other to type a custom diagnosis, then click Add diagnosis. You can also edit the list directly, one diagnosis per line (up to 10, 150 characters each). Do not include patient details.</p>
            <div class="mb-4 flex flex-wrap items-end gap-3"><div class="min-w-0 flex-1"><label for="suggested-diagnosis" class="mb-2 block text-sm font-medium">Choose a diagnosis</label><select id="suggested-diagnosis" x-ref="suggestedDiagnosis" @change="customDiagnosis = $event.target.selectedOptions[0].dataset.custom === 'true'; diagnosisError = ''; if (customDiagnosis) $nextTick(() => $refs.customDiagnosis.focus())" class="w-full rounded-lg border border-slate-300 p-3"><option value="">Select a previous diagnosis or Other</option>@foreach($diagnosisSuggestions as $label)<option value="{{ $label }}">{{ $label }}</option>@endforeach<option value="" data-custom="true">Other - enter a custom diagnosis</option></select></div><x-button variant="secondary" type="button" x-on:click="addDiagnosis()">Add diagnosis</x-button></div>
            <p class="mb-3 text-xs text-slate-500">Reuse a label to avoid spelling variations. These are previous entries, not suggested diagnoses. Review before saving.</p>
            <div x-show="customDiagnosis" x-cloak class="mb-4"><label for="custom-diagnosis" class="mb-2 block text-sm font-medium">Custom diagnosis</label><input id="custom-diagnosis" x-ref="customDiagnosis" type="text" maxlength="150" placeholder="Enter the doctor-assigned diagnosis" @keydown.enter.prevent="addDiagnosis()" class="w-full rounded-lg border border-slate-300 p-3"><p class="mt-2 text-sm text-slate-500">Click Add diagnosis to include this name in the list below. The name you enter is used in analytics; it is not grouped under Other.</p></div>
            <p x-show="diagnosisError" x-text="diagnosisError" role="alert" class="mb-3 text-sm text-red-700"></p>
            <textarea x-ref="diagnoses" @input="diagnosisText = $event.target.value" id="diagnoses" name="diagnoses" rows="3" maxlength="1600" aria-describedby="diagnoses-help" class="w-full rounded-lg border border-slate-300 p-3">{{ old('diagnoses', implode("\n", $record?->diagnoses ?? [])) }}</textarea>@error('diagnoses')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </fieldset>
                <p class="mt-3 text-sm text-slate-500">{{ $canEdit ? 'Changes stay in this form. Select Save ITR after closing to save them.' : 'This ITR is read-only.' }}</p>
                <div class="mt-5 flex justify-end"><x-button type="button" x-on:click="$refs.diagnosisEditor.close()">Done</x-button></div>
                </div>
            </x-dialog>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6" x-data="{}" x-init="@if(!$errors->has('diagnoses') && ($errors->has('assessment') || $errors->has('planning') || $errors->has('remarks'))) $nextTick(() => $refs.itrNotes.showModal()) @endif">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div><h2 class="text-xl font-semibold">Assessment, Planning and Remarks</h2><p class="mt-2 text-sm text-slate-600">Review or enter the notes for this ITR.</p></div>
                <x-button variant="secondary" type="button" x-on:click="$refs.itrNotes.showModal()" aria-haspopup="dialog">{{ $canEdit ? 'Edit notes' : 'View notes' }}</x-button>
            </div>
            <x-dialog reference="itrNotes" labelledby="itr-notes-title">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-5">
                    <h2 id="itr-notes-title" class="text-xl font-semibold">Assessment, Planning and Remarks</h2>
                    <button type="button" @click="$refs.itrNotes.close()" aria-label="Close ITR notes" class="rounded-lg px-3 py-1 text-xl text-slate-500 hover:bg-slate-100">&times;</button>
                </div>
                <div class="p-6">
                    <fieldset @disabled(!$canEdit) class="space-y-6">
                        <legend class="sr-only">ITR notes</legend>
                        @foreach(['assessment' => 'Assessment', 'planning' => 'Planning', 'remarks' => 'Remarks'] as $field => $label)
                            <div>
                                <label for="{{ $field }}" class="mb-2 block text-xl font-semibold">{{ $label }}</label>
                                <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="5000" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror class="w-full rounded-lg border border-slate-300 p-3">{{ old($field, $record?->$field) }}</textarea>
                                @error($field)<p id="{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </fieldset>
                    <p class="mt-3 text-sm text-slate-500">{{ $canEdit ? 'Changes stay in this form. Select Save ITR after closing to save them.' : 'This ITR is read-only.' }}</p>
                    <div class="mt-5 flex justify-end"><x-button type="button" x-on:click="$refs.itrNotes.close()">Done</x-button></div>
                </div>
            </x-dialog>
        </section>
        @if($record)<p class="text-sm text-slate-500">Last saved {{ $record->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
        @if($canEdit)<x-button>Save ITR</x-button>@else<p class="text-sm text-slate-600">This ITR is read-only.</p>@endif
    </form>
</x-layout>
