<x-layout title="Visit details">
    <a href="{{ route('visits.index', ['from' => $visit->visit_date->toDateString(), 'to' => $visit->visit_date->toDateString()]) }}" class="font-semibold text-emerald-800 underline">Back to patient visits</a>
    <h1 class="mt-5 text-3xl font-semibold">Visit details</h1>
    <p class="mt-2 text-slate-600">Read-only administrative visit record.</p>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $patient->full_name }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $patient->patient_number }}</p>
        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
            <div><dt class="text-sm text-slate-500">Visit number</dt><dd class="font-medium">{{ $visit->visit_number }}</dd></div>
            <div><dt class="text-sm text-slate-500">Visit date</dt><dd>{{ $visit->visit_date->format('M j, Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">Requested service</dt><dd>{{ $visit->service?->name ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Queue reference</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Status</dt><dd class="font-semibold">{{ $visit->status }}</dd></div>
            <div><dt class="text-sm text-slate-500">Visit registered by</dt><dd>{{ $creator?->name ?? 'Not recorded' }}</dd></div>
            <div><dt class="text-sm text-slate-500">Registered at (Asia/Manila)</dt><dd>{{ $visit->created_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'Not recorded' }}</dd></div>
        </dl>
    </section>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Completion details</h2>
        @if($visit->status === 'COMPLETED')
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Completed by</dt><dd>{{ $visit->completedBy?->name ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Completed at (Asia/Manila)</dt><dd>{{ $visit->completed_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') ?? 'Not recorded' }}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="text-sm text-slate-500">Completion remarks</dt><dd class="mt-2 whitespace-pre-wrap break-words">{{ $visit->completion_remarks ?: 'No completion remarks recorded.' }}</dd></div>
            </dl>
        @else
            <p class="mt-4 text-slate-600">This visit has not been marked completed. No completion details are recorded.</p>
        @endif
    </section>
</x-layout>
