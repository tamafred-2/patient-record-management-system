<x-layout title="Prescription">
    @php
        $record = $visit->prescription;
        $canEdit = $visit->status === 'OPEN' && $visit->treatmentRecord && (!$record || ($record->status === 'DRAFT' && $record->doctor_id === auth()->id())) && auth()->user()->can($record ? 'prescriptions.update' : 'prescriptions.create');
        $blank = array_fill_keys(App\Models\PrescriptionItem::FIELDS, '');
        $rows = old('items', $record ? $record->items->map(fn ($item) => $item->only(App\Models\PrescriptionItem::FIELDS))->all() : [$blank]);
        foreach ($rows as &$row) {
            $row['quantity_prescribed'] = preg_replace('/\.0+$/', '', (string) ($row['quantity_prescribed'] ?? ''));
        }
        unset($row);
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
                                @if(!in_array($field, ['medicine_name', 'strength']))<div><dt class="text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-wrap break-words">{{ $field === 'quantity_prescribed' ? preg_replace('/\.0+$/', '', (string) $item->$field) : ($item->$field ?? 'Not recorded') }}</dd></div>@endif
                            @endforeach
                        </dl>
                    </article>
                @endforeach
            </div>
            @if($record->prescribed_at)<p class="mt-5 text-sm text-slate-500">Issued {{ $record->prescribed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Issued prescriptions are read-only.</p>@endif
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
            <p class="text-sm text-slate-600">Type the medicine name. For strength, dosage, frequency and duration, choose a common value or a previous entry, or type your own. Dropdown entries are not dosing recommendations or a stock list; review each value for the medicine being prescribed. Save your changes before reviewing and issuing the prescription above.</p>
            <template x-for="(row, index) in rows" :key="index">
                <fieldset class="rounded-xl border border-slate-200 bg-white p-5">
                    <legend class="px-2 font-semibold" x-text="'Medicine ' + (index + 1)"></legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($labels as $field => $label)
                            <div @class(['sm:col-span-2' => $field === 'instructions'])><label :for="'{{ $field }}-' + index" class="mb-2 block text-sm font-medium">{{ $label }}</label>
                                @if(in_array($field, ['strength', 'dosage', 'frequency', 'duration']))
                                    <div class="relative" x-data="{ open: false, active: 0, options: {{ Js::from(array_merge([null], $suggestions[$field]->all())) }}, choose(position) { this.row.{{ $field }} = this.options[position] ?? ''; this.open = false; this.$refs.entry.focus(); } }" @click.outside="open = false" @focusout="if (!$el.contains($event.relatedTarget)) open = false">
                                        <input x-ref="entry" :id="'{{ $field }}-' + index" :name="'items[' + index + '][{{ $field }}]'" x-model="row.{{ $field }}" type="text" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" :aria-expanded="open" :aria-controls="'choices-{{ $field }}-' + index" :aria-activedescendant="open ? 'choice-{{ $field }}-' + index + '-' + active : null" autocomplete="off" maxlength="{{ $field === 'strength' ? 100 : 200 }}" @if(in_array($field, ['dosage', 'frequency'])) required @endif placeholder="Select or type a value" @input="open = false" @keydown.arrow-down.prevent="if (!open) { open = true; active = 0; } else { active = (active + 1) % options.length; }" @keydown.arrow-up.prevent="if (!open) { open = true; active = options.length - 1; } else { active = (active + options.length - 1) % options.length; }" @keydown.enter="if (open) { $event.preventDefault(); choose(active); }" @keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); open = false; }" class="w-full rounded-lg border border-slate-300 py-3 pl-3 pr-12">
                                        <button type="button" aria-label="Show {{ strtolower($label) }} options" :aria-expanded="open" :aria-controls="'choices-{{ $field }}-' + index" @click="open = !open; active = 0; $refs.entry.focus()" class="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center rounded-r-lg text-slate-600 hover:bg-slate-100"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 7 5 5 5-5"/></svg></button>
                                        <div x-show="open" x-cloak :id="'choices-{{ $field }}-' + index" role="listbox" aria-label="{{ $label }} options" class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-slate-300 bg-white p-1 shadow-lg">
                                            <template x-for="(option, position) in options" :key="position"><button type="button" role="option" tabindex="-1" :id="'choice-{{ $field }}-' + index + '-' + position" :aria-selected="active === position" @mousedown.prevent @click="choose(position)" @mouseenter="active = position" :class="active === position ? 'bg-emerald-50 text-emerald-900' : ''" class="block w-full cursor-pointer rounded px-3 py-2 text-left text-sm hover:bg-emerald-50" x-text="option === null ? 'Other - type your own value' : option"></button></template>
                                        </div>
                                    </div>
                                @elseif($field === 'instructions')
                                    <textarea :id="'instructions-' + index" :name="'items[' + index + '][instructions]'" x-model="row.instructions" rows="5" maxlength="1000" class="w-full resize-y rounded-lg border border-slate-300 p-3"></textarea>
                                @else
                                <input :id="'{{ $field }}-' + index" :name="'items[' + index + '][{{ $field }}]'" x-model="row.{{ $field }}" @if($field === 'quantity_prescribed') type="number" step="1" min="1" max="99999999" inputmode="numeric" @else type="text" maxlength="{{ $field === 'instructions' ? 1000 : ($field === 'strength' ? 100 : 200) }}" @endif @if(in_array($field, ['medicine_name','dosage','frequency','quantity_prescribed'])) required @endif class="w-full rounded-lg border border-slate-300 p-3">
                                @endif
                                @if($field === 'strength')<p class="mt-1 text-xs text-slate-500">Medicine strength, for example 100 mg or 125 mg/5 mL. Check the product label.</p>@endif
                                @if($field === 'dosage')<p class="mt-1 text-xs text-slate-500">Amount per dose, for example 1 tablet or 5 mL.</p>@endif
                                @if($field === 'frequency')<p class="mt-1 text-xs text-slate-500">How often it is taken, for example 3 times a day.</p>@endif
                                @if($field === 'duration')<p class="mt-1 text-xs text-slate-500">How long it is taken: specify a number of days, weeks or months.</p>@endif
                                @if($field === 'quantity_prescribed')<p class="mt-1 text-xs text-slate-500">Enter a whole number of the prescribed units, such as 10. Put fractional doses in Dosage.</p>@endif
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
