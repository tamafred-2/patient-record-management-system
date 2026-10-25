@php($reassessmentErrors = collect(['reassessment', 'reason', 'resolution_note', 'assessment_version'])->flatMap(fn ($key) => $errors->get($key)))
<x-dialog reference="reassessmentDialog" labelledby="reassessment-title">
    <div class="sticky top-0 flex items-start justify-between gap-4 border-b border-slate-100 bg-white px-6 py-4">
        <h2 id="reassessment-title" class="text-xl font-semibold">Reassessment requests</h2>
        <button type="button" x-on:click="$refs.reassessmentDialog.close()" aria-label="Close reassessment requests" class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-emerald-700">
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <div class="px-6 pb-6 pt-2">
        <p class="mt-2 text-sm text-slate-600">Use clinical judgment to decide whether another assessment is needed. There is no automatic expiry period. A pending request does not replace the recorded values.</p>
        @if($reassessmentErrors->isNotEmpty())<x-alert type="error" class="mt-4">@foreach($reassessmentErrors as $error)<p>{{ $error }}</p>@endforeach</x-alert>@endif
        @if($pending = $visit->pendingAssessmentRequest)
            <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-5">
                <h3 class="font-semibold">Reassessment requested — waiting for nurse</h3>
                <p class="mt-2 text-sm">{{ $pending->requester->name }} · {{ $pending->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                <p class="mt-3 whitespace-pre-wrap break-words">{{ $pending->reason }}</p>
                @can('assessments.request')
                    @if($visit->isOpen())
                        <details class="mt-4"><summary class="cursor-pointer font-semibold">Withdraw this request</summary>
                            <form method="POST" action="{{ route('reassessments.cancel', [$patient, $visit, $pending]) }}" class="mt-3 space-y-3">
                                @csrf
                                <label for="resolution_note" class="block text-sm font-medium">Reason for withdrawal</label>
                                <textarea id="resolution_note" name="resolution_note" required maxlength="1000" rows="2" class="w-full rounded-lg border border-slate-300 p-3">{{ old('resolution_note') }}</textarea>
                                <x-button>Withdraw reassessment request</x-button>
                            </form>
                        </details>
                    @endif
                @endcan
            </div>
        @elseif($visit->isOpen() && $visit->vitalSign)
            @can('assessments.request')
                <form method="POST" action="{{ route('reassessments.store', [$patient, $visit]) }}" class="mt-5 space-y-3">
                    @csrf
                    <input type="hidden" name="assessment_version" value="{{ $visit->vitalSign->lock_version }}">
                    <label for="reassessment_reason" class="block font-semibold">Reason for requesting a new assessment</label>
                    <textarea id="reassessment_reason" name="reason" required maxlength="2000" rows="3" class="w-full rounded-lg border border-slate-300 p-3">{{ old('reason') }}</textarea>
                    <x-button>Request latest nurse assessment</x-button>
                </form>
            @endcan
        @elseif(! $visit->vitalSign)
            <p class="mt-4 text-sm text-slate-500">A reassessment can be requested after the nurse saves an assessment.</p>
        @else
            <p class="mt-4 text-sm text-slate-500">This visit is completed, so no new reassessment can be requested.</p>
        @endif
        @if($assessmentRequests->isNotEmpty())
            <details class="mt-5"><summary class="cursor-pointer font-semibold">Recent reassessment requests</summary>
                <ul class="mt-3 space-y-4">@foreach($assessmentRequests as $entry)<li class="rounded-lg border border-slate-200 p-4 text-sm">
                    <strong>{{ $entry->status === 'CANCELLED' ? 'Withdrawn' : ucfirst(strtolower($entry->status)) }}</strong> · {{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} · {{ $entry->requester->name }}
                    <p class="mt-2 whitespace-pre-wrap break-words">{{ $entry->reason }}</p>
                    @if($entry->resolved_at)<p class="mt-2 text-slate-500">{{ $entry->resolver?->name }} · {{ $entry->resolved_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}@if($entry->completed_version) · Assessment version {{ $entry->completed_version }}@endif</p>@endif
                    @if($entry->resolution_note)<p class="mt-2 whitespace-pre-wrap break-words">{{ $entry->resolution_note }}</p>@endif
                </li>@endforeach</ul>
                <p class="mt-2 text-xs text-slate-500">The latest 10 requests are shown.</p>
            </details>
        @endif
    </div>
</x-dialog>
