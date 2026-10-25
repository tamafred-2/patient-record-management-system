<x-layout title="User Accounts">
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-semibold">User Accounts</h1><p class="mt-2 text-slate-600">Manage who can access the RHU workspace.</p></div>
        <x-button :href="route('staff.create')">Create user account</x-button>
    </div>
    <p class="mb-4 text-sm text-slate-600">Recently active means activity within the last 5 minutes, even if the user has since closed the browser or signed out. Refresh to update.</p>
    <x-table-filters label="Search users by name or email" :filters="[
        'role' => ['label' => 'Role', 'options' => array_combine(array_keys(App\Support\AccountRoles::ADDITIONAL), array_keys(App\Support\AccountRoles::ADDITIONAL))],
        'status' => ['label' => 'Account status', 'options' => ['active' => 'Activated', 'inactive' => 'Deactivated']],
        'activity' => ['label' => 'Activity', 'options' => ['recent' => 'Recently active', 'offline' => 'Offline']],
    ]" />
    <x-scroll-queue :paginator="$users" id="users" label="Users, scroll to load more">
<table class="w-full text-left text-sm">
            <caption class="sr-only">User Accounts, assigned roles and access status</caption>
            <thead class="bg-slate-100"><tr><th scope="col" class="p-4">User</th><th scope="col" class="p-4">Roles</th><th scope="col" class="p-4">Account status</th><th scope="col" class="p-4">Activity</th><th scope="col" class="p-4">Last active</th><th scope="col" class="p-4">Action</th></tr></thead>
            <tbody>@foreach($users as $staff)<tr class="border-t border-slate-100">
                <td class="p-4"><strong>{{ $staff->name }}</strong><span class="mt-1 block text-slate-500">{{ $staff->email }}</span></td>
                <td class="p-4"><span class="block font-semibold">{{ $staff->primary_role ?? 'Primary role needs review' }}</span><span class="mt-1 block text-slate-500">{{ $staff->primary_role ? 'Additional: '.($staff->roles->pluck('name')->reject(fn ($role) => $role === $staff->primary_role)->join(', ') ?: 'None') : $staff->roles->pluck('name')->join(', ') }}</span></td>
                <td class="p-4">{{ $staff->is_active ? 'Activated' : 'Deactivated' }}</td>
                <td class="p-4"><span class="{{ $staff->recently_active ? 'text-emerald-800' : 'text-slate-500' }}">{{ $staff->recently_active ? 'Recently active' : 'Offline' }}</span></td>
                <td class="p-4">{{ $staff->last_active_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Not recorded' }}</td>
                <td class="whitespace-nowrap p-4"><a href="{{ route('staff.edit', $staff) }}" class="inline-block whitespace-nowrap font-semibold text-emerald-800 underline" aria-label="Manage access for {{ $staff->name }}">Manage access</a>@if($staff->is(auth()->user()))<span class="mt-1 block text-xs text-slate-500">Your account</span>@endif</td>
            </tr>@endforeach</tbody>
        </table>
</x-scroll-queue>
    
</x-layout>
