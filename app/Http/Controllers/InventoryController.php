<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\StockMovement;
use App\Support\DispensingQuantities;
use App\Support\Inventory;
use App\Support\ListFilters;
use App\Support\TableSearch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    // Medicines are counted in whole units (tablets, capsules, bottles, sachets).
    private const QUANTITY = ['required', 'integer', 'min:1', 'max:99999999'];

    private const LEVEL = ['required', 'integer', 'min:0', 'max:99999999'];

    public function index(Request $request)
    {
        Gate::authorize('inventory.view');
        $filters = $request->validate([
            'search' => ListFilters::search(),
            'status' => ['nullable', Rule::in(array_keys(Medicine::STATUSES))],
            'form' => ['nullable', Rule::in(Medicine::FORMS)],
            'active' => ['nullable', 'in:active,inactive'],
        ]);
        $query = Medicine::with('batches');
        TableSearch::apply($query, $filters['search'] ?? '', ['name', 'strength']);
        $this->applyStatus($query, $filters['status'] ?? null);
        $query->when($filters['form'] ?? null, fn ($query, $form) => $query->where('form', $form))
            ->when(($filters['active'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['active'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false));
        $all = Medicine::with('batches')->active()->get();
        $summary = collect(array_keys(Medicine::STATUSES))->mapWithKeys(fn ($status) => [$status => $all->filter(fn ($medicine) => in_array($status, $medicine->statuses(), true))->count()]);

        return view('inventory.index', [
            'medicines' => $query->orderBy('name')->orderBy('strength')->orderBy('id')->scrollPaginate()->withQueryString(),
            'summary' => $summary,
        ]);
    }

    public function storeMedicine(Request $request)
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'strength' => ['nullable', 'string', 'max:50'],
            'form' => ['required', Rule::in(Medicine::FORMS)], 'reorder_level' => self::LEVEL,
        ]);
        $key = Medicine::lookupKey($data['name'], $data['strength'] ?? null, $data['form']);
        if (Medicine::where('lookup_key', $key)->exists()) {
            throw ValidationException::withMessages(['name' => 'This medicine, strength and form is already in the inventory.']);
        }
        $medicine = Medicine::forceCreate([...$data, 'name' => trim($data['name']), 'strength' => trim($data['strength'] ?? '') ?: null, 'lookup_key' => $key, 'created_by' => $request->user()->id]);
        activity('inventory')->causedBy($request->user())->performedOn($medicine)->log('inventory.medicine_added');

        return redirect()->route('inventory.show', $medicine)->with('status', $medicine->label().' added. Record the stock you received.');
    }

    public function show(Request $request, Medicine $medicine)
    {
        Gate::authorize('inventory.view');
        $medicine->load(['batches.creator']);
        $movements = $medicine->movements()->with(['batch', 'creator', 'dispensing.prescription'])->scrollPaginate();

        return view('inventory.show', compact('medicine', 'movements'));
    }

    public function updateMedicine(Request $request, Medicine $medicine)
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate(['reorder_level' => self::LEVEL, 'is_active' => ['required', 'boolean']]);
        $medicine->forceFill(['reorder_level' => $data['reorder_level'], 'is_active' => (bool) $data['is_active']])->save();
        activity('inventory')->causedBy($request->user())->performedOn($medicine)->withProperties(['is_active' => $medicine->is_active])->log('inventory.medicine_updated');

        return redirect()->route('inventory.show', $medicine)->with('status', 'Medicine settings saved.');
    }

    public function receive(Request $request, Medicine $medicine)
    {
        Gate::authorize('inventory.manage');
        $today = CarbonImmutable::now('Asia/Manila')->toDateString();
        $data = $request->validate([
            'quantity' => self::QUANTITY, 'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d', 'after:'.$today],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today], 'supplier' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['expiry_date.after' => 'Do not receive stock that is already expired or expires today.']);
        $batch = DB::transaction(fn () => Inventory::receive($medicine, DispensingQuantities::hundredths((string) $data['quantity']), $data, $request->user()));
        activity('inventory')->causedBy($request->user())->performedOn($batch)->withProperties(['medicine_id' => $medicine->id])->log('inventory.stock_received');

        return redirect()->route('inventory.show', $medicine)->with('status', 'Stock received: '.DispensingQuantities::display((string) $batch->quantity_received).'.');
    }

    public function adjust(Request $request, Medicine $medicine)
    {
        Gate::authorize('inventory.manage');
        $data = $request->validate([
            'medicine_batch_id' => ['required', 'integer'], 'direction' => ['required', 'in:add,remove'], 'adjust_quantity' => self::QUANTITY,
            'reason' => ['required', Rule::in(StockMovement::ADJUSTMENT_REASONS)], 'details' => ['nullable', 'string', 'max:200'],
        ]);
        $batch = $medicine->batches()->findOrFail($data['medicine_batch_id']);
        $amount = DispensingQuantities::hundredths((string) $data['adjust_quantity']);
        $reason = $data['reason'].(filled($data['details'] ?? null) ? ': '.trim($data['details']) : '');
        DB::transaction(fn () => Inventory::adjust($batch, $data['direction'] === 'add' ? $amount : -$amount, $reason, $request->user()));
        activity('inventory')->causedBy($request->user())->performedOn($batch)->withProperties(['medicine_id' => $medicine->id])->log('inventory.adjusted');

        return redirect()->route('inventory.show', $medicine)->with('status', 'Stock adjusted.');
    }

    // Status filters run in SQL so they combine with search and scrolling. Date columns may carry a time part,
    // so comparisons use strict bounds against plain dates.
    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === null) {
            return;
        }
        $today = CarbonImmutable::now('Asia/Manila')->toDateString();
        $usable = 'COALESCE((SELECT SUM(b.quantity_remaining) FROM medicine_batches b WHERE b.medicine_id = medicines.id AND (b.expiry_date IS NULL OR b.expiry_date >= ?)), 0)';
        match ($status) {
            'out' => $query->whereRaw("$usable = 0", [$today]),
            'low' => $query->whereRaw("$usable > 0", [$today])->whereRaw("$usable <= medicines.reorder_level", [$today]),
            'in_stock' => $query->whereRaw("$usable > medicines.reorder_level", [$today]),
            'expiring' => $query->whereHas('batches', fn ($batch) => $batch->where('quantity_remaining', '>', 0)->where('expiry_date', '>=', $today)
                ->where('expiry_date', '<', CarbonImmutable::parse($today)->addDays(Medicine::EXPIRY_WARNING_DAYS + 1)->toDateString())),
            'expired' => $query->whereHas('batches', fn ($batch) => $batch->where('quantity_remaining', '>', 0)->where('expiry_date', '<', $today)),
        };
    }
}
