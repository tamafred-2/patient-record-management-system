<x-layout title="User Accounts">
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-semibold">User Accounts</h1><p class="mt-2 text-slate-600">Manage who can access the RHU workspace.</p></div>
        <a href="{{ route('staff.create') }}" class="rounded-lg bg-emerald-800 px-4 py-3 text-sm font-semibold text-white">Create user account</a>
    </div>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">User Accounts, assigned roles and access status</caption>
            <thead class="bg-slate-100"><tr><th scope="col" class="p-4">User</th><th scope="col" class="p-4">Roles</th><th scope="col" class="p-4">Status</th><th scope="col" class="p-4">Action</th></tr></thead>
            <tbody>@foreach($users as $staff)<tr class="border-t border-slate-100">
                <td class="p-4"><strong>{{ $staff->name }}</strong><span class="mt-1 block text-slate-500">{{ $staff->email }}</span></td>
                <td class="p-4">{{ $staff->roles->pluck('name')->join(', ') ?: 'No roles' }}</td>
                <td class="p-4">{{ $staff->is_active ? 'Active' : 'Inactive' }}</td>
                <td class="p-4">@if($staff->is(auth()->user()))<span class="text-slate-500">Your account</span>@else<a href="{{ route('staff.edit', $staff) }}" class="font-semibold text-emerald-800 underline" aria-label="Manage access for {{ $staff->name }}">Manage access</a>@endif</td>
            </tr>@endforeach</tbody>
        </table>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
</x-layout>
