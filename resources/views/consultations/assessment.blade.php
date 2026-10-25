<section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="nurse-summary-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="nurse-summary-title" class="text-xl font-semibold">Nurse assessment summary</h2>
        @can('followups.authorize')
            <x-button variant="secondary" :href="route('followups.show', [$patient, $visit])">Order laboratory tests</x-button>
        @endcan
    </div>
    <p class="mt-3 whitespace-pre-wrap break-words">{{ $visit->vitalSign?->assessment_summary ?: 'No assessment summary recorded.' }}</p>
    @if($visit->vitalSign)
        <p class="mt-3 text-sm text-slate-500">Last saved by {{ $visit->vitalSign->updater?->name ?? 'Not recorded' }} · {{ $visit->vitalSign->updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
    @endif
</section>
