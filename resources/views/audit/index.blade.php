<x-layout title="Audit log">
    <h1 class="text-3xl font-semibold">Audit log</h1>
    <p class="mt-2 text-slate-600">User activity and record references. Dates and times use Asia/Manila.</p>
    @if($errors->any())<x-alert type="error" class="mt-5">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</x-alert>@endif
    <form method="GET" class="my-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div><label for="from" class="mb-2 block text-sm font-medium">From date</label><input type="date" id="from" name="from" value="{{ old('from',$filters['from']??'') }}" class="w-full rounded-lg border border-slate-300 p-3"></div>
        <div><label for="to" class="mb-2 block text-sm font-medium">To date</label><input type="date" id="to" name="to" value="{{ old('to',$filters['to']??'') }}" class="w-full rounded-lg border border-slate-300 p-3"></div>
        <div><label for="staff" class="mb-2 block text-sm font-medium">User</label><select id="staff" name="staff" class="w-full rounded-lg border border-slate-300 p-3"><option value="">All users</option><option value="system" @selected(($filters['staff']??'')==='system')>System / unattributed</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((string)($filters['staff']??'')===(string)$member->id)>{{ $member->name }}{{ $member->is_active ? '' : ' (inactive)' }}</option>@endforeach</select></div>
        <div><label for="action" class="mb-2 block text-sm font-medium">Action</label><select id="action" name="action" class="w-full rounded-lg border border-slate-300 p-3"><option value="">All actions</option>@foreach($actions as $key=>$label)<option value="{{ $key }}" @selected(($filters['action']??'')===$key)>{{ $label }}</option>@endforeach</select></div>
        <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-4"><x-button>Apply filters</x-button><a href="{{ route('audit.index') }}" class="text-sm text-emerald-800 underline">Reset to last 7 days</a></div>
    </form>
    <p class="mb-3 text-sm text-slate-500">{{ $events->total() }} matching events. Clinical content and raw audit properties are not shown.</p>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Recorded activity</caption>
            <thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="p-4">Time</th><th scope="col" class="p-4">User</th><th scope="col" class="p-4">Action</th><th scope="col" class="p-4">Record</th></tr></thead>
            <tbody>@forelse($events as $event)<tr class="border-t border-slate-100">
                <td class="whitespace-nowrap p-4">{{ $event->created_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') }}<span class="block text-xs text-slate-500">Event #{{ $event->id }}</span></td>
                <td class="p-4">{{ $event->actor_label }}</td><td class="p-4">{{ $event->action_label }}</td><td class="p-4">{{ $event->record_label }}</td>
            </tr>@empty<tr><td colspan="4" class="p-8 text-center text-slate-500">No activity matches these filters.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-6">{{ $events->links() }}</div>
</x-layout>
