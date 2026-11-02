<x-layout title="Inventory">
    @php
        $fmt = fn ($quantity) => App\Support\DispensingQuantities::display($quantity);
        $tones = ['in_stock' => 'border-emerald-300 bg-emerald-50 text-emerald-900', 'low' => 'border-amber-300 bg-amber-50 text-amber-900', 'out' => 'border-red-300 bg-red-50 text-red-900', 'expiring' => 'border-amber-300 bg-amber-50 text-amber-900', 'expired' => 'border-red-300 bg-red-50 text-red-900'];
        $inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700';
    @endphp
    <div x-data="{}" @if($errors->hasAny(['name', 'strength', 'form', 'reorder_level'])) x-init="$nextTick(() => $refs.medicineDialog.showModal())" @endif>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold">Inventory</h1>
                <p class="mt-2 text-slate-600">Medicine stock available at the RHU pharmacy. Releases are deducted automatically, earliest expiry first.</p>
            </div>
            @can('inventory.manage')
                <x-button type="button" x-on:click="$refs.medicineDialog.showModal()" aria-haspopup="dialog">Add medicine</x-button>
            @endcan
        </div>
        <ul class="mt-6 grid gap-3 sm:grid-cols-3 lg:grid-cols-5" aria-label="Stock summary">
            @foreach(App\Models\Medicine::STATUSES as $status => $label)
                <li><a href="{{ route('inventory.index', ['status' => $status]) }}" class="block rounded-xl border p-4 hover:shadow-sm {{ $tones[$status] }}"><span class="block text-2xl font-semibold">{{ $summary[$status] }}</span><span class="text-sm">{{ $label }}</span></a></li>
            @endforeach
        </ul>
        <x-table-filters label="Medicine name or strength" :filters="[
            'status' => ['label' => 'Stock status', 'options' => App\Models\Medicine::STATUSES],
            'form' => ['label' => 'Form', 'options' => array_combine(App\Models\Medicine::FORMS, App\Models\Medicine::FORMS)],
            'active' => ['label' => 'Listing', 'options' => ['active' => 'Active only', 'inactive' => 'Inactive only']],
        ]" />
        <x-scroll-queue :paginator="$medicines" id="medicines" label="Medicines, scroll to load more">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Medicine inventory</caption>
                <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Medicine</th><th scope="col" class="p-3">Available</th><th scope="col" class="p-3">Low-stock level</th><th scope="col" class="p-3">Nearest expiry</th><th scope="col" class="p-3">Status</th><th scope="col" class="p-3">Action</th></tr></thead>
                <tbody>
                    @forelse($medicines as $medicine)
                        <tr class="border-t border-slate-100">
                            <td class="p-3"><strong>{{ $medicine->name }} {{ $medicine->strength }}</strong><p class="text-slate-500">{{ $medicine->form }}@unless($medicine->is_active) &middot; Inactive @endunless</p></td>
                            <td class="p-3 font-semibold">{{ $fmt($medicine->available()) }}</td>
                            <td class="p-3">{{ $fmt((string) $medicine->reorder_level) }}</td>
                            <td class="p-3">{{ $medicine->nearestExpiry()?->format('M j, Y') ?? 'None recorded' }}</td>
                            <td class="p-3">@foreach($medicine->statuses() as $status)<span class="mb-1 mr-1 inline-block whitespace-nowrap rounded-full border px-2 py-0.5 text-xs font-semibold {{ $tones[$status] }}">{{ App\Models\Medicine::STATUSES[$status] }}</span>@endforeach</td>
                            <td class="p-3"><a href="{{ route('inventory.show', $medicine) }}" class="font-semibold text-emerald-800 underline" aria-label="Open stock for {{ $medicine->label() }}">Open stock</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-slate-600">No medicines match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-scroll-queue>
        @can('inventory.manage')
            <x-dialog reference="medicineDialog" labelledby="medicine-dialog-title">
                <form method="POST" action="{{ route('inventory.store') }}" class="space-y-4 p-6">
                    @csrf
                    <h2 id="medicine-dialog-title" class="text-xl font-semibold">Add medicine</h2>
                    <p class="text-sm text-slate-600">Add each medicine once. Record the stock you received on its page afterwards.</p>
                    <x-input name="name" label="Medicine name (generic)" maxlength="150" required />
                    <x-input name="strength" label="Strength (optional)" maxlength="50" placeholder="For example: 500 mg" />
                    <div>
                        <label for="form" class="mb-2 block text-sm font-medium">Form / unit</label>
                        <select id="form" name="form" required class="{{ $inputClass }}"><option value="">Choose a form</option>@foreach(App\Models\Medicine::FORMS as $form)<option value="{{ $form }}" @selected(old('form') === $form)>{{ $form }}</option>@endforeach</select>
                        @error('form')<p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <x-input name="reorder_level" label="Low-stock level" type="number" min="0" step="1" :value="old('reorder_level', 0)" required />
                    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <x-button variant="neutral" type="button" x-on:click="$refs.medicineDialog.close()">Cancel</x-button>
                        <x-button>Add medicine</x-button>
                    </div>
                </form>
            </x-dialog>
        @endcan
    </div>
</x-layout>
