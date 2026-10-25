<x-layout title="Forgot password" :show-guest-header="false">
    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-semibold">Forgot password?</h1>
        @include('auth.password-help')
        <x-button href="{{ route('login') }}" class="mt-8">Back to login</x-button>
    </section>
</x-layout>
