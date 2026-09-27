<x-layout title="Complete visit">
    <a href="{{ route('dashboard') }}" class="text-emerald-800 underline">Back to dashboard</a>
    <h1 class="mt-5 text-3xl font-semibold">Complete visit</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-2 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }} &middot; {{ $visit->service->name }} &middot; Queue: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    @if($errors->any())<x-alert type="error" class="mt-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<a class="underline" href="{{ route('visits.completion', [$patient, $visit]) }}">Reload current service status</a></x-alert>@endif
    <section class="mt-6 space-y-3 rounded-xl border border-slate-200 bg-white p-6">
        <p><strong>Status:</strong> <span>{{ $visit->status }}</span></p>
        <p><strong>Pharmacy:</strong> <span>{{ $review['pharmacy'] }}</span></p>
        <p><strong>Pending laboratory services:</strong> {{ $review['pendingLabs'] }}</p>
        @if($review['midwifeCareCount'] > 0 || $visit->service->seed_key === 'MIDWIFE_CARE')<p><strong>Midwife care entries:</strong> {{ $review['midwifeCareCount'] }}</p>@endif
        @if($review['vaccinationCount'] > 0 || $visit->service->seed_key === 'VACCINATION')<p><strong>Vaccination entries:</strong> {{ $review['vaccinationCount'] }}</p>@endif
        @if($visit->status === 'COMPLETED')
            <p>Completed by {{ $visit->completedBy?->name ?? 'Not recorded' }} &middot; {{ $visit->completed_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'Not recorded' }} (Asia/Manila)</p>
            @if($visit->completion_remarks)<p class="whitespace-pre-wrap break-words">{{ $visit->completion_remarks }}</p>@endif
        @elseif($visit->status === 'OPEN')
            @if($review['blocked'])
                <p class="text-amber-800">Complete the pending laboratory work or record external laboratory advice where applicable. Any prescription draft must be reviewed and issued by its doctor before this visit can close.</p>
            @else
                @if($review['unreleased'])<p class="text-amber-800">Check with Pharmacy before closing. Some medicines have not been released. Explain the actual reason, such as unavailable medicine or the patient declining the remaining supply. Completion does not mark these medicines as dispensed.</p>@endif
                <form method="POST" action="{{ route('visits.complete', [$patient, $visit]) }}" class="space-y-5 pt-3">
                    @csrf
                    <input type="hidden" name="review_version" value="{{ old('review_version', $review['version']) }}">
                    <div><label for="completion_remarks" class="mb-2 block font-medium">Completion remarks ({{ $review['unreleased'] ? 'required for unreleased medicines' : 'optional' }})</label><textarea id="completion_remarks" name="completion_remarks" rows="3" maxlength="1000" @required($review['unreleased']) class="w-full rounded-lg border border-slate-300 p-3">{{ old('completion_remarks') }}</textarea></div>
                    <label class="flex items-start gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I have checked that no further service is needed during this visit. Completing it prevents further clinical edits and medicine releases. There is no reopen action.</span></label>
                    <x-button>Complete visit</x-button>
                </form>
            @endif
        @endif
    </section>
</x-layout>
