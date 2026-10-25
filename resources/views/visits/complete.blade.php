<x-layout title="Complete visit">
    <x-back-link href="{{ route('dashboard') }}">Back to dashboard</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">Complete visit</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-2 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    @if($errors->any())<x-alert type="error" class="mt-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<a class="underline" href="{{ route('visits.completion', [$patient, $visit]) }}">Reload current service status</a></x-alert>@endif
    <section class="mt-6 space-y-3 rounded-xl border border-slate-200 bg-white p-6">
        <p><strong>Status:</strong> <span>{{ $visit->status }}</span></p>
        <p><strong>Pharmacy:</strong> <span>{{ $review['pharmacy'] }}</span></p>
        <p><strong>Pending laboratory services:</strong> {{ $review['pendingLabs'] }}</p>
        <p><strong>Pending reassessment requests:</strong> {{ $review['pendingAssessments'] }}</p>
        @if($review['midwifeCareCount'] > 0 || $visit->service->seed_key === 'MIDWIFE_CARE')<p><strong>Midwife care entries:</strong> {{ $review['midwifeCareCount'] }}</p>@endif
        @if($review['vaccinationCount'] > 0 || $visit->service->seed_key === 'VACCINATION')<p><strong>Vaccination entries:</strong> {{ $review['vaccinationCount'] }}</p>@endif
        @if($visit->status === 'COMPLETED')
            <p>Completed by {{ $visit->completedBy?->name ?? 'Not recorded' }} &middot; {{ $visit->completed_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'Not recorded' }}</p>
            @if($visit->completion_remarks)<p class="whitespace-pre-wrap break-words">{{ $visit->completion_remarks }}</p>@endif
            <details class="pt-4">
                <summary class="cursor-pointer text-emerald-800 underline">Reopen for correction</summary>
                <form method="POST" action="{{ route('visits.reopen', [$patient, $visit]) }}" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ $review['version'] }}">
                    <label class="block">Reason for reopening<textarea name="reason" required maxlength="1000" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 p-3">{{ old('reason') }}</textarea></label>
                    <label class="flex items-start gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I confirm this visit needs correction. Authorized staff can edit it again, and it must be completed afterward. The previous completion is retained.</span></label>
                    <x-button>Reopen visit</x-button>
                </form>
            </details>
        @elseif($visit->status === 'OPEN')
            @if($review['blocked'])
                @if($review['pendingAssessments'])<p class="text-amber-800">The nurse must complete the requested reassessment, or a doctor must withdraw the request with a reason, before this visit can close.</p>@endif
                <p class="text-amber-800">Complete the pending laboratory work or record external laboratory advice where applicable. Any prescription draft must be reviewed and issued by its doctor before this visit can close.</p>
            @else
                @if($review['unreleased'])<p class="text-amber-800">Check with Pharmacy before closing. Some medicines have not been released. Explain the actual reason, such as unavailable medicine or the patient declining the remaining supply. Completion does not mark these medicines as dispensed.</p>@endif
                <form method="POST" action="{{ route('visits.complete', [$patient, $visit]) }}" class="space-y-5 pt-3">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ old('review_version', $review['version']) }}">
                    <div><label for="completion_remarks" class="mb-2 block font-medium">Completion remarks ({{ $review['unreleased'] ? 'required for unreleased medicines' : 'optional' }})</label><textarea id="completion_remarks" name="completion_remarks" rows="3" maxlength="1000" @required($review['unreleased']) class="w-full rounded-lg border border-slate-300 p-3">{{ old('completion_remarks') }}</textarea></div>
                    <label class="flex items-start gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I have checked that no further service is needed during this visit. Completing it prevents further clinical edits and medicine releases until authorized staff reopen it for correction.</span></label>
                    <x-button>Complete visit</x-button>
                </form>
            @endif
        @endif
    </section>
    @if($reopenings->isNotEmpty())
        <section class="mt-6 space-y-4"><h2 class="text-xl font-semibold">Reopening history</h2>
            @foreach($reopenings as $entry)
                <article class="rounded-xl border border-slate-200 bg-white p-5">
                    <p>Reopened by {{ $entry->reopenedBy?->name }} on {{ $entry->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                    <p class="mt-2 whitespace-pre-wrap">{{ $entry->reason }}</p>
                    <p class="mt-2 text-sm text-slate-600">Previous completion: {{ $entry->previous_completed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Not recorded' }}</p>
                    @if($entry->previous_completion_remarks)<p class="whitespace-pre-wrap text-sm">{{ $entry->previous_completion_remarks }}</p>@endif
                </article>
            @endforeach
        </section>
    @endif
</x-layout>
