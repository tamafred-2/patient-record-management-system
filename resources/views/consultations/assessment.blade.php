<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="nurse-summary-title">
    <h2 id="nurse-summary-title" class="text-xl font-semibold">Nurse assessment summary</h2>
    @can('followups.authorize')
        <a class="mt-3 inline-block font-semibold text-emerald-800 underline" href="{{ route('followups.show', [$patient, $visit]) }}">Order laboratory tests</a>
    @endcan
    <p class="mt-3 whitespace-pre-wrap break-words">{{ $visit->vitalSign?->assessment_summary ?: 'No assessment summary recorded.' }}</p>
    @if($visit->vitalSign)
        <p class="mt-3 text-sm text-slate-500">Last saved by {{ $visit->vitalSign->updater?->name ?? 'Not recorded' }} · {{ $visit->vitalSign->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
    @endif
    <p class="mt-3 text-sm text-slate-600">Use clinical judgment to decide whether another assessment is needed. There is no automatic expiry period. A pending request does not replace the recorded values.</p>
    @if($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($pending = $visit->pendingAssessmentRequest)
        <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-5">
            <h3 class="font-semibold">Reassessment requested — waiting for nurse</h3>
            <p class="mt-2 text-sm">{{ $pending->requester->name }} · {{ $pending->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
            <p class="mt-3 whitespace-pre-wrap break-words">{{ $pending->reason }}</p>
            @can('assessments.request')
                @if($visit->status === 'OPEN')
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
    @elseif($visit->status === 'OPEN' && $visit->vitalSign)
        @can('assessments.request')
            <form method="POST" action="{{ route('reassessments.store', [$patient, $visit]) }}" class="mt-5 space-y-3 border-t border-slate-100 pt-5">
                @csrf
                <input type="hidden" name="assessment_version" value="{{ $visit->vitalSign->lock_version }}">
                <label for="reassessment_reason" class="block font-semibold">Reason for requesting a new assessment</label>
                <textarea id="reassessment_reason" name="reason" required maxlength="2000" rows="3" class="w-full rounded-lg border border-slate-300 p-3">{{ old('reason') }}</textarea>
                <x-button>Request latest nurse assessment</x-button>
            </form>
        @endcan
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
</section>
