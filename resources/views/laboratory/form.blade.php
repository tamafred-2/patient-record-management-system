@php
    $record = $record ?? null;
    $canEdit = $visit->status === 'OPEN' && auth()->user()->can('laboratory.record');
@endphp
@if($errors->any())<x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    @if($errors->has('lock_version') || $errors->has('submission_token'))<a class="underline" href="{{ $record ? route('laboratory.edit', [$patient,$visit,$record]) : route('laboratory.show', [$patient,$visit]) }}">Reload latest records</a>@endif
</x-alert>@endif
<form method="POST" action="{{ $record ? route('laboratory.update', [$patient,$visit,$record]) : route('laboratory.store', [$patient,$visit]) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
    @csrf @if($record) @method('PUT') @endif
    <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
    @if(!$record)<input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Illuminate\Support\Str::uuid()) }}">@endif
    <h2 class="text-xl font-semibold">{{ $record ? 'Edit laboratory service' : 'Add laboratory service' }}</h2>
    <fieldset @disabled(!$canEdit) class="space-y-5">
        <legend class="sr-only">Laboratory service details</legend>
        <div><label for="test_name" class="mb-2 block text-sm font-medium">Requested test / service (required)</label><input id="test_name" name="test_name" maxlength="200" required value="{{ old('test_name',$record?->test_name) }}" class="w-full rounded-lg border border-slate-300 p-3">@error('test_name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        <div><label for="availability_status" class="mb-2 block text-sm font-medium">Service status (required)</label><select id="availability_status" name="availability_status" required class="w-full rounded-lg border border-slate-300 p-3">@foreach(App\Models\LaboratoryRecord::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('availability_status',$record?->availability_status ?? 'PENDING')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="external_advice" class="mb-2 block text-sm font-medium">External laboratory advice</label><textarea id="external_advice" name="external_advice" rows="3" maxlength="1000" aria-describedby="advice-help" class="w-full rounded-lg border border-slate-300 p-3">{{ old('external_advice',$record?->external_advice) }}</textarea><p id="advice-help" class="mt-1 text-xs text-slate-500">Required for External laboratory advised; leave blank for other statuses. Record the advice actually given.</p>@error('external_advice')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        <div><label for="notes" class="mb-2 block text-sm font-medium">Service notes (optional)</label><textarea id="notes" name="notes" rows="3" maxlength="1000" class="w-full rounded-lg border border-slate-300 p-3">{{ old('notes',$record?->notes) }}</textarea><p class="mt-1 text-xs text-slate-500">For service activity only. Laboratory result entry is not available yet.</p></div>
    </fieldset>
    @if($canEdit)<x-button>Save laboratory service</x-button>@else<p class="text-sm text-slate-600">This record is read-only.</p>@endif
</form>
