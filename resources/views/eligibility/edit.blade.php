<x-layout title="PhilHealth confirmation">
    @php
        $record = $visit->eligibilityCheck;
        $canEdit = $visit->status === 'OPEN' && auth()->user()->can('eligibility.verify');

    @endphp
    <a href="{{ route('eligibility.index', ['date' => $visit->visit_date->toDateString()]) }}" class="text-emerald-800 underline">Back to PhilHealth confirmation</a>
    <h1 class="mt-5 text-3xl font-semibold">PhilHealth confirmation</h1>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <p class="mt-2">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }}</p>
        <p class="mt-1 text-sm text-slate-600">Queue reference: {{ $visit->queue_reference ?? 'Not recorded' }} &middot; {{ $visit->status }}</p>
    </section>
    @if($errors->any())
        <x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            @error('lock_version')<a href="{{ route('eligibility.edit', [$patient, $visit]) }}" class="font-semibold underline">Reload latest result</a>@enderror
        </x-alert>
    @endif
    <form method="POST" action="{{ route('eligibility.save', [$patient, $visit]) }}" class="rounded-xl border border-slate-200 bg-white p-6">
        @csrf @method('PUT')
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
        <p class="mb-6 text-sm text-slate-600">Check the existing PhilHealth system, then select whether the patient record was found.</p>
        <fieldset @disabled(!$canEdit) class="space-y-6">
            <legend class="sr-only">Confirmation details</legend>
            <div>
                <p class="mb-2 text-sm font-medium">Confirmation (required)</p>
                @foreach(['1' => 'With record — record found', '0' => 'No record — record not found'] as $value => $label)
                    <label for="philhealth_confirmed_{{ $value }}" class="mb-3 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <input type="radio" id="philhealth_confirmed_{{ $value }}" name="philhealth_confirmed" value="{{ $value }}" required @checked((string) old('philhealth_confirmed', $record?->philhealth_confirmed === null ? '' : (int) $record->philhealth_confirmed) === (string) $value) aria-describedby="confirmation-help" class="mt-1 h-5 w-5 border-slate-300 accent-emerald-700">
                        <span class="text-sm font-medium">{{ $label }}</span>
                    </label>
                @endforeach
                <p id="confirmation-help" class="mt-2 text-sm text-slate-500">Select the result after checking. This confirmation does not determine coverage or which services are free.</p>
                @error('philhealth_confirmed')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                @if($record && $record->status !== 'CHECKBOX')
                    <p class="mt-3 text-sm text-slate-600">Previous recorded result: {{ $record->status }}</p>
                    @if($record->philhealth_confirmed === null)<p class="mt-1 text-sm text-slate-600">Please review the existing system before confirming. The previous text has not been converted automatically.</p>@endif
                @endif
            </div>
            <div>
                <label for="remarks" class="mb-2 block text-sm font-medium">Remarks (optional)</label>
                <textarea id="remarks" name="remarks" rows="4" maxlength="1000" @error('remarks') aria-invalid="true" aria-describedby="remarks-error" @enderror class="w-full rounded-lg border border-slate-300 p-3 disabled:bg-slate-50">{{ old('remarks', $record?->remarks) }}</textarea>
                @error('remarks')<p id="remarks-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        </fieldset>
        @if($record)
            <dl class="mt-6 space-y-2 text-sm text-slate-500">
                <div><dt class="inline font-medium">First recorded:</dt><dd class="inline">{{ $record->verifier?->name }} &middot; {{ $record->verified_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</dd></div>
                <div><dt class="inline font-medium">Last updated:</dt><dd class="inline">{{ $record->editor?->name }} &middot; {{ $record->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila)</dd></div>
            </dl>
        @endif
        @if($canEdit)<div class="mt-6"><x-button>Save confirmation</x-button></div>@else<p class="mt-6 text-sm text-slate-600">This confirmation record is read-only.</p>@endif
    </form>
</x-layout>
