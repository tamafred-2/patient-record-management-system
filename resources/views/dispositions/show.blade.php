<x-layout title="Supporting records">
    <a href="{{ route('consultations.show',[$patient,$visit]) }}" class="text-emerald-800 underline">Back to visit summary</a>
    <h1 class="mt-5 text-3xl font-semibold">Referrals and certificate requests</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }} &middot; {{ $visit->visit_number }}</p>
    <p class="mt-3 text-sm text-slate-600">Document an action already taken or a certificate request. This register does not generate a formal referral document or issue a medical certificate.</p>
    <section class="my-6 space-y-4">@forelse($records as $entry)
        <article class="rounded-xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">{{ App\Models\DispositionRecord::TYPES[$entry->type] }}</h2><p class="mt-2 whitespace-pre-wrap break-words">{{ $entry->reason }}</p>@if($entry->facility_name)<p class="mt-2">{{ $entry->facility_name }} &middot; {{ $entry->specialty }}</p>@endif @if($entry->remarks)<p class="mt-2 whitespace-pre-wrap break-words">{{ $entry->remarks }}</p>@endif<p class="mt-3 text-xs text-slate-500">Entry #{{ $entry->id }} &middot; {{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p></article>
    @empty<p class="text-slate-600">No supporting records yet.</p>@endforelse {{ $records->links() }}</section>
    @if($errors->any())<x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<a class="underline" href="{{ route('dispositions.show',[$patient,$visit]) }}">Reload records</a></x-alert>@endif
    @if($visit->status === 'OPEN' && auth()->user()->can('dispositions.record'))
        <form method="POST" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6" x-data="{ type: {{ Js::from(old('type', 'FORMAL_REFERRAL')) }} }">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token',(string)Illuminate\Support\Str::uuid()) }}">
            <div><label for="type" class="mb-2 block font-medium">Record type</label><select id="type" name="type" x-model="type" class="w-full rounded-lg border border-slate-300 p-3">@foreach(App\Models\DispositionRecord::TYPES as $key=>$label)<option value="{{ $key }}" @selected(old('type')===$key)>{{ $label }}</option>@endforeach</select></div>
            <p x-cloak x-show="type === 'CERTIFICATE_REQUEST'" class="text-sm text-slate-600">Enter the purpose of the certificate request and any remarks. This records the request; it does not issue a certificate.</p>
            <div><label for="reason" class="mb-2 block font-medium" x-text="type === 'CERTIFICATE_REQUEST' ? 'Certificate request purpose (required)' : (type === 'ADVISED_HIGHER_FACILITY' ? 'Reason for higher-facility advice (required)' : 'Referral reason (required)')">Reason / purpose (required)</label><textarea id="reason" name="reason" required maxlength="3000" rows="3" class="w-full rounded-lg border border-slate-300 p-3">{{ old('reason') }}</textarea></div>
            <fieldset x-cloak x-show="type !== 'CERTIFICATE_REQUEST'" :disabled="type === 'CERTIFICATE_REQUEST'" class="space-y-5">
                <legend class="sr-only">Referral or higher-facility advice details</legend>
                @foreach(['facility_name'=>'Receiving facility (optional)','specialty'=>'Specialty (optional)'] as $field=>$label)<div><label for="{{ $field }}" class="mb-2 block font-medium">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" maxlength="200" value="{{ old($field) }}" class="w-full rounded-lg border border-slate-300 p-3"></div>@endforeach
            </fieldset>
            <div><label for="remarks" class="mb-2 block font-medium">Remarks (optional)</label><textarea id="remarks" name="remarks" maxlength="3000" rows="3" class="w-full rounded-lg border border-slate-300 p-3">{{ old('remarks') }}</textarea></div>
            <label class="flex items-start gap-3 text-sm"><input name="confirm" type="checkbox" value="1" required class="mt-1">I confirm this entry accurately records the action or request. Entries are retained; corrections should refer to the earlier entry number.</label>
            <x-button><span x-text="type === 'CERTIFICATE_REQUEST' ? 'Save certificate request' : (type === 'ADVISED_HIGHER_FACILITY' ? 'Save higher-facility advice' : 'Save referral record')">Save record</span></x-button>
        </form>
    @endif
</x-layout>
