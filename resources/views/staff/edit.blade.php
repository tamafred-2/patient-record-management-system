<x-layout title="Manage user access">
    <a href="{{ route('staff.index') }}" class="text-sm text-emerald-800 underline">← User Accounts</a>
    <h1 class="mt-5 text-3xl font-semibold">Manage user access</h1>
    <p class="mt-2 text-slate-600">{{ $staff->name }} · {{ $staff->email }}</p>
    <form method="POST" action="{{ route('staff.update', $staff) }}" class="mt-8 max-w-2xl space-y-6 rounded-xl border border-slate-200 bg-white p-6" x-data @submit="if (!confirm('Apply these role and account status changes? Inactive accounts will lose access.')) $event.preventDefault()">
        @csrf @method('PATCH')
        @include('staff.roles', ['selectedRoles' => $staff->getRoleNames()->all()])
        <div><label for="is_active" class="mb-2 block text-sm font-semibold">Account status</label>
            <select id="is_active" name="is_active" class="w-full rounded-lg border border-slate-300 p-3">
                <option value="1" @selected(old('is_active', (int) $staff->is_active) == 1)>Active</option>
                <option value="0" @selected(old('is_active', (int) $staff->is_active) == 0)>Inactive</option>
            </select>
            @error('is_active')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <p class="text-sm text-slate-600">Deactivation ends database-backed sessions and blocks further access. Role changes apply on the next request.</p>
        <x-button>Save access changes</x-button>
    </form>
    @if(!auth()->user()->is($staff))
        <section class="mt-8 max-w-2xl rounded-xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Reset user password</h2><p class="mt-2 text-sm text-slate-600">Ends this account's sessions and requires a new password at next sign-in. Account status and roles stay unchanged.</p><p class="mt-2 text-sm font-medium">{{ $staff->must_change_password ? 'Password change required' : 'No pending password change' }}</p>
            @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }} <a href="{{ route('staff.edit',$staff) }}" class="underline">Reload user page</a></x-alert>@endif
            <form method="POST" action="{{ route('staff.password.reset', $staff) }}" class="mt-5 space-y-5">
                @csrf<input type="hidden" name="password_version" value="{{ $staff->password_version }}">
                @foreach(['current_password'=>'Your current password','password'=>'Temporary password','password_confirmation'=>'Confirm temporary password'] as $name=>$label)<div><label for="reset-{{ $name }}" class="mb-2 block font-medium">{{ $label }}</label><input id="reset-{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" required class="w-full rounded-lg border border-slate-300 p-3"></div>@endforeach
                <p class="text-sm text-slate-500">Use at least 12 characters and no more than 72 bytes. Share it through your approved private channel.</p>
                <label class="flex items-start gap-3 text-sm"><input name="confirm" type="checkbox" value="1" required class="mt-1">I confirm this reset and understand the user must change the temporary password.</label>
                <x-button>Reset password</x-button>
            </form>
        </section>
    @endif
</x-layout>
