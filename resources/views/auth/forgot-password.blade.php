<x-layout title="Forgot password" :show-guest-header="false">
    <section class="mx-auto max-w-lg rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-semibold">Forgot password?</h1>
        @include('auth.password-help')
        <a href="{{ route('login') }}" class="mt-8 inline-flex rounded-lg bg-emerald-800 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Back to login</a>
    </section>
</x-layout>
