<x-layout title="Manage user access">
    <a href="{{ route('staff.index') }}" class="text-sm text-emerald-800 underline">← User Accounts</a>
    <h1 class="mt-5 text-3xl font-semibold">Manage user access</h1>
    <p class="mt-2 text-slate-600">{{ $staff->name }} · {{ $staff->email }}</p>
    <form method="POST" action="{{ route('staff.update', $staff) }}" class="mt-8 max-w-2xl space-y-6 rounded-xl border border-slate-200 bg-white p-6" x-data @submit="if (!confirm('Apply these role and account status changes? Deactivated accounts will lose access.')) $event.preventDefault()">
        @csrf @method('PATCH')
        @if(auth()->user()->is($staff))<p class="text-sm text-slate-600">You can add or remove your additional roles here. Your own account must stay active, and your System Admin role must stay assigned.</p>@endif
        @include('staff.roles')
        <div><label for="is_active" class="mb-2 block text-sm font-semibold">Account status</label>
            <select id="is_active" name="is_active" class="w-full rounded-lg border border-slate-300 p-3">
                <option value="1" @selected(old('is_active', (int) $staff->is_active) == 1)>Activated — can sign in</option>
                @if(!auth()->user()->is($staff))<option value="0" @selected(old('is_active', (int) $staff->is_active) == 0)>Deactivated — cannot sign in</option>@endif
            </select>
            @error('is_active')<p role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            <p class="mt-2 text-sm text-slate-500">Controls whether this account can use the system. This does not indicate whether the user is online.</p>
        </div>
        <p class="text-sm text-slate-600">Deactivation ends database-backed sessions and blocks further access. Role changes apply on the next request.</p>
        <x-button>Save access changes</x-button>
    </form>
    @if(!auth()->user()->is($staff))
        @if($generatedPassword ?? null)
            <div x-data="{ show: false, message: '', clearPassword() { this.$refs.generated.value = ''; this.$refs.generated.removeAttribute('value'); this.show = false; this.message = ''; } }" x-init="$nextTick(() => $refs.resetPasswordDialog.showModal())" @close.capture="clearPassword(); document.getElementById('generate-reset-password').focus()" @pagehide.window="clearPassword()">
                <x-dialog reference="resetPasswordDialog" labelledby="generated-password-title">
                <div class="p-6">
                <h2 id="generated-password-title" class="text-xl font-semibold">Generated temporary password</h2>
                <p class="mt-2 text-sm">Copy and share this through your approved private channel. It is shown once. Closing this window clears it; if lost, generate a new password.</p>
                <label for="generated-password" class="mt-4 block font-medium">Temporary password</label>
                <input id="generated-password" x-ref="generated" type="password" :type="show ? 'text' : 'password'" value="{{ $generatedPassword }}" readonly autocomplete="off" class="mt-2 w-full rounded-lg border border-slate-300 p-3 font-mono">
                <div class="mt-3 flex flex-wrap gap-3">
                    <button type="button" @click="show = !show" :aria-pressed="show" class="rounded-lg border border-emerald-800 px-4 py-2" x-text="show ? 'Hide password' : 'Show password'">Show password</button>
                    <button type="button" @click="navigator.clipboard ? navigator.clipboard.writeText($refs.generated.value).then(() => message = 'Copied.').catch(() => message = 'Use Show password, select the text and copy it.') : message = 'Use Show password, select the text and copy it.'" class="rounded-lg bg-emerald-800 px-4 py-2 text-white">Copy password</button>
                </div>
                <p x-text="message" aria-live="polite" class="mt-2 text-sm"></p>
                <div class="mt-6 flex justify-end border-t border-slate-100 pt-4"><button type="button" @click="$refs.resetPasswordDialog.close()" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Close</button></div>
                </div>
                </x-dialog>
            </div>
        @endif
        <section class="mt-8 max-w-2xl rounded-xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-semibold">Reset user password</h2><p class="mt-2 text-sm text-slate-600">Ends this account's sessions and requires a new password at next sign-in. Account status and roles stay unchanged.</p><p class="mt-2 text-sm font-medium">{{ $staff->must_change_password ? 'Password change required' : 'No pending password change' }}</p>
            @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }} <a href="{{ route('staff.edit',$staff) }}" class="underline">Reload user page</a></x-alert>@endif
            <form method="POST" action="{{ route('staff.password.reset', $staff) }}" class="mt-5 space-y-5">
                @csrf<input type="hidden" name="password_version" value="{{ $staff->password_version }}">
                <p class="text-sm text-slate-500">After you confirm, the system generates a 16-character temporary password and displays it once.</p>
                <label class="flex items-start gap-3 text-sm"><input name="confirm" type="checkbox" value="1" required class="mt-1">I confirm this reset and understand the user must change the temporary password.</label>
                <x-button id="generate-reset-password">Generate and reset password</x-button>
            </form>
        </section>
    @endif
</x-layout>
