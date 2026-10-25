<x-layout title="Notifications">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-semibold">Notifications</h1>
        @if($notifications->contains(fn ($item) => ! $item['model']->read_at))
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Mark all as read</button></form>
        @endif
    </div>
    <p class="mt-3 text-slate-600">New items appear automatically while a page is open. The latest 50 are shown.</p>
    <ul class="mt-6 space-y-3">
        @forelse($notifications as $item)
            @php($notification = $item['model'])
            <li class="rounded-xl border {{ $item['urgent'] && ! $notification->read_at ? 'border-red-300 bg-red-50' : ($notification->read_at ? 'border-slate-200 bg-white' : 'border-emerald-300 bg-emerald-50') }} p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">@unless($notification->read_at)<span class="mr-2 rounded-full {{ $item['urgent'] ? 'bg-red-700' : 'bg-emerald-700' }} px-2 py-0.5 text-xs text-white">New</span>@endunless{{ $item['headline'] }}</p>
                        @if($item['details'])<p class="mt-1 text-sm text-slate-600">{{ $item['details'] }}</p>@endif
                        <p class="mt-1 text-sm text-slate-500">{{ $notification->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}@if($item['state']) &middot; {{ $item['state'] }}@endif</p>
                    </div>
                    @if($item['url'])<a href="{{ route('notifications.open', $notification->id) }}" class="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">Open</a>@endif
                </div>
            </li>
        @empty
            <li class="rounded-xl border border-slate-200 bg-white p-6 text-slate-600">No notifications yet.</li>
        @endforelse
    </ul>
</x-layout>
