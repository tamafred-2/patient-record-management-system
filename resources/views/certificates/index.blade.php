<x-layout title="Medical certificates">
    <a href="{{ route('consultations.show', [$patient, $visit]) }}" class="text-sm text-emerald-800 underline">Back to visit summary</a>
    <h1 class="mt-5 text-3xl font-semibold">Medical certificates</h1>
    <p class="mt-3 font-semibold">{{ $patient->full_name }} · {{ $patient->patient_number }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $visit->visit_number }} · {{ $visit->visit_date->format('M j, Y') }} · {{ $visit->status }}</p>
    <p class="mt-4 max-w-3xl text-sm text-slate-600">Based on the two supplied MHO Calasiao forms. Review and edit the wording to reflect the examination actually performed. Saving prepares a printable copy; signature and official seal are applied through the RHU process.</p>
    @if($errors->any())<div role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><p class="font-semibold">Please correct the following:</p><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($visit->status === 'OPEN')
        <form id="certificate-form" data-certificate-form method="POST" action="{{ route('certificates.store', [$patient, $visit]) }}" class="mt-6 max-w-4xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8" x-data="{ template: @js(old('template', $template)), templates: @js($templates) }">
            @csrf
            <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
            <div>
                <label for="template" class="mb-2 block text-sm font-semibold">Certificate template</label>
                <select id="template" name="template" x-model="template" class="w-full rounded-lg border border-slate-300 p-3">
                    @foreach(\App\Models\MedicalCertificate::TEMPLATES as $key => $label)<option value="{{ $key }}" @selected(old('template', $template) === $key)>{{ $label }}</option>@endforeach
                </select>
                <button type="button" @click="if (confirm('Replace the certificate wording with the selected template? Other fields will be kept.')) $refs.body.value = templates[template]" class="mt-3 text-sm font-semibold text-emerald-800 underline">Use selected template wording</button>
                <p class="mt-2 text-sm text-slate-500">Changing the template does not overwrite your text. Use the button above to reset the wording.</p>
            </div>
            <div><label for="body" class="mb-2 block font-semibold">Certificate wording</label><textarea id="body" name="body" x-ref="body" rows="7" required maxlength="1800" class="w-full rounded-lg border border-slate-300 p-3">{{ old('body', $defaults['body']) }}</textarea><p class="mt-2 text-sm text-slate-500">Replace the blank line with the actual reason or examination conclusion. Fitness is never assumed automatically.</p></div>
            <div><label for="request_text" class="mb-2 block font-semibold">Request / purpose paragraph</label><textarea id="request_text" name="request_text" rows="3" required maxlength="600" class="w-full rounded-lg border border-slate-300 p-3">{{ old('request_text', $defaults['request_text']) }}</textarea></div>
            <div><label for="findings" class="mb-2 block font-semibold">Findings (optional)</label><textarea id="findings" name="findings" rows="3" maxlength="600" class="w-full rounded-lg border border-slate-300 p-3">{{ old('findings', $defaults['findings'] ?? '') }}</textarea></div>
            @if($examinationFindings)
                <fieldset x-show="template === 'examination'">
                    <legend class="mb-3 font-semibold">Examination findings</legend>
                    <p class="mb-4 text-sm text-slate-500">From this visit's saved assessment. The latest saved measurements are included when you save this copy. Request reassessment if updated measurements are needed.</p>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        @foreach(['blood_pressure' => 'Blood pressure (mmHg)', 'pulse_rate' => 'Pulse rate (/minute)', 'respiratory_rate' => 'Respiratory rate (/minute)', 'temperature' => 'Temperature (°C)'] as $field => $label)
                            @if(array_key_exists($field, $examinationFindings))
                                <div><dt class="text-sm font-medium">{{ $label }}</dt><dd id="{{ $field }}" class="mt-2 rounded-lg bg-slate-50 px-3 py-2.5">{{ $examinationFindings[$field] }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                </fieldset>
            @endif
            <div><label for="remarks" class="mb-2 block font-semibold">Remarks (optional)</label><textarea id="remarks" name="remarks" rows="3" maxlength="600" class="w-full rounded-lg border border-slate-300 p-3">{{ old('remarks', $defaults['remarks'] ?? '') }}</textarea></div>
            <fieldset><legend class="mb-3 font-semibold">Signing physician</legend><div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="signatory_name" class="mb-2 block text-sm font-medium">Doctor's printed name</label>
                    <input id="signatory_name" type="text" value="{{ auth()->user()->name }}" readonly aria-describedby="signatory-name-help" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2.5">
                    <p id="signatory-name-help" class="mt-2 text-sm text-slate-600">Automatically taken from your account when this copy is saved.</p>
                    @error('signatory_name')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                </div>
                <x-input name="signatory_title" label="Designation" :value="$defaults['signatory_title'] ?? ''" maxlength="100" required />
                <x-input name="license_number" label="License number" :value="$defaults['license_number'] ?? ''" maxlength="20" digits-only required />
            </div></fieldset>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1">I reviewed the patient, visit, clinical wording and signing physician details. This copy is for the RHU's signature and sealing process.</label>

        </form>
        <section class="mt-8" aria-labelledby="certificate-preview-title">
            <h2 id="certificate-preview-title" class="text-xl font-semibold">Landscape certificate preview</h2>
            <p class="mt-2 mb-4 text-sm text-slate-600">Draft preview updates as you edit. Use Prepare for print / PDF below when ready. Paper: A4 landscape, with the municipal seal, MHO logo and Calasiao heading.</p>
            @php
                $previewContent = array_merge($defaults, $examinationFindings, ['signatory_name' => auth()->user()->name]);
                $previewHtml = view('certificates.print', ['preview' => true, 'certificate' => (object) ['template' => $template], 'content' => $previewContent, 'patient' => $patient, 'visit' => $visit])->render();
            @endphp
            <div data-certificate-preview-container class="relative w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-100" style="max-width:1123px; height:560px">
                <iframe data-certificate-preview title="Draft medical certificate, A4 landscape" srcdoc="{{ $previewHtml }}" sandbox="allow-same-origin" style="display:block; width:1123px; height:794px; border:0; transform-origin:top left;"></iframe>
            </div>
            <div class="mt-4"><x-button type="submit" form="certificate-form">Prepare for print / PDF</x-button><p class="mt-2 text-sm text-slate-600">On the next page, choose Print / Save as PDF. Select your printer or the browser's Save as PDF destination.</p></div>
        </section>
    @else
        <p class="mt-5 rounded-lg bg-slate-100 p-4">This visit is closed. New certificates cannot be prepared.</p>
    @endif
</x-layout>
