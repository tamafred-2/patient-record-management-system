@props(['unread' => 0])
<div class="relative" x-data="{
        open: false, loading: false, failed: false, items: [],
        async load() {
            this.loading = true; this.failed = false;
            try {
                const response = await fetch(@js(route('notifications.panel')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error();
                this.items = (await response.json()).items;
            } catch { this.failed = true; } finally { this.loading = false; }
        },
        toggle() { this.open = !this.open; if (this.open) this.load(); },
    }" @keydown.escape.window="if (open) { open = false; $refs.bellButton.focus() }" @click.outside="open = false">
    <button type="button" x-ref="bellButton" @click="toggle()" data-unread-link aria-haspopup="true" aria-controls="notification-panel" :aria-expanded="open.toString()"
            aria-label="Notifications{{ $unread ? ', '.$unread.' unread' : '' }}" title="Notifications"
            class="relative flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
        <x-sidebar-icon name="bell" />
        <span aria-hidden="true" data-unread-badge @if(! $unread) hidden @endif class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-600 px-1 text-center text-[11px] font-bold leading-5 text-white">{{ $unread > 9 ? '9+' : $unread }}</span>
    </button>
    <div id="notification-panel" x-show="open" x-cloak x-transition.opacity role="region" aria-label="Recent notifications"
         class="absolute right-0 z-50 mt-2 w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="font-semibold">Notifications</p>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-sm font-medium text-emerald-800 hover:underline">Mark all as read</button></form>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <p x-show="loading && !items.length" class="px-4 py-6 text-sm text-slate-500">Loading…</p>
            <p x-show="failed" class="px-4 py-6 text-sm text-red-700">Notifications could not be loaded. Try again.</p>
            <p x-show="!loading && !failed && !items.length" class="px-4 py-6 text-sm text-slate-500">No notifications yet.</p>
            <template x-for="item in items" :key="item.id">
                <a :href="item.url || @js(route('notifications.index'))" class="block border-b border-slate-100 px-4 py-3 hover:bg-slate-50" :class="item.unread ? (item.urgent ? 'bg-red-50' : 'bg-emerald-50/60') : ''">
                    <span class="flex items-start gap-2">
                        <span x-show="item.unread" aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="item.urgent ? 'bg-red-600' : 'bg-emerald-600'"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900" x-text="item.headline"></span>
                            <span x-show="item.details" class="mt-0.5 block truncate text-xs text-slate-500" x-text="item.details"></span>
                            <span class="mt-0.5 block text-xs text-slate-400" x-text="item.time"></span>
                        </span>
                    </span>
                </a>
            </template>
        </div>
        <a href="{{ route('notifications.index') }}" class="block bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-emerald-800 hover:bg-slate-100">View all notifications</a>
    </div>
</div>
