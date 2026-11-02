<x-layout title="Dispense medicines">
    @php
        $remaining = array_sum(array_column($quantities, 'remaining'));
        $released = array_sum(array_column($quantities, 'released'));
        $closed = array_sum(array_column($quantities, 'closed'));
        // Pharmacy keeps its own records: releases continue after the visit itself is completed.
        $canRelease = $remaining > 0 && auth()->user()->can('pharmacy.dispense');
        $inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700';
        $fmt = fn ($quantity) => App\Support\DispensingQuantities::display($quantity);
        // Preselect the stock item matching the prescribed medicine: same name and strength first, then same name.
        $match = function ($item) use ($medicines) {
            $name = mb_strtolower(trim($item->medicine_name));
            $strength = mb_strtolower(trim((string) $item->strength));
            $same = $medicines->filter(fn ($medicine) => mb_strtolower(trim($medicine->name)) === $name);

            return ($same->first(fn ($medicine) => mb_strtolower(trim((string) $medicine->strength)) === $strength) ?? $same->first())?->id;
        };
        $byId = $prescription->items->keyBy('id');
    @endphp
    <x-back-link :href="route('pharmacy.index', ['date' => $visit->visit_date->toDateString()])">Back to pharmacy</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">{{ $prescription->prescription_number }}</h1>
    <x-button variant="secondary" :href="route('prints.prescription', [$patient, $visit])" class="mt-3">Print prescription</x-button>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }} @can('history.view') &middot; <a href="{{ route('history.show', $patient) }}" class="font-semibold text-emerald-800 underline">Patient file</a>@endcan</p>
    <p class="mt-1 text-sm text-slate-600">Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }} &middot; {{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</p>
    <p class="mt-1 text-sm text-slate-600">Queue reference: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    <p class="mt-2 text-sm">Prescribing doctor: {{ $prescription->doctor?->name }} &middot; Issued {{ $prescription->prescribed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
    <p class="mt-3 font-semibold">{{ $remaining > 0 ? ($released > 0 ? 'Partially dispensed' : 'Not dispensed') : ($closed > 0 ? 'Pharmacy complete: some medicines not released here' : 'Fully dispensed') }}</p>
    @if($errors->any())<x-alert type="error" class="mt-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach @error('lock_version')<a href="{{ route('pharmacy.show', [$patient, $visit]) }}" class="underline">Reload remaining quantities</a>@enderror</x-alert>@endif
    <p class="mt-4 text-sm text-slate-600">Choose the stock item actually given; its available quantity is shown and the release is deducted from the inventory (earliest expiry first). Expired stock cannot be released. Record only quantities actually given. If a different brand or generic was given, enter it under <strong>Released as</strong>; the doctor's prescription is not changed. If the patient will buy a medicine outside or declines it, choose that under <strong>Remaining quantity</strong> so it is no longer counted as waiting for release.</p>
    <form method="POST" action="{{ route('pharmacy.store', [$patient, $visit]) }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $prescription->lock_version) }}">
        @foreach($prescription->items as $item)
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">{{ $item->medicine_name }} {{ $item->strength }}</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach(['dosage'=>'Dosage','frequency'=>'Frequency','duration'=>'Duration','instructions'=>'Instructions'] as $field=>$label)<div><dt class="text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-wrap break-words">{{ $item->$field ?? 'Not recorded' }}</dd></div>@endforeach
                </dl>
                <p class="mt-4 text-sm">Prescribed: {{ $fmt((string) $item->quantity_prescribed) }} &middot; Released: {{ $fmt($quantities[$item->id]['released']) }}@foreach($quantities[$item->id]['outcomes'] as $outcome => $amount) &middot; {{ App\Models\DispensingItem::OUTCOMES[$outcome] }}: {{ $fmt($amount) }}@endforeach &middot; Remaining: {{ $fmt($quantities[$item->id]['remaining']) }}</p>
                @if($canRelease && $quantities[$item->id]['remaining'] > 0)
                    <div class="mt-4 grid gap-4 sm:grid-cols-2" x-data="{ outcome: @js(old('outcomes.'.$item->id, 'KEEP')) }">
                        <div>
                            <label for="quantity-{{ $item->id }}" class="mb-2 block text-sm font-medium">Quantity released now</label>
                            <input id="quantity-{{ $item->id }}" name="quantities[{{ $item->id }}]" type="number" min="0" max="{{ intdiv($quantities[$item->id]['remaining'], 100) }}" step="1" value="{{ old('quantities.'.$item->id, '0') }}" aria-describedby="quantity-help-{{ $item->id }}" class="{{ $inputClass }}">
                            <p id="quantity-help-{{ $item->id }}" class="mt-1 text-xs text-slate-500">Use the prescribed quantity units. Leave zero if none is released now.</p>
                            @error('quantities.'.$item->id)<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="medicine-{{ $item->id }}" class="mb-2 block text-sm font-medium">Release from stock</label>
                            @php($selectedMedicine = (string) old('medicine_ids.'.$item->id, $match($item)))
                            <select id="medicine-{{ $item->id }}" name="medicine_ids[{{ $item->id }}]" class="{{ $inputClass }}" aria-describedby="medicine-help-{{ $item->id }}">
                                <option value="">Choose the stock item given</option>
                                @foreach($medicines as $medicine)
                                    @php($available = $medicine->available())
                                    <option value="{{ $medicine->id }}" @selected($selectedMedicine === (string) $medicine->id) @disabled($available === 0)>{{ $medicine->label() }} — {{ $available > 0 ? $fmt($available).' available' : 'out of stock' }}</option>
                                @endforeach
                            </select>
                            <p id="medicine-help-{{ $item->id }}" class="mt-1 text-xs text-slate-500">Needed only when a quantity is released. @can('inventory.manage')<a href="{{ route('inventory.index') }}" class="underline">Open inventory</a>@endcan</p>
                            @error('medicine_ids.'.$item->id)<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="released-as-{{ $item->id }}" class="mb-2 block text-sm font-medium">Released as (optional)</label>
                            <input id="released-as-{{ $item->id }}" name="released_as[{{ $item->id }}]" maxlength="150" value="{{ old('released_as.'.$item->id) }}" placeholder="Brand or generic actually given, with strength" aria-describedby="released-as-help-{{ $item->id }}" class="{{ $inputClass }}">
                            <p id="released-as-help-{{ $item->id }}" class="mt-1 text-xs text-slate-500">Filled in automatically from the stock item when its name differs from the prescription.</p>
                            @error('released_as.'.$item->id)<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="outcome-{{ $item->id }}" class="mb-2 block text-sm font-medium">Remaining quantity after this entry</label>
                            <select id="outcome-{{ $item->id }}" name="outcomes[{{ $item->id }}]" x-model="outcome" class="{{ $inputClass }}">
                                <option value="KEEP">Keep for a later release</option>
                                @foreach(App\Models\DispensingItem::OUTCOMES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            @error('outcomes.'.$item->id)<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="outcome !== 'KEEP'" x-cloak>
                            <label for="outcome-note-{{ $item->id }}" class="mb-2 block text-sm font-medium">Note (optional)</label>
                            <input id="outcome-note-{{ $item->id }}" name="outcome_notes[{{ $item->id }}]" maxlength="500" value="{{ old('outcome_notes.'.$item->id) }}" x-bind:disabled="outcome === 'KEEP'" placeholder="For example: out of stock at RHU" class="{{ $inputClass }}">
                            @error('outcome_notes.'.$item->id)<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
        @if($canRelease)
            <x-textarea name="remarks" label="Release remarks (optional)" maxlength="1000" rows="3" />
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1">I confirm these quantities were actually released and the outcomes are correct. This entry will be saved as a permanent record.</label>
            <x-button>Record pharmacy entry</x-button>
        @else
            <p class="text-sm text-slate-600">No release can be recorded here: quantities are complete, or this account has read-only access.</p>
        @endif
    </form>
    <section class="mt-8">
        <h2 class="text-xl font-semibold">Release history</h2>
        @forelse($prescription->dispensings as $release)
            <article class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="font-semibold">Release #{{ $release->id }} &middot; {{ $release->pharmacist?->name }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $release->dispensed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                <ul class="mt-3 list-inside list-disc space-y-1 text-sm">@foreach($release->items as $line)<li>
                    {{ $byId[$line->prescription_item_id]->medicine_name }}:
                    @if($line->quantity_dispensed > 0){{ $fmt((string) $line->quantity_dispensed) }} released @if($line->released_as)<span class="font-medium">as {{ $line->released_as }}</span>@endif @else none released @endif
                    @if($line->remainder_outcome)&middot; {{ $fmt((string) $line->quantity_closed) }} {{ strtolower(App\Models\DispensingItem::OUTCOMES[$line->remainder_outcome]) }}@if($line->outcome_note) ({{ $line->outcome_note }})@endif @endif
                </li>@endforeach</ul>
                @if($release->remarks)<p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $release->remarks }}</p>@endif
                @if($release->voided_at)
                    <p class="mt-3 font-semibold text-red-800">Voided by {{ $release->voidedBy?->name }} on {{ $release->voided_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
                    <p class="whitespace-pre-wrap text-sm">{{ $release->void_reason }}</p>
                @elseif(auth()->user()->can('pharmacy.dispense'))
                    <details class="mt-4"><summary class="cursor-pointer text-emerald-800 underline">Correct this release</summary>
                        <form method="POST" action="{{ route('pharmacy.void', [$patient, $visit, $release]) }}" class="mt-4 space-y-4">
                            @csrf
                            <input type="hidden" name="lock_version" value="{{ $prescription->lock_version }}">
                            <p class="text-sm">Void an incorrectly entered release, then record the correct quantities and outcomes. This preserves the original entry and reopens anything it marked as buy outside or declined. It does not record a medicine return or change physical stock.</p>
                            <label class="block">Correction reason<textarea name="reason" required maxlength="1000" rows="2" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></textarea></label>
                            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1">I confirm this entry is incorrect and should no longer count toward released quantities.</label>
                            <x-button>Void incorrect release</x-button>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <p class="mt-3 text-sm text-slate-600">No releases recorded.</p>
        @endforelse
    </section>
</x-layout>
