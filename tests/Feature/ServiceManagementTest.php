<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin);
    }

    public function test_admin_creates_and_edits_service_using_shared_form_fields(): void
    {
        $this->get('/services')->assertOk()->assertSee('Existing services')->assertSee('service-editor-title');
        $this->from('/services')->post('/services', ['name' => 'Example', 'code' => 'EXAMPLE', 'description' => 'Initial', 'is_active' => '0'])->assertRedirect('/services');
        $service = Service::where('code', 'EXAMPLE')->firstOrFail();
        $this->assertFalse($service->is_active);
        $this->from('/services')->patch(route('services.update', $service), ['name' => 'Updated', 'code' => 'TAMPERED', 'description' => 'Updated description', 'is_active' => '1'])->assertRedirect('/services');
        $this->assertSame('Updated description', $service->fresh()->description);
        $this->assertSame('TAMPERED', $service->fresh()->code);
        $this->assertTrue($service->fresh()->is_active);
    }

    public function test_failed_validation_preserves_correct_editor_and_input(): void
    {
        $service = Service::first();
        $this->from('/services')->patch(route('services.update', $service), ['name' => '', 'description' => 'Keep this text', 'is_active' => '1', 'editing_id' => 999])->assertSessionHasErrors('name')->assertSessionHasInput('editing_id', $service->id)->assertSessionHasInput('description', 'Keep this text');
        $this->get('/services')->assertOk()->assertSee('Keep this text');
        $this->from('/services')->post('/services', ['name' => 'Keep new name', 'code' => '', 'editing_id' => $service->id])->assertSessionHasErrors('code')->assertSessionHasInput('name', 'Keep new name');
        $this->assertNull(session()->getOldInput('editing_id'));
    }

    public function test_unused_service_is_soft_deleted_audited_and_not_recreated_by_seeding(): void
    {
        $service = Service::first();
        $this->delete(route('services.destroy', $service))->assertRedirect('/services');
        $this->assertSoftDeleted('services', ['id' => $service->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'service.deleted', 'subject_id' => $service->id]);
        $serviceCount = Service::withTrashed()->count();
        $this->seed(ServiceSeeder::class);
        $this->assertNull(Service::find($service->id));
        $this->assertSame($serviceCount, Service::withTrashed()->count());
        $this->patch(route('services.update', $service), ['name' => 'Revive', 'is_active' => true])->assertNotFound();
    }

    public function test_non_admin_cannot_delete_service(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('Information Staff');
        $service = Service::first();
        $this->actingAs($staff)->delete(route('services.destroy', $service))->assertForbidden();
        $this->assertNotNull(Service::find($service->id));
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_renaming_default_code_survives_reseeding_and_code_validation_is_enforced(): void
    {
        $service = Service::where('code', 'CONSULTATION')->firstOrFail();
        $data = ['name' => $service->name, 'code' => 'CONSULT', 'is_active' => true];
        $this->from('/services')->patch(route('services.update', $service), $data)->assertSessionHasNoErrors();
        $serviceCount = Service::withTrashed()->count();
        $this->seed(ServiceSeeder::class);
        $this->assertSame('CONSULT', $service->fresh()->code);
        $this->assertSame($serviceCount, Service::withTrashed()->count());
        $this->from('/services')->patch(route('services.update', $service), $data)->assertSessionHasNoErrors();
        foreach (['LABORATORY', 'invalid code', ''] as $code) {
            $this->from('/services')->patch(route('services.update', $service), [...$data, 'code' => $code])->assertSessionHasErrors('code');
        }
        $this->assertSame('CONSULT', $service->fresh()->code);
        $this->assertDatabaseHas('activity_log', ['description' => 'service.updated', 'subject_id' => $service->id]);
    }
}
