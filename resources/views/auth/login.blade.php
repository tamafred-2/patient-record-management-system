<x-layout title="Login" :show-guest-header="false">
    <section x-data="{}" aria-labelledby="login-heading" class="login-shell mx-auto my-3 grid max-w-5xl overflow-hidden rounded-3xl border border-slate-200 bg-white font-sans shadow-xl shadow-slate-200/50 lg:my-10 lg:grid-cols-2">
        <div class="relative overflow-hidden bg-emerald-950 px-7 py-9 text-white sm:px-10 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full border border-white/10"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-36 -left-16 h-96 w-96 rounded-full border border-white/10"></div>
            <div class="relative">
                <div class="mb-6 flex items-center gap-3 lg:mb-16">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full"><img src="{{ asset('images/rhu-logo.jpg') }}" alt="RHU Calasiao logo" class="h-full w-full rounded-full object-cover"></span>
                    <div><p class="text-xl font-semibold">RHU Calasiao</p><p class="mt-1 text-xs tracking-wide text-emerald-200">Municipal Health Office</p></div>
                </div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-300">Patient Records Management</p>
                <h2 class="mt-4 hidden max-w-sm lg:block text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">Better records.<br>Better care.</h2>
                <p class="mt-5 hidden max-w-sm lg:block text-sm leading-7 text-emerald-100/85">A shared workspace for the people caring for our community, from registration to consultation and follow-up.</p>
            </div>
            <div class="relative mt-9 hidden items-start lg:flex gap-3 border-t border-white/15 pt-6 lg:mt-16">
                <svg aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-6"/></svg>
                <p class="text-xs leading-6 text-emerald-100/85">For authorized RHU personnel only.<br>Keep patient information private and sign out when finished.</p>
            </div>
        </div>
        <div class="flex flex-col justify-center px-7 py-10 sm:px-12 lg:py-14">
            <h1 id="login-heading" class="text-3xl font-semibold tracking-tight text-slate-900 ">Login</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">Sign in with your assigned account to access your workspace.</p>
            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-6" x-data="{ showPassword: false }">
                @csrf
                <x-input name="email" label="Email address" type="email" autocomplete="username" placeholder="Enter your email address" required autofocus />
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required placeholder="Enter your password" class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-3 pr-12 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" :aria-pressed="showPassword.toString()" aria-label="Show password" aria-controls="password" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-lg text-slate-500 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-emerald-700">
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path x-show="showPassword" x-cloak stroke-linecap="round" d="m3 3 18 18"/></svg>
                        </button>
                    </div>
                    @error('password')<p id="password-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end text-sm">
                    <a href="{{ route('password.request') }}" @click.prevent="$refs.passwordHelp.showModal()" aria-haspopup="dialog" class="font-medium text-emerald-700 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-emerald-700">Forgot password?</a>
                </div>
                <x-button class="w-full py-3">Sign in</x-button>
            </form>
            <div class="mt-8 border-t border-slate-100 pt-6">
                <p class="text-sm font-medium text-slate-700">Need help with your account?</p>
                <p class="mt-2 text-xs leading-6 text-slate-500">Contact your system administrator for access or a password reset.</p>
            </div>
        </div>
        <dialog x-ref="passwordHelp" aria-labelledby="password-help-heading" class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-3xl border border-slate-200 bg-white p-7 text-slate-900 shadow-2xl backdrop:bg-slate-950/50 sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <h2 id="password-help-heading" class="text-2xl font-semibold">Forgot password?</h2>
                <button type="button" @click="$refs.passwordHelp.close()" aria-label="Close password help" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-700">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>
            @include('auth.password-help')
        </dialog>
    </section>
</x-layout>
