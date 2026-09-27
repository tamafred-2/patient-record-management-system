<x-layout title="Vaccination record">
    @php($canEdit = $visit->status === 'OPEN' && auth()->user()->can('vaccinations.record'))
    <a href="{{ route('vaccinations.index', ['date' => $visit->visit_date->toDateString()]) }}" class="text-emerald-800 underline">Back to vaccination visits</a>
    <h1 class="mt-5 text-3xl font-semibold">Vaccination record</h1>
    <section class="my-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-2">{{ $patient->patient_number }} &middot; Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }}</p>
        <p class="mt-2 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }} &middot; {{ $visit->status }}</p>
    </section>
    @if($errors->any())<x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<a class="underline" href="{{ $record ? route('vaccinations.edit', [$patient, $visit, $record]) : route('vaccinations.show', [$patient, $visit]) }}">Reload current record</a></x-alert>@endif
    @if($canEdit)
        <form method="POST" action="{{ $record ? route('vaccinations.update', [$patient, $visit, $record]) : route('vaccinations.store', [$patient, $visit]) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
            @csrf @if($record) @method('PUT') @endif
            <h2 class="text-xl font-semibold">{{ $record ? 'Edit administered vaccine' : 'Record administered vaccine' }}</h2>
            <p class="text-sm text-slate-600">Record what was actually administered. Vaccine and dose entries are not recommendations. Use the RHU source record.</p>
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
            @unless($record)<input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Illuminate\Support\Str::uuid()) }}">@endunless
            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="vaccine_name" label="Vaccine name (required)" :value="$record?->vaccine_name" maxlength="200" required />
                <x-input name="dose" label="Dose as documented (required)" :value="$record?->dose" maxlength="100" required />
                <x-input name="administered_on" label="Date administered (visit date)" type="date" :value="$record?->administered_on?->toDateString() ?? $visit->visit_date->toDateString()" :min="$visit->visit_date->toDateString()" :max="$visit->visit_date->toDateString()" required />
                <x-input name="batch_number" label="Batch / lot number (optional)" :value="$record?->batch_number" maxlength="100" />
                <x-input name="administered_by" label="Administering staff name (required)" :value="$record?->administered_by ?? auth()->user()->name" maxlength="200" required />
                <x-input name="next_appointment" label="Next appointment (optional)" type="date" :value="$record?->next_appointment?->toDateString()" />
            </div>
            <p class="text-sm text-slate-500">Enter a return date only when arranged by staff. No vaccine schedule is calculated. The signed-in account is recorded separately as the person entering this record.</p>
            <div><label for="remarks" class="mb-2 block font-medium">Remarks (optional)</label><textarea id="remarks" name="remarks" rows="3" maxlength="1000" class="w-full rounded-lg border border-slate-300 p-3">{{ old('remarks', $record?->remarks) }}</textarea></div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I checked these details against the vaccine actually administered during this visit.</span></label>
            <div class="flex flex-wrap items-center gap-4"><x-button>Save vaccination record</x-button>@if($record)<a href="{{ route('vaccinations.show', [$patient, $visit]) }}" class="text-emerald-800 underline">Cancel editing</a>@endif</div>
        </form>
    @else<p class="text-slate-600">Vaccination records for this visit are read-only.</p>@endif
    <section class="mt-8 space-y-4">
        <h2 class="text-xl font-semibold">Patient vaccination history</h2>
        <p class="text-sm text-slate-500">Records entered in this application across this patient's visits. This is not a complete external immunization history.</p>
        @forelse($history as $entry)
            <article class="space-y-2 rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="font-semibold">{{ $entry->vaccine_name }} &middot; {{ $entry->dose }}</h3>
                <p class="text-sm">Administered {{ $entry->administered_on->format('M j, Y') }} by {{ $entry->administered_by }} &middot; Visit {{ $entry->visit->visit_number }}</p>
                <p class="text-sm">Batch / lot: {{ $entry->batch_number ?? 'Not recorded' }} &middot; Next appointment: {{ $entry->next_appointment?->format('M j, Y') ?? 'Not recorded' }}</p>
                @if($entry->remarks)<p class="whitespace-pre-wrap break-words text-sm">{{ $entry->remarks }}</p>@endif
                <p class="text-xs text-slate-500">Entered by {{ $entry->recorder?->name }} &middot; Last saved {{ $entry->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila)</p>
                @if($canEdit && $entry->visit_id === $visit->id)<a class="inline-block font-semibold text-emerald-800 underline" href="{{ route('vaccinations.edit', [$patient, $visit, $entry]) }}">Edit vaccination entry</a>@endif
            </article>
        @empty<p class="text-slate-500">No vaccinations recorded yet.</p>@endforelse
        {{ $history->links() }}
    </section>
</x-layout>
