<x-layout :title="$medicine->name">
    @php
        $fmt = fn ($quantity) => App\Support\DispensingQuantities::display($quantity);
        $tones = ['in_stock' => 'border-emerald-300 bg-emerald-50 text-emerald-900', 'low' => 'border-amber-300 bg-amber-50 text-amber-900', 'out' => 'border-red-300 bg-red-50 text-red-900', 'expiring' => 'border-amber-300 bg-amber-50 text-amber-900', 'expired' => 'border-red-300 bg-red-50 text-red-900'];
        $inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700';
        $today = App\Models\Medicine::today();
        $usableBatches = $medicine->batches->filter(fn ($batch) => $batch->quantity_remaining > 0 || ! $batch->isExpired());
    @endphp
    <x-back-link :href="route('inventory.index')">Back to inventory</x-back-link>
    <div class="mt-5 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold">{{ $medicine->name }} {{ $medicine->strength }}</h1>
            <p class="mt-1 text-slate-600">{{ $medicine->form }} &middot; Low-stock level {{ $fmt((string) $medicine->reorder_level) }}@unless($medicine->is_active) &middot; <strong>Inactive</strong>@endunless</p>
        </div>
        <div class="text-right"><p class="text-sm text-slate-500">Available now</p><p class="text-3xl font-semibold">{{ $fmt($medicine->available()) }}</p>
            <p class="mt-1">@foreach($medicine->statuses() as $status)<span class="ml-1 inline-block rounded-full border px-2 py-0.5 text-xs font-semibold {{ $tones[$status] }}">{{ App\Models\Medicine::STATUSES[$status] }}</span>@endforeach</p></div>
    </div>
    @if($errors->any())<x-alert type="error" class="mt-5">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</x-alert>@endif

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="batches-title">
        <h2 id="batches-title" class="text-xl font-semibold">Stock by batch</h2>
        <p class="mt-1 text-sm text-slate-600">Releases use the earliest-expiring batch first. Expired batches are never released; dispose of them with an adjustment.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Batch</th><th scope="col" class="p-3">Expiry</th><th scope="col" class="p-3">Received</th><th scope="col" class="p-3">Remaining / received</th><th scope="col" class="p-3">Supplier</th></tr></thead>
                <tbody>
                    @forelse($medicine->batches as $batch)
                        <tr class="border-t border-slate-100 @if($batch->quantity_remaining == 0) text-slate-400 @endif">
                            <td class="p-3">{{ $batch->batch_number ?? 'Not recorded' }}</td>
                            <td class="p-3">{{ $batch->expiry_date?->format('M j, Y') ?? 'Not recorded' }}@if($batch->isExpired() && $batch->quantity_remaining > 0) <span class="ml-1 rounded-full border border-red-300 bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-900">Expired</span>@endif</td>
                            <td class="p-3">{{ $batch->received_on->format('M j, Y') }}<span class="block text-xs text-slate-500">{{ $batch->creator?->name }}</span></td>
                            <td class="p-3 font-semibold">{{ $fmt((string) $batch->quantity_remaining) }} / {{ $fmt((string) $batch->quantity_received) }}</td>
                            <td class="p-3">{{ $batch->supplier ?? 'Not recorded' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-slate-600">No stock received yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @can('inventory.manage')
        <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="receive-title">
                <h2 id="receive-title" class="text-xl font-semibold">Receive stock</h2>
                <form method="POST" action="{{ route('inventory.receive', $medicine) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <x-input name="quantity" label="Quantity received" type="number" min="1" step="1" required />
                    <x-input name="received_on" label="Date received" type="date" :value="old('received_on', $today)" :max="$today" required />
                    <x-input name="batch_number" label="Batch / lot number (optional)" maxlength="100" />
                    <x-input name="expiry_date" label="Expiry date" type="date" :min="Carbon\CarbonImmutable::parse($today)->addDay()->toDateString()" />
                    <div class="sm:col-span-2"><x-input name="supplier" label="Supplier / source (optional)" maxlength="150" placeholder="For example: DOH allocation" /></div>
                    <div class="sm:col-span-2"><x-button>Add stock</x-button></div>
                </form>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="adjust-title">
                <h2 id="adjust-title" class="text-xl font-semibold">Adjust stock</h2>
                @if($usableBatches->isEmpty())
                    <p class="mt-3 text-sm text-slate-600">Receive stock before making adjustments.</p>
                @else
                    <form method="POST" action="{{ route('inventory.adjust', $medicine) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div class="sm:col-span-2"><label for="medicine_batch_id" class="mb-2 block text-sm font-medium">Batch</label>
                            <select id="medicine_batch_id" name="medicine_batch_id" required class="{{ $inputClass }}">@foreach($usableBatches as $batch)<option value="{{ $batch->id }}" @selected((string) old('medicine_batch_id') === (string) $batch->id)>{{ $batch->batch_number ?? 'No batch number' }} &middot; expires {{ $batch->expiry_date?->format('M j, Y') ?? 'not recorded' }} &middot; {{ $fmt((string) $batch->quantity_remaining) }} remaining</option>@endforeach</select></div>
                        <div><label for="direction" class="mb-2 block text-sm font-medium">Change</label>
                            <select id="direction" name="direction" required class="{{ $inputClass }}"><option value="remove" @selected(old('direction') === 'remove')>Remove from stock</option><option value="add" @selected(old('direction') === 'add')>Add to stock</option></select></div>
                        <x-input name="adjust_quantity" label="Quantity" type="number" min="1" step="1" required />
                        <div><label for="reason" class="mb-2 block text-sm font-medium">Reason</label>
                            <select id="reason" name="reason" required class="{{ $inputClass }}"><option value="">Choose a reason</option>@foreach(App\Models\StockMovement::ADJUSTMENT_REASONS as $reason)<option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reason }}</option>@endforeach</select></div>
                        <x-input name="details" label="Details (optional)" maxlength="200" />
                        <div class="sm:col-span-2"><x-button variant="secondary">Save adjustment</x-button></div>
                    </form>
                @endif
            </section>
        </div>
        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="settings-title">
            <h2 id="settings-title" class="text-xl font-semibold">Medicine settings</h2>
            <form method="POST" action="{{ route('inventory.update', $medicine) }}" class="mt-4 flex flex-wrap items-end gap-4">
                @csrf @method('PUT')
                <div class="w-48"><x-input name="reorder_level" label="Low-stock level" type="number" min="0" step="1" :value="old('reorder_level', $fmt((string) $medicine->reorder_level))" required /></div>
                <div><label for="is_active" class="mb-2 block text-sm font-medium">Listing</label>
                    <select id="is_active" name="is_active" class="{{ $inputClass }}"><option value="1" @selected($medicine->is_active)>Active (can be released)</option><option value="0" @selected(! $medicine->is_active)>Inactive (hidden from release)</option></select></div>
                <x-button variant="secondary">Save settings</x-button>
            </form>
        </section>
    @endcan

    <section class="mt-6" aria-labelledby="movements-title">
        <h2 id="movements-title" class="text-xl font-semibold">Stock movements</h2>
        <x-scroll-queue :paginator="$movements" id="movements" label="Stock movements, scroll to load more" :min-rows="5">
            <table class="w-full text-left text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Date</th><th scope="col" class="p-3">Movement</th><th scope="col" class="p-3">Quantity</th><th scope="col" class="p-3">Batch</th><th scope="col" class="p-3">Details</th><th scope="col" class="p-3">By</th></tr></thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr class="border-t border-slate-100">
                            <td class="p-3 whitespace-nowrap">{{ $movement->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td class="p-3">{{ App\Models\StockMovement::TYPES[$movement->type] ?? $movement->type }}</td>
                            <td class="p-3 font-semibold {{ $movement->quantity < 0 ? 'text-red-800' : 'text-emerald-800' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $fmt((string) $movement->quantity) }}</td>
                            <td class="p-3">{{ $movement->batch?->batch_number ?? '—' }}</td>
                            <td class="p-3">{{ $movement->dispensing?->prescription ? 'Prescription '.$movement->dispensing->prescription->prescription_number : ($movement->reason ?? '') }}</td>
                            <td class="p-3">{{ $movement->creator?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-slate-600">No stock movements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-scroll-queue>
    </section>
</x-layout>
