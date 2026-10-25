<x-layout title="Record vital signs">
    @php
        $record = $visit->vitalSign;
        $canEdit = $visit->status === 'OPEN' && App\Support\VitalsAccess::canRecord(auth()->user(), $visit, (bool) $record);
        $asMidwife = App\Support\VitalsAccess::viaMidwife(auth()->user(), $visit);
        $fields = ['systolic_bp' => ['Systolic BP (mmHg)', '1'], 'diastolic_bp' => ['Diastolic BP (mmHg)', '1'], 'temperature' => ['Temperature (Celsius)', '0.01'], 'pulse_rate' => ['Pulse rate (bpm)', '1'], 'height_cm' => ['Height (cm)', '0.01'], 'weight_kg' => ['Weight (kg)', '0.01'], 'respiratory_rate' => ['Respiratory rate (cpm)', '1'], 'oxygen_saturation' => ['O2 saturation (%)', '0.01']];
    @endphp
    @if($asMidwife)
        <x-back-link :href="route(App\Support\VitalsAccess::midwifeReturnRoute($visit), [$patient, $visit])">Back to {{ $visit->service->seed_key === 'VACCINATION' ? 'vaccination record' : 'Midwife care record' }}</x-back-link>
    @else
        <x-back-link :href="route('assessments.index', ['date' => $visit->visit_date->toDateString()])">Back to assessments</x-back-link>
    @endif
    <h1 class="mt-5 text-3xl font-semibold">Vital signs and nurse assessment</h1>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center gap-3"><h2 class="text-lg font-semibold">{{ $patient->full_name }}</h2><x-visit-priority-inline :patient="$patient" :visit="$visit" /></div>
        <p class="mt-1 text-sm text-slate-600">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <p class="mt-2">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }}</p>
        <p class="mt-1 text-sm text-slate-600">Queue reference: {{ $visit->queue_reference ?? 'Not recorded' }} &middot; {{ $visit->status }}</p>
    </section>
    @if($errors->any())
        <x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            @error('lock_version')<a href="{{ route('assessments.edit', [$patient, $visit]) }}" class="font-semibold underline">Reload latest measurements</a>@enderror
            @error('reassessment')<a href="{{ route('assessments.edit', [$patient, $visit]) }}" class="font-semibold underline">Reload current assessment request</a>@enderror
        </x-alert>
    @endif
    <form method="POST" action="{{ route('assessments.save', [$patient, $visit]) }}" class="rounded-xl border border-slate-200 bg-white p-6">
        @csrf @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
        <p class="mb-6 text-sm text-slate-600">Record available measurements. Enter at least one measurement; blood pressure requires both values. Leave measurements you have not taken blank.</p>
        <fieldset @disabled(!$canEdit)>
            <legend class="sr-only">Vital sign measurements</legend>
            @if($pending = $visit->pendingAssessmentRequest)
                <section class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5" aria-labelledby="reassessment-title">
                    <h2 id="reassessment-title" class="font-semibold">Reassessment requested</h2>
                    <p class="mt-2 text-sm">{{ $pending->requester->name }} · {{ $pending->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                    <p class="mt-3 whitespace-pre-wrap break-words">{{ $pending->reason }}</p>
                    <input type="hidden" name="reassessment_request_id" value="{{ old('reassessment_request_id', $pending->id) }}">
                    <label class="mt-4 flex items-start gap-3 text-sm font-medium"><input type="checkbox" name="complete_reassessment" value="1" class="mt-1">I have reassessed this patient and updated the measurements and summary below. Mark this request completed when saving.</label>
                    <p class="mt-2 text-sm text-slate-600">Saving without this confirmation leaves the request pending.</p>
                </section>
            @endif
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach($fields as $field => [$label, $step])
                    <div>
                        <label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                        <input type="number" id="{{ $field }}" name="{{ $field }}" step="{{ $step }}" min="{{ $field === 'oxygen_saturation' ? 0 : $step }}" value="{{ old($field, $record?->$field) }}" @if($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif class="w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-50">
                        @error($field)<p id="{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
            <div class="mt-6">
                <label for="assessment_summary" class="mb-2 block font-semibold">Nurse assessment summary</label>
                <p id="assessment-summary-help" class="mb-3 text-sm text-slate-600">Describe the patient's reported concerns, your observations and findings, and any actions taken. Record what you actually assessed; do not infer a diagnosis from vital signs alone.</p>
                <textarea id="assessment_summary" name="assessment_summary" rows="6" maxlength="5000" required aria-describedby="assessment-summary-help" class="w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-50">{{ old('assessment_summary', $record?->assessment_summary) }}</textarea>
                @error('assessment_summary')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        </fieldset>
        @if($record)<p class="mt-6 text-sm text-slate-500">First recorded {{ $record->recorded_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} &middot; Last updated {{ $record->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>@endif
        @if($canEdit)
            <div class="mt-6 flex flex-wrap items-center gap-3" x-data="{ cleared: false }">
                <x-button>Save vital signs</x-button>
                <button type="button" @click="$el.closest('form').querySelectorAll('fieldset input[type=number], fieldset textarea').forEach((field) => field.value = ''); cleared = true; $el.closest('form').querySelector('#systolic_bp').focus()" class="rounded-lg border border-slate-300 px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700">Clear form</button>
                <p class="text-sm text-slate-600" aria-live="polite" x-text="cleared ? 'Form cleared. Nothing is changed until you save.{{ $record ? ' Reload the page to restore the saved values.' : '' }}' : ''"></p>
            </div>
        @else<p class="mt-6 text-sm text-slate-600">These measurements are read-only.</p>@endif
    </form>
    @can('followups.request')
        <a class="mt-6 inline-block rounded-lg border border-emerald-700 px-5 py-3 font-semibold text-emerald-800" href="{{ route('followups.show', [$patient, $visit]) }}">Authorized follow-up tests</a>
    @endcan
</x-layout>
