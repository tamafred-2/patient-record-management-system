<x-layout title="Prescription">
    @php
        $record = $visit->prescription;
        $canEdit = $visit->status === 'OPEN' && $visit->treatmentRecord && (!$record || ($record->status === 'DRAFT' && $record->doctor_id === auth()->id())) && auth()->user()->can($record ? 'prescriptions.update' : 'prescriptions.create');
        $blank = array_fill_keys(App\Models\PrescriptionItem::FIELDS, '');
        $rows = old('items', $record ? $record->items->map(fn ($item) => $item->only(App\Models\PrescriptionItem::FIELDS))->all() : [$blank]);
        $labels = ['medicine_name' => 'Medicine name', 'strength' => 'Strength (optional)', 'dosage' => 'Dosage', 'frequency' => 'Frequency', 'duration' => 'Duration (optional)', 'quantity_prescribed' => 'Quantity prescribed', 'instructions' => 'Instructions (optional)'];
    @endphp
    <a href="{{ route('consultations.show', [$patient, $visit]) }}" class="text-emerald-800 underline">Back to visit summary</a>
    <h1 class="mt-5 text-3xl font-semibold">Prescription</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-1 text-sm text-slate-600">{{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</p>
    @if($errors->any())<x-alert type="error" class="mt-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach @error('lock_version')<a href="{{ route('prescriptions.show', [$patient, $visit]) }}" class="underline">Reload latest prescription</a>@enderror</x-alert>@endif
    @if($record)
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold">{{ $record->prescription_number }} &middot; {{ $record->status }}</h2>
            <p class="mt-2 text-sm text-slate-500">Prescribing doctor: {{ $record->doctor?->name }} &middot; Saved prescription</p>
            <div class="mt-4 space-y-5">
                @foreach($record->items as $item)
                    <article class="rounded-lg border border-slate-200 p-4">
                        <h3 class="font-semibold">{{ $loop->iteration }}. {{ $item->medicine_name }} {{ $item->strength }}</h3>
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                            @foreach($labels as $field => $label)
                                @if(!in_array($field, ['medicine_name', 'strength']))<div><dt class="text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-wrap break-words">{{ $item->$field ?? 'Not recorded' }}</dd></div>@endif
                            @endforeach
                        </dl>
                    </article>
                @endforeach
            </div>
            @if($record->prescribed_at)<p class="mt-5 text-sm text-slate-500">Issued {{ $record->prescribed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila). Issued prescriptions are read-only.</p>@endif
            @if($record?->status === 'ISSUED')<p class="mt-5 rounded-lg bg-emerald-50 p-4 text-emerald-900">This prescription is available in Pharmacy. The pharmacist checks availability and records the quantities actually released. The visit remains open until it is explicitly completed.</p>@endif
    @if($canEdit)
                <form method="POST" action="{{ route('prescriptions.issue', [$patient, $visit]) }}" class="mt-6 space-y-4 border-t border-slate-200 pt-5">
                    @csrf
                    <input type="hidden" name="lock_version" value="{{ $record->lock_version }}">
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1">I reviewed the saved prescription above and confirm it is ready to issue. Issuing locks it against editing.</label>
                    <x-button>Issue saved prescription</x-button>
                </form>
            @endif
        </section>
    @endif
    @if($canEdit)
        <form method="POST" action="{{ route('prescriptions.save', [$patient, $visit]) }}" class="mt-6 space-y-5" x-data="{ rows: {{ Js::from(array_values($rows)) }}, blank: {{ Js::from($blank) }} }">
            @csrf @method('PUT')
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $record?->lock_version ?? 0) }}">
            <h2 class="text-xl font-semibold">{{ $record ? 'Edit draft' : 'New prescription draft' }}</h2>
            <p class="text-sm text-slate-600">Enter the prescribed medicine details. Save your changes before reviewing and issuing the prescription above.</p>
            <template x-for="(row, index) in rows" :key="index">
                <fieldset class="rounded-xl border border-slate-200 bg-white p-5">
                    <legend class="px-2 font-semibold" x-text="'Medicine ' + (index + 1)"></legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($labels as $field => $label)
                            <div @class(['sm:col-span-2' => $field === 'instructions'])><label :for="'{{ $field }}-' + index" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                                @if($field === 'instructions')
                                    <textarea :id="'instructions-' + index" :name="'items[' + index + '][instructions]'" x-model="row.instructions" rows="5" maxlength="1000" class="w-full resize-y rounded-lg border border-slate-300 p-3"></textarea>
                                @else
                                <input :id="'{{ $field }}-' + index" :name="'items[' + index + '][{{ $field }}]'" x-model="row.{{ $field }}" @if($field === 'quantity_prescribed') type="number" step="0.01" min="0.01" max="99999999.99" @else type="text" maxlength="{{ $field === 'instructions' ? 1000 : ($field === 'strength' ? 100 : 200) }}" @endif @if(in_array($field, ['medicine_name','dosage','frequency','quantity_prescribed'])) required @endif class="w-full rounded-lg border border-slate-300 p-3">
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <button type="button" @click="rows.splice(index, 1)" :disabled="rows.length === 1" class="mt-4 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 disabled:opacity-40">Remove medicine</button>
                </fieldset>
            </template>
            <div class="flex flex-wrap gap-3">
                <button type="button" @click="rows.push({ ...blank })" :disabled="rows.length >= 20" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold disabled:opacity-40">Add medicine</button>
                <x-button>Save draft</x-button>
            </div>
        </form>
    @elseif(!$visit->treatmentRecord)
        <p class="mt-6 text-slate-600">Save the visit's Digital ITR before creating a prescription.</p>
    @elseif(!$record)
        <p class="mt-6 text-slate-600">No prescription recorded. This visit is read-only.</p>
    @endif
</x-layout>
