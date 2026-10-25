<x-layout title="Change password">
    @php
        $forced = auth()->user()->must_change_password;
        $fields = $forced
            ? ['password' => 'New password', 'password_confirmation' => 'Confirm new password']
            : ['current_password' => 'Current password', 'password' => 'New password', 'password_confirmation' => 'Confirm new password'];
    @endphp
    <h1 class="text-3xl font-semibold">Change password</h1>
    <p class="mt-3 text-slate-600">{{ $forced ? 'Set a new private password before continuing. You signed in with a temporary password, so you do not need to enter it again.' : 'Choose a new private password. Other signed-in sessions will be ended.' }}</p>
    @if($errors->any())<x-alert type="error" class="mt-5">{{ $errors->first() }}</x-alert>@endif
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6"
          x-data="{ show: {}, password: '', confirmation: '', get mismatch() { return this.confirmation !== '' && this.password !== this.confirmation; }, get matches() { return this.confirmation !== '' && this.password === this.confirmation; } }"
          x-effect="$refs.confirmation.setCustomValidity(mismatch ? 'Passwords do not match.' : '')">
        @csrf @method('PUT')
        @foreach($fields as $name => $label)
            <div>
                <label for="{{ $name }}" class="mb-2 block font-medium">{{ $label }}</label>
                <div class="relative">
                    <input id="{{ $name }}" name="{{ $name }}" type="password" :type="show.{{ $name }} ? 'text' : 'password'" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" required
                           @if($name === 'password') x-model="password" @endif
                           @if($name === 'password_confirmation') x-model="confirmation" x-ref="confirmation" :class="mismatch ? 'border-red-500' : (matches ? 'border-emerald-600' : 'border-slate-300')" :aria-invalid="mismatch.toString()" aria-describedby="password-match-status" @endif
                           class="w-full rounded-lg border border-slate-300 py-3 pl-3 pr-12 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700">
                    <button type="button" @click="show.{{ $name }} = !show.{{ $name }}" :aria-pressed="(!!show.{{ $name }}).toString()" :aria-label="show.{{ $name }} ? 'Hide {{ strtolower($label) }}' : 'Show {{ strtolower($label) }}'" aria-label="Show {{ strtolower($label) }}" aria-controls="{{ $name }}" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-lg text-slate-500 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-emerald-700">
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path x-show="show.{{ $name }}" x-cloak stroke-linecap="round" d="m3 3 18 18"/></svg>
                    </button>
                </div>
                @if($name === 'password_confirmation')
                    <p id="password-match-status" class="mt-1 text-sm" aria-live="polite" :class="mismatch ? 'text-red-700' : 'text-emerald-800'" x-text="mismatch ? 'Passwords do not match.' : (matches ? 'Passwords match.' : '')"></p>
                @endif
                @error($name)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <p class="text-sm text-slate-500">Use at least 8 characters and no more than 72 bytes. Do not reuse your current password.</p>
        <x-button>Change password</x-button>
    </form>
    <form action="{{ route('logout') }}" method="POST" class="mt-5">@csrf<button class="font-medium text-emerald-800 underline">Log out instead</button></form>
</x-layout>
