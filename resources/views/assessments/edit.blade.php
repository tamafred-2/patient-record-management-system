<x-layout title="Record vital signs">
    @php
        $record = $visit->vitalSign;
        $canEdit = $visit->status === 'OPEN' && auth()->user()->can($record ? 'vitals.update' : 'vitals.create');
        $fields = ['systolic_bp' => ['Systolic BP (mmHg)', '1'], 'diastolic_bp' => ['Diastolic BP (mmHg)', '1'], 'temperature' => ['Temperature (Celsius)', '0.01'], 'pulse_rate' => ['Pulse rate (bpm)', '1'], 'height_cm' => ['Height (cm)', '0.01'], 'weight_kg' => ['Weight (kg)', '0.01'], 'respiratory_rate' => ['Respiratory rate (cpm)', '1'], 'oxygen_saturation' => ['O2 saturation (%)', '0.01']];
    @endphp
    <a href="{{ route('assessments.index', ['date' => $visit->visit_date->toDateString()]) }}" class="text-emerald-800 underline">Back to assessments</a>
    <h1 class="mt-5 text-3xl font-semibold">Vital signs</h1>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <p class="mt-2">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }}</p>
        <p class="mt-1 text-sm text-slate-600">Queue reference: {{ $visit->queue_reference ?? 'Not recorded' }} &middot; {{ $visit->status }}</p>
    </section>
    @if($errors->any())
        <x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            @error('lock_version')<a href="{{ route('assessments.edit', [$patient, $visit]) }}" class="font-semibold underline">Reload latest measurements</a>@enderror
        </x-alert>
    @endif
    <form method="POST" action="{{ route('assessments.save', [$patient, $visit]) }}" class="rounded-xl border border-slate-200 bg-white p-6">
        @csrf @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
        <p class="mb-6 text-sm text-slate-600">Record available measurements. Enter at least one measurement; blood pressure requires both values. Leave measurements you have not taken blank.</p>
        <fieldset @disabled(!$canEdit)>
            <legend class="sr-only">Vital sign measurements</legend>
            <div class="grid gap-6 sm:grid-cols-2">
                @foreach($fields as $field => [$label, $step])
                    <div>
                        <label for="{{ $field }}" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                        <input type="number" id="{{ $field }}" name="{{ $field }}" step="{{ $step }}" min="{{ $field === 'oxygen_saturation' ? 0 : $step }}" value="{{ old($field, $record?->$field) }}" @if($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif class="w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-50">
                        @error($field)<p id="{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </fieldset>
        @if($record)<p class="mt-6 text-sm text-slate-500">First recorded {{ $record->recorded_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} &middot; Last updated {{ $record->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila)</p>@endif
        @if($canEdit)<div class="mt-6"><x-button>Save vital signs</x-button></div>@else<p class="mt-6 text-sm text-slate-600">These measurements are read-only.</p>@endif
    </form>
</x-layout>
