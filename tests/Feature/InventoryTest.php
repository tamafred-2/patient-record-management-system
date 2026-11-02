<?php

namespace Tests\Feature;

use App\Models\DispensingItem;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $pharmacist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->pharmacist = User::where('email', 'pharmacist@rhu.test')->firstOrFail();
        $this->actingAs($this->pharmacist);
    }

    private function medicine(string $name = 'Fictional Paracetamol', ?string $strength = '500 mg', string $level = '10'): Medicine
    {
        $this->post(route('inventory.store'), ['name' => $name, 'strength' => $strength, 'form' => 'Tablet', 'reorder_level' => $level])->assertSessionHasNoErrors();

        return Medicine::where('lookup_key', Medicine::lookupKey($name, $strength, 'Tablet'))->firstOrFail();
    }

    private function receive(Medicine $medicine, string $quantity, ?string $expiry, string $batch): void
    {
        $this->post(route('inventory.receive', $medicine), ['quantity' => $quantity, 'expiry_date' => $expiry, 'received_on' => now('Asia/Manila')->toDateString(), 'batch_number' => $batch, 'supplier' => 'Fictional supplier'])
            ->assertSessionHasNoErrors();
    }

    public function test_pharmacist_adds_medicines_and_receives_stock_in_batches(): void
    {
        $this->get(route('inventory.index'))->assertOk()->assertSee('Add medicine')->assertSee('Fictional demonstration medicine');
        $medicine = $this->medicine();
        $this->post(route('inventory.store'), ['name' => '  fictional  PARACETAMOL ', 'strength' => '500 MG', 'form' => 'Tablet', 'reorder_level' => '5'])->assertSessionHasErrors('name');
        $this->post(route('inventory.store'), ['name' => '', 'form' => 'Powder', 'reorder_level' => '-1'])->assertSessionHasErrors(['name', 'form', 'reorder_level']);
        $this->receive($medicine, '100', now('Asia/Manila')->addYear()->toDateString(), 'LOT-A');
        $this->post(route('inventory.receive', $medicine), ['quantity' => '5', 'expiry_date' => now('Asia/Manila')->toDateString(), 'received_on' => now('Asia/Manila')->toDateString()])->assertSessionHasErrors('expiry_date');
        $this->post(route('inventory.receive', $medicine), ['quantity' => '2.5', 'received_on' => now('Asia/Manila')->addDay()->toDateString()])->assertSessionHasErrors(['quantity', 'received_on']);
        $this->assertSame(10000, $medicine->fresh()->available());
        $this->get(route('inventory.show', $medicine))->assertOk()->assertSee('LOT-A')->assertSee('Stock received')->assertSee('+100')->assertSee('100 / 100');
        $this->assertSame(1, Activity::where('description', 'inventory.stock_received')->count());
        $this->assertStringNotContainsString('Fictional supplier', Activity::all()->toJson());
    }

    public function test_release_uses_earliest_expiry_first_skips_expired_stock_and_rejects_shortages(): void
    {
        $medicine = $this->medicine('Fictional FEFO medicine', null);
        $later = now('Asia/Manila')->addMonths(6)->toDateString();
        $sooner = now('Asia/Manila')->addMonth()->toDateString();
        $this->receive($medicine, '3', $later, 'LATER');
        $this->receive($medicine, '2', $sooner, 'SOONER');
        $expired = MedicineBatch::forceCreate(['medicine_id' => $medicine->id, 'batch_number' => 'EXPIRED', 'expiry_date' => now('Asia/Manila')->subDay()->toDateString(), 'received_on' => now('Asia/Manila')->subYear()->toDateString(),
            'quantity_received' => 50, 'quantity_remaining' => 50, 'created_by' => $this->pharmacist->id]);
        $this->assertSame(500, $medicine->fresh()->available());

        $visit = Visit::where('visit_number', 'DEMO-VISIT-1')->firstOrFail();
        $rx = $visit->prescription;
        $item = $rx->items()->firstOrFail();
        $url = route('pharmacy.show', [$visit->patient, $visit]);
        $this->get($url)->assertOk()->assertSee('Release from stock')->assertSee('5 available');
        $data = ['lock_version' => $rx->lock_version, 'confirm' => 1, 'quantities' => [$item->id => '6'], 'medicine_ids' => [$item->id => $medicine->id]];
        $this->post($url, $data)->assertSessionHasErrors();
        $this->assertSame(0, StockMovement::where('type', 'RELEASED')->count());
        $this->post($url, [...$data, 'medicine_ids' => []])->assertSessionHasErrors();

        $this->post($url, [...$data, 'quantities' => [$item->id => '4']])->assertSessionHasNoErrors();
        $this->assertSame('0.00', MedicineBatch::where('batch_number', 'SOONER')->value('quantity_remaining'));
        $this->assertSame('1.00', MedicineBatch::where('batch_number', 'LATER')->value('quantity_remaining'));
        $this->assertSame('50.00', $expired->fresh()->quantity_remaining);
        $this->assertSame(100, $medicine->fresh()->available());
        $line = DispensingItem::where('medicine_id', $medicine->id)->sole();
        $this->assertSame('4.00', $line->quantity_dispensed);
        $this->assertSame(['-2.00', '-2.00'], StockMovement::where('type', 'RELEASED')->orderBy('id')->pluck('quantity')->all());

        // Voiding the release puts the same quantities back into the same batches.
        $release = $rx->dispensings()->latest('id')->firstOrFail();
        $this->post(route('pharmacy.void', [$visit->patient, $visit, $release]), ['confirm' => 1, 'reason' => 'Fictional wrong entry', 'lock_version' => $rx->fresh()->lock_version])->assertSessionHasNoErrors();
        $this->assertSame('2.00', MedicineBatch::where('batch_number', 'SOONER')->value('quantity_remaining'));
        $this->assertSame('3.00', MedicineBatch::where('batch_number', 'LATER')->value('quantity_remaining'));
        $this->assertSame(2, StockMovement::where('type', 'RETURNED')->count());
        $this->get(route('inventory.show', $medicine))->assertSee('Returned (release voided)')->assertSee('Prescription '.$rx->prescription_number);
    }

    public function test_substitute_stock_fills_released_as_automatically(): void
    {
        $generic = $this->medicine('Fictional generic equivalent', '250 mg');
        $this->receive($generic, '20', now('Asia/Manila')->addYear()->toDateString(), 'GEN-1');
        $visit = Visit::where('visit_number', 'DEMO-VISIT-1')->firstOrFail();
        $rx = $visit->prescription;
        $item = $rx->items()->firstOrFail();
        $this->post(route('pharmacy.store', [$visit->patient, $visit]), ['lock_version' => $rx->lock_version, 'confirm' => 1, 'quantities' => [$item->id => '1'], 'medicine_ids' => [$item->id => $generic->id]])->assertSessionHasNoErrors();
        $this->assertSame('Fictional generic equivalent 250 mg (Tablet)', DispensingItem::where('medicine_id', $generic->id)->value('released_as'));
    }

    public function test_adjustments_cannot_make_stock_negative_and_need_a_reason(): void
    {
        $medicine = $this->medicine();
        $this->receive($medicine, '10', now('Asia/Manila')->addYear()->toDateString(), 'LOT-A');
        $batch = $medicine->batches()->firstOrFail();
        $url = route('inventory.adjust', $medicine);
        $this->post($url, ['medicine_batch_id' => $batch->id, 'direction' => 'remove', 'adjust_quantity' => '11', 'reason' => 'Damaged'])->assertSessionHasErrors('adjust_quantity');
        $this->post($url, ['medicine_batch_id' => $batch->id, 'direction' => 'remove', 'adjust_quantity' => '3'])->assertSessionHasErrors('reason');
        $this->post($url, ['medicine_batch_id' => $batch->id, 'direction' => 'remove', 'adjust_quantity' => '3', 'reason' => 'Damaged', 'details' => 'Wet packaging'])->assertSessionHasNoErrors();
        $this->post($url, ['medicine_batch_id' => $batch->id, 'direction' => 'add', 'adjust_quantity' => '1', 'reason' => 'Physical count correction'])->assertSessionHasNoErrors();
        $this->assertSame('8.00', $batch->fresh()->quantity_remaining);
        $this->assertSame('Damaged: Wet packaging', StockMovement::where('type', 'ADJUSTED')->orderBy('id')->value('reason'));
        $other = $this->medicine('Fictional other medicine');
        $this->post(route('inventory.adjust', $other), ['medicine_batch_id' => $batch->id, 'direction' => 'add', 'adjust_quantity' => '1', 'reason' => 'Other'])->assertNotFound();
    }

    public function test_status_filters_and_summary(): void
    {
        $low = $this->medicine('Fictional low medicine', null, '10');
        $this->receive($low, '5', now('Asia/Manila')->addYear()->toDateString(), 'LOW');
        $out = $this->medicine('Fictional out medicine', null);
        $expiring = $this->medicine('Fictional expiring medicine', null, '1');
        $this->receive($expiring, '50', now('Asia/Manila')->addDays(30)->toDateString(), 'SOON');
        MedicineBatch::forceCreate(['medicine_id' => $expiring->id, 'batch_number' => 'OLD', 'expiry_date' => now('Asia/Manila')->subDays(3)->toDateString(), 'received_on' => now('Asia/Manila')->subYear()->toDateString(),
            'quantity_received' => 4, 'quantity_remaining' => 4, 'created_by' => $this->pharmacist->id]);
        $this->assertSame(['low'], $low->fresh()->statuses());
        $this->assertSame(['out'], $out->fresh()->statuses());
        $this->assertSame(['in_stock', 'expiring', 'expired'], $expiring->fresh()->statuses());
        $this->get(route('inventory.index', ['status' => 'low']))->assertSee('Fictional low medicine')->assertDontSee('Fictional out medicine');
        $this->get(route('inventory.index', ['status' => 'out']))->assertSee('Fictional out medicine')->assertDontSee('Fictional low medicine');
        $this->get(route('inventory.index', ['status' => 'expiring']))->assertSee('Fictional expiring medicine')->assertDontSee('Fictional low medicine');
        $this->get(route('inventory.index', ['status' => 'expired']))->assertSee('Fictional expiring medicine')->assertDontSee('Fictional out medicine');
        $this->get(route('inventory.index', ['status' => 'in_stock', 'search' => 'fictional']))->assertSee('Fictional expiring medicine')->assertDontSee('Fictional low medicine');
        $this->get(route('inventory.index', ['status' => 'invented']))->assertSessionHasErrors('status');

        // Inactive medicines leave the active list and the release choices.
        $this->put(route('inventory.update', $low), ['reorder_level' => '2', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->get(route('inventory.index', ['active' => 'active']))->assertDontSee('Fictional low medicine');
        $this->get(route('inventory.index'))->assertSee('Fictional low medicine')->assertSee('Inactive');
        $this->get(route('inventory.index', ['active' => 'inactive']))->assertSee('Fictional low medicine');
        $visit = Visit::where('visit_number', 'DEMO-VISIT-1')->firstOrFail();
        $this->get(route('pharmacy.show', [$visit->patient, $visit]))->assertDontSee('Fictional low medicine');
    }

    public function test_only_pharmacy_staff_can_open_or_change_inventory(): void
    {
        $medicine = Medicine::firstOrFail();
        foreach (['admin', 'information', 'nurse', 'doctor', 'medtech', 'midwife'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->getJson(route('inventory.index'))->assertForbidden();
            $this->getJson(route('inventory.show', $medicine))->assertForbidden();
            $this->post(route('inventory.store'), ['name' => 'X', 'form' => 'Tablet', 'reorder_level' => 0])->assertForbidden();
            $this->post(route('inventory.receive', $medicine), ['quantity' => 1, 'received_on' => now('Asia/Manila')->toDateString()])->assertForbidden();
        }
        $reader = User::factory()->create();
        $reader->givePermissionTo(['inventory.view']);
        $this->actingAs($reader)->get(route('inventory.show', $medicine))->assertOk()->assertDontSee('Receive stock');
        $this->post(route('inventory.receive', $medicine), ['quantity' => 1, 'received_on' => now('Asia/Manila')->toDateString()])->assertForbidden();
        $this->assertSame(1, MedicineBatch::count());
    }
}
