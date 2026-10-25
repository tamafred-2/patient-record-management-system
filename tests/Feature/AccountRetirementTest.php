<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_retirement_keeps_linked_records_removes_access_and_only_deletes_unlinked_accounts(): void
    {
        $this->seed(WorkflowDemoSeeder::class);
        $admin = User::where('email', 'admin@rhu.test')->sole();
        $staff = User::where('email', 'information@rhu.test')->sole();
        $this->actingAs($staff)->post(route('staff.retire', $admin), ['action' => 'delete', 'confirm' => 1])->assertForbidden();
        $this->actingAs($admin);
        $this->post(route('staff.retire', $admin), ['action' => 'deactivate', 'confirm' => 1])->assertSessionHasErrors('account');
        $this->post(route('staff.retire', $staff), ['action' => 'delete', 'confirm' => 1])->assertSessionHasErrors('account');
        $staff->givePermissionTo('audit.view');
        $this->post(route('staff.retire', $staff), ['action' => 'deactivate', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertCount(0, $staff->fresh()->roles);
        $this->assertCount(0, $staff->fresh()->permissions);
        $this->assertDatabaseHas('patients', ['created_by' => $staff->id]);
        $unused = User::factory()->create();
        $unused->assignRole('Nurse');
        $this->post(route('staff.retire', $unused), ['action' => 'delete'])->assertSessionHasErrors('confirm');
        $this->post(route('staff.retire', $unused), ['action' => 'delete', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $unused->id]);
    }
}
