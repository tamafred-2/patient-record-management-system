<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-50 font-sans text-slate-900 antialiased">
        <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
            <section class="w-full rounded-2xl border border-emerald-100 bg-white p-8 shadow-sm sm:p-12" aria-labelledby="page-title">
                <img src="{{ asset('images/rhu-logo.jpg') }}" alt="Municipal Health Office of Calasiao, Pangasinan" width="96" height="96" class="mb-8 h-24 w-24 object-contain">
                <p class="text-sm font-semibold uppercase tracking-widest text-emerald-800">RHU Calasiao</p>
                <h1 id="page-title" class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Patient Records Management System</h1>
                <p class="mt-5 max-w-xl leading-7 text-slate-600">A connected record of each patient's care, from registration through their RHU visit.</p>
                <div class="mt-8 rounded-lg bg-emerald-50 px-5 py-4 text-sm leading-6 text-emerald-950">
                    Authorized RHU users can sign in to their workspace.
                </div>
                <a href="{{ route('login') }}" class="mt-6 inline-block rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white">User sign in</a>
            </section>
        </main>
        @livewireScripts
    </body>
</html>
