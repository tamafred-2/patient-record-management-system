<dialog id="validation-modal" aria-labelledby="validation-modal-title" aria-describedby="validation-modal-description" class="m-auto w-[calc(100%-2rem)] max-w-lg max-h-[85dvh] overflow-auto rounded-2xl border border-red-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/50">
    <div class="border-b border-red-100 bg-red-50 px-6 py-5">
        <p class="text-sm font-semibold text-red-700">Please review your information</p>
        <h2 id="validation-modal-title" class="mt-1 text-xl font-semibold">Some details need attention</h2>
        <p id="validation-modal-description" class="mt-2 text-sm text-slate-600">Review the messages below, then return to the form to correct them.</p>
    </div>
    <div class="px-6 py-5">
        <ul data-validation-messages class="list-disc space-y-3 break-words pl-5 text-sm leading-6 text-red-900">
            @foreach($errors->getMessages() as $field => $messages)
                @foreach($messages as $message)<li data-error-field="{{ $field }}">{{ $message }}</li>@endforeach
            @endforeach
        </ul>
        <button type="button" data-validation-close autofocus class="mt-6 w-full rounded-lg bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Return to form</button>
    </div>
</dialog>
