<x-layout title="Create user account">
    <a href="{{ route('staff.index') }}" class="text-sm text-emerald-800 underline">← User Accounts</a>
    <h1 class="mt-5 text-3xl font-semibold">Create user account</h1>
    <p class="mt-2 text-slate-600">All fields are required. New accounts are active.</p>
    <form method="POST" action="{{ route('staff.store') }}" class="mt-8 max-w-2xl space-y-6 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        <x-input name="name" label="Full name" autocomplete="name" maxlength="255" required />
        <x-input name="email" label="Email address" type="email" autocomplete="off" maxlength="255" required />
        <x-input name="password" label="Password (12–72 characters)" type="password" autocomplete="new-password" minlength="12" maxlength="72" required />
        <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        @include('staff.roles')
        <p class="text-sm text-slate-600">The user must change this temporary password at first sign-in.</p>
        <x-button>Create account</x-button>
    </form>
</x-layout>
