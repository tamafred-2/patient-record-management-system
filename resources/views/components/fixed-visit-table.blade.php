@props(['empty' => false, 'label' => 'Visits, scroll to view more', 'title' => 'No matching visits', 'message' => 'Try a different date or search.'])
<div class="rounded-xl border border-slate-200 bg-white">
    <div class="fixed-visit-table overflow-auto" style="height: 744px" tabindex="0" role="region" aria-label="{{ $label }}">
        {{ $slot }}
        @if($empty)
            <div class="flex flex-col items-center justify-center px-6 text-center" style="height: 700px" role="status">
                <div class="mb-4 rounded-full bg-slate-100 p-4 text-slate-400">
                    <svg aria-hidden="true" class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z M16 3v4 M8 3v4 M3 11h18 M9 16h6" /></svg>
                </div>
                <p class="font-semibold text-slate-700">{{ $title }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $message }}</p>
            </div>
        @endif
    </div>
</div>
