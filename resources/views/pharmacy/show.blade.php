<x-layout title="Dispense medicines">
    @php
        $remaining = array_sum(array_column($quantities, 'remaining'));
        $released = array_sum(array_column($quantities, 'released'));
        $canRelease = $visit->status === 'OPEN' && $remaining > 0 && auth()->user()->can('pharmacy.dispense');
        $byId = $prescription->items->keyBy('id');
    @endphp
    <a href="{{ route('pharmacy.index', ['date' => $visit->visit_date->toDateString()]) }}" class="text-emerald-800 underline">Back to pharmacy</a>
    <h1 class="mt-5 text-3xl font-semibold">{{ $prescription->prescription_number }}</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-1 text-sm text-slate-600">Birth date: {{ $patient->birth_date?->format('M j, Y') ?? 'Not recorded' }} &middot; {{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</p>
    <p class="mt-1 text-sm text-slate-600">Queue reference: {{ $visit->queue_reference ?? 'Not recorded' }}</p>
    <p class="mt-2 text-sm">Prescribing doctor: {{ $prescription->doctor?->name }} &middot; Issued {{ $prescription->prescribed_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
    <p class="mt-3 font-semibold">{{ $remaining === 0 ? 'Fully dispensed' : ($released > 0 ? 'Partially dispensed' : 'Not dispensed') }}</p>
    @if($errors->any())<x-alert type="error" class="mt-6">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach @error('lock_version')<a href="{{ route('pharmacy.show', [$patient, $visit]) }}" class="underline">Reload remaining quantities</a>@enderror</x-alert>@endif
    <p class="mt-4 text-sm text-slate-600">Check actual medicine availability before recording a release. There is no live stock balance in this system. Record only quantities actually given; tell the doctor or Information Staff if medicine is unavailable before they complete the visit.</p>
    <form method="POST" action="{{ route('pharmacy.store', [$patient, $visit]) }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $prescription->lock_version) }}">
        @foreach($prescription->items as $item)
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">{{ $item->medicine_name }} {{ $item->strength }}</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    @foreach(['dosage'=>'Dosage','frequency'=>'Frequency','duration'=>'Duration','instructions'=>'Instructions'] as $field=>$label)<div><dt class="text-slate-500">{{ $label }}</dt><dd class="whitespace-pre-wrap break-words">{{ $item->$field ?? 'Not recorded' }}</dd></div>@endforeach
                </dl>
                <p class="mt-4 text-sm">Prescribed: {{ $item->quantity_prescribed }} &middot; Released: {{ App\Support\DispensingQuantities::format($quantities[$item->id]['released']) }} &middot; Remaining: {{ App\Support\DispensingQuantities::format($quantities[$item->id]['remaining']) }}</p>
                @if($canRelease && $quantities[$item->id]['remaining'] > 0)
                    <div class="mt-4"><label for="quantity-{{ $item->id }}" class="mb-2 block text-sm font-medium">Quantity released now</label><input id="quantity-{{ $item->id }}" name="quantities[{{ $item->id }}]" type="number" min="0" max="{{ App\Support\DispensingQuantities::format($quantities[$item->id]['remaining']) }}" step="0.01" value="{{ old('quantities.'.$item->id, '0') }}" aria-describedby="quantity-help-{{ $item->id }}" class="w-full rounded-lg border border-slate-300 p-3 sm:max-w-xs"><p id="quantity-help-{{ $item->id }}" class="mt-1 text-xs text-slate-500">Use the prescribed quantity units. Leave zero if none of this medicine is released.</p>@error('quantities.'.$item->id)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                @endif
            </section>
        @endforeach
        @if($canRelease)
            <div><label for="remarks" class="mb-2 block text-sm font-medium">Release remarks (optional)</label><textarea id="remarks" name="remarks" maxlength="1000" rows="3" class="w-full rounded-lg border border-slate-300 bg-white p-3">{{ old('remarks') }}</textarea></div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1">I confirm these quantities were actually released. This release will be saved as a permanent record.</label>
            <x-button>Record release</x-button>
        @else
            <p class="text-sm text-slate-600">No release can be recorded here: quantities are complete, the visit is closed, or this account has read-only access.</p>
        @endif
    </form>
    <section class="mt-8">
        <h2 class="text-xl font-semibold">Release history</h2>
        @forelse($prescription->dispensings as $release)
            <article class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="font-semibold">Release #{{ $release->id }} &middot; {{ $release->pharmacist?->name }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $release->dispensed_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (Asia/Manila)</p>
                <ul class="mt-3 list-inside list-disc text-sm">@foreach($release->items as $line)<li>{{ $byId[$line->prescription_item_id]->medicine_name }}: {{ $line->quantity_dispensed }}</li>@endforeach</ul>
                @if($release->remarks)<p class="mt-3 whitespace-pre-wrap break-words text-sm">{{ $release->remarks }}</p>@endif
            </article>
        @empty
            <p class="mt-3 text-sm text-slate-600">No releases recorded.</p>
        @endforelse
    </section>
</x-layout>
