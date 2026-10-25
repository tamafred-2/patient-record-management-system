<div class="mt-5" x-data="{}" x-init="$nextTick(() => $refs.existingPatient.showModal())" @close.capture="document.getElementById('first_name').focus()">
    <button type="button" @click="$refs.existingPatient.showModal()" aria-haspopup="dialog" class="text-sm font-semibold text-emerald-800 underline underline-offset-4">View matching patient</button>
    <x-dialog reference="existingPatient" labelledby="existing-patient-title">
        <div class="relative p-6 sm:p-8">
            <button type="button" @click="$refs.existingPatient.close()" aria-label="Close duplicate notice" class="absolute right-4 top-4 rounded-full p-2 text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-emerald-700">
                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
            <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                <svg aria-hidden="true" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path d="M12 7v6m0 3v1" /></svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-700">Duplicate profile detected</p>
            <h2 id="existing-patient-title" class="mt-2 text-2xl font-semibold tracking-tight">Patient already exists</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">These details match an existing patient. Nothing was saved. Use their existing profile, or return to the form to correct the details.</p>
            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">{{ $match->patient_number }}</p>
                <p class="mt-1 break-words text-lg font-semibold">{{ $match->full_name }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-slate-500">Date of birth</dt><dd class="mt-1 font-medium">{{ $match->birth_date?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
                    <div><dt class="text-slate-500">Barangay</dt><dd class="mt-1 break-words font-medium">{{ $match->barangay ?? 'Not recorded' }}</dd></div>
                </dl>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <x-button href="{{ route('patients.show', $match) }}">Open existing profile</x-button>
                <button type="button" autofocus @click="$refs.existingPatient.close()" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700">Back to editing</button>
            </div>
        </div>
    </x-dialog>
    <noscript><p class="mt-2 text-red-800">Patient already exists. Nothing was saved. <a href="{{ route('patients.show', $match) }}" class="underline">Open existing profile</a> or correct the details below.</p></noscript>
</div>
