@props(['type' => 'success', 'title' => null, 'dismissible' => false])
@php($isError = $type === 'error')
<div x-data="{ visible: true }" x-show="visible"
    role="{{ $isError ? 'alert' : 'status' }}"
    {{ $attributes->class(['flex items-start gap-3 rounded-xl border p-4 shadow-sm', 'border-red-200 bg-red-50 text-red-900' => $isError, 'border-emerald-200 bg-emerald-50 text-emerald-950' => !$isError]) }}>
    <svg aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        @if($isError)
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
        @else
            <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>
        @endif
    </svg>
    <div class="min-w-0 flex-1 text-sm">
        <p class="font-semibold">{{ $title ?? ($isError ? 'Please check the following' : 'Success') }}</p>
        <div class="mt-1 break-words leading-6">{{ $slot }}</div>
    </div>
    @if($dismissible)
        <button type="button" @click="visible = false" aria-label="Dismiss notification" class="shrink-0 rounded-md p-1 opacity-70 hover:bg-black/5 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    @endif
</div>
