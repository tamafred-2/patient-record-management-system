<x-layout title="Change password">
    <h1 class="text-3xl font-semibold">Change password</h1>
    <p class="mt-3 text-slate-600">{{ auth()->user()->must_change_password ? 'Set a new private password before continuing. Enter your temporary password as the current password.' : 'Choose a new private password. Other signed-in sessions will be ended.' }}</p>
    @if($errors->any())<x-alert type="error" class="mt-5">{{ $errors->first() }}</x-alert>@endif
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf @method('PUT')
        @foreach(['current_password'=>'Current password','password'=>'New password','password_confirmation'=>'Confirm new password'] as $name=>$label)
            <div><label for="{{ $name }}" class="mb-2 block font-medium">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $name === 'current_password' ? 'current-password' : 'new-password' }}" required class="w-full rounded-lg border border-slate-300 p-3">@error($name)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        @endforeach
        <p class="text-sm text-slate-500">Use at least 12 characters and no more than 72 bytes. Do not reuse your current password.</p>
        <x-button>Change password</x-button>
    </form>
    <form action="{{ route('logout') }}" method="POST" class="mt-5">@csrf<button class="font-medium text-emerald-800 underline">Log out instead</button></form>
</x-layout>
