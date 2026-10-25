@php
    $record = $record ?? null;
    $selectedTest = $record?->authorizing_doctor_id ? $record->test_name : old('test_name', $record?->test_name);
    $templates = config('laboratory.components');
    $componentNames = $templates[$selectedTest] ?? [$selectedTest ?: ''];
    $blankRows = array_map(fn ($name) => ['measurement' => $name, 'value' => '', 'unit' => '', 'reference_range' => '', 'flag' => ''], $componentNames);
    $rows = old('result_rows', $record?->result_rows ?: $blankRows);
    $canEdit = $visit->status === 'OPEN' && auth()->user()->can('laboratory.record');
@endphp
@if($errors->any())<x-alert type="error" class="mb-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    @if($errors->has('lock_version') || $errors->has('submission_token'))<a class="underline" href="{{ $record ? route('laboratory.edit', [$patient,$visit,$record]) : route('laboratory.show', [$patient,$visit]) }}">Reload latest records</a>@endif
</x-alert>@endif
<form method="POST" action="{{ $record ? route('laboratory.update', [$patient,$visit,$record]) : route('laboratory.store', [$patient,$visit]) }}" x-data="{ status: @js(old('availability_status', $record?->availability_status ?? 'PENDING')), rows: @js($rows), testName: @js($selectedTest ?? ''), templates: @js($templates), suggestions: @js(config('laboratory.suggestions')),
        loadTemplate() {
            if (this.rows.some(row => row.value || row.unit || row.reference_range || row.flag) && !confirm('Replace the unsaved result rows with blank fields for the selected test?')) return;
            this.rows = (this.templates[this.testName] || [this.testName]).map(name => ({ measurement: name, value: '', unit: '', reference_range: '', flag: '' }));
        },
        changeTest() { if (!this.rows.some(row => row.value || row.unit || row.reference_range || row.flag)) this.loadTemplate(); }
    }" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6">
    @csrf @if($record) @method('PUT') @endif
    <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
    @if(!$record)<input type="hidden" name="submission_token" value="{{ old('submission_token', (string) Illuminate\Support\Str::uuid()) }}">@endif
    <h2 class="text-xl font-semibold">{{ $record ? 'Edit laboratory service' : 'Add laboratory service' }}</h2>
    <fieldset @disabled(!$canEdit) class="space-y-5">
        <legend class="sr-only">Laboratory service details</legend>
        <div>
            <label for="test_name" class="mb-2 block text-sm font-medium">Requested test / service</label>
            @if($record?->authorizing_doctor_id)<input type="hidden" name="test_name" value="{{ $record->test_name }}"><p class="mb-2 text-sm text-slate-600">The doctor's requested test is fixed.</p>@endif
            <select id="test_name" name="test_name" x-model="testName" @change="changeTest()" required @disabled($record?->authorizing_doctor_id) class="w-full rounded-lg border border-slate-300 p-3">
                <option value="">Select test / service</option>
                @foreach(config('laboratory.tests') as $group => $tests)
                    <optgroup label="{{ $group }}">@foreach($tests as $test)<option value="{{ $test }}" @selected($selectedTest === $test)>{{ $test }}</option>@endforeach</optgroup>
                @endforeach
                @if($record && !in_array($record->test_name, array_merge(...array_values(config('laboratory.tests'))), true))
                    <option value="{{ $record->test_name }}" @selected($selectedTest === $record->test_name)>{{ $record->test_name }} (existing record)</option>
                @endif
            </select>
        </div>
        <div>
            <label for="availability_status" class="mb-2 block text-sm font-medium">Service status</label>
            <select id="availability_status" name="availability_status" x-model="status" required class="w-full rounded-lg border border-slate-300 p-3">
                @foreach(App\Models\LaboratoryRecord::STATUSES as $value => $label)
                    @if(($record && $record->availability_status !== 'PERFORMED_RHU') || ($record?->availability_status === 'PERFORMED_RHU' && $value === 'PERFORMED_RHU') || (!$record && $value !== 'PERFORMED_RHU'))
                        <option value="{{ $value }}" @selected(old('availability_status', $record?->availability_status ?? 'PENDING') === $value)>{{ $value === 'PERFORMED_RHU' && $record?->availability_status !== 'PERFORMED_RHU' ? 'Record results and complete at RHU' : $label }}</option>
                    @endif
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">{{ $record ? 'Complete a pending request by recording its results.' : 'Save the request as Pending, or document external laboratory advice.' }}</p>
        </div>
        <div x-show="status === 'EXTERNAL_ADVISED'" x-cloak><label for="external_advice" class="mb-2 block text-sm font-medium">External laboratory advice</label><textarea id="external_advice" name="external_advice" :disabled="status !== 'EXTERNAL_ADVISED'" :required="status === 'EXTERNAL_ADVISED'" rows="3" maxlength="1000" aria-describedby="advice-help" class="w-full rounded-lg border border-slate-300 p-3">{{ old('external_advice',$record?->external_advice) }}</textarea><p id="advice-help" class="mt-1 text-xs text-slate-500">Record the advice actually given.</p>@error('external_advice')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        <fieldset x-show="status === 'PERFORMED_RHU'" x-cloak :disabled="status !== 'PERFORMED_RHU'" class="space-y-4">
            <legend class="font-semibold" x-text="testName + ' results'">Laboratory results</legend>
            <div><label for="specimen" class="mb-2 block text-sm">Specimen (optional)</label><input id="specimen" name="specimen" maxlength="200" value="{{ old('specimen', $record?->specimen) }}" class="w-full rounded-lg border border-slate-300 p-3"></div>
            <p class="text-xs text-slate-500">Enter only findings on the actual report. Leave unreported components blank; blank rows are not saved. Values and reference ranges are not prefilled. Flags are copied from the report, not calculated.</p>
            <button type="button" @click="loadTemplate()" class="cursor-pointer text-sm font-semibold text-emerald-800 underline">Load blank fields for selected test</button>
            <template x-for="(row, index) in rows" :key="index">
                <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach(['measurement' => 'Measurement / component', 'value' => 'Result value', 'unit' => 'Unit (optional)', 'reference_range' => 'Reference range (optional)'] as $key => $label)
                            <label class="block text-sm">{{ $label }}<input :name="'result_rows[' + index + '][{{ $key }}]'" x-model="row.{{ $key }}" @if($key === 'value') :aria-label="(row.measurement || ('Result ' + (index + 1))) + ' result'" :list="'result-options-' + index" @endif :required="status === 'PERFORMED_RHU' && !!(row.value || row.unit || row.reference_range || row.flag) && {{ in_array($key, ['measurement', 'value']) ? 'true' : 'false' }}" maxlength="{{ ['measurement'=>150,'value'=>500,'unit'=>50,'reference_range'=>200][$key] }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></label>
                        @endforeach
                    </div>
                    <datalist :id="'result-options-' + index"><template x-for="option in (suggestions[testName]?.[row.measurement] || [])" :key="option"><option :value="option"></option></template></datalist>
                    <label class="block text-sm">Report flag (optional)<select :name="'result_rows[' + index + '][flag]'" x-model="row.flag" :aria-label="row.measurement + ' report flag'" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                        <option value="">Not reported</option><option value="Normal">Normal</option><option value="High">High</option><option value="Low">Low</option><option value="Abnormal">Abnormal</option>
                    </select></label>
                    <button type="button" x-show="rows.length > 1" @click="rows.splice(index, 1)" class="cursor-pointer text-sm text-red-700 underline">Remove result</button>
                </div>
            </template>
            <button type="button" x-show="rows.length < 30" @click="rows.push({ measurement: '', value: '', unit: '', reference_range: '', flag: '' })" class="cursor-pointer font-semibold text-emerald-800 underline">Add result row</button>
            @if($record?->result)<p class="whitespace-pre-wrap text-sm">Previous text result: {{ $record->result }}</p>@endif
        </fieldset>
        <div><label for="notes" class="mb-2 block text-sm font-medium">Service notes (optional)</label><textarea id="notes" name="notes" rows="3" maxlength="1000" class="w-full rounded-lg border border-slate-300 p-3">{{ old('notes',$record?->notes) }}</textarea><p class="mt-1 text-xs text-slate-500">Record service remarks or comments from the laboratory report. Clinical diagnosis belongs in the doctor consultation.</p></div>
    </fieldset>
    @if($canEdit)<x-button><span x-text="status === 'PERFORMED_RHU' ? 'Save results and complete' : 'Save laboratory service'">Save laboratory service</span></x-button>@else<p class="text-sm text-slate-600">This record is read-only.</p>@endif
</form>
