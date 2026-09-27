@props(['reference', 'labelledby'])
<dialog x-ref="{{ $reference }}" aria-labelledby="{{ $labelledby }}" class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/45" @click="if ($event.target === $el) { const rect = $el.getBoundingClientRect(); if ($event.clientX < rect.left || $event.clientX > rect.right || $event.clientY < rect.top || $event.clientY > rect.bottom) $el.close() }">
    {{ $slot }}
</dialog>
