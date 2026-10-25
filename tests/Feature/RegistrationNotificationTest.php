<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\RegistrationAlert;
use App\Services\QueueNumbers;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class RegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->assignRole($role);

        return $user;
    }

    public function test_registration_alerts_reach_administrative_staff_and_respect_current_access(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $actor = $this->staff('Information Staff');
        $staff = $this->staff('Information Staff');
        $admin = $this->staff('System Admin');
        $inactive = $this->staff('Information Staff', false);
        $nurse = $this->staff('Nurse');
        $pharmacist = $this->staff('Pharmacist');
        $payload = ['first_name' => 'Fictional', 'last_name' => 'Registration', 'birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example', 'municipality' => 'Calasiao', 'province' => 'Pangasinan'];
        $this->actingAs($actor)->post('/patients', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $patient = Patient::latest('id')->firstOrFail();
        $staffAlert = $staff->notifications()->sole();
        $adminAlert = $admin->notifications()->sole();
        $this->assertSame(RegistrationAlert::class, $staffAlert->type);
        foreach ([$actor, $inactive, $nurse, $pharmacist] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $this->actingAs($staff)->getJson(route('notifications.panel'))->assertOk()
            ->assertJsonPath('items.0.headline', 'New patient registered: '.$patient->full_name);
        $this->get(route('notifications.open', $staffAlert->id))->assertRedirect(route('patients.show', $patient));
        $this->actingAs($admin)->getJson(route('notifications.poll'))->assertOk()
            ->assertJsonPath('unread', 1)->assertJsonPath('latest.0.headline', 'New patient registered')->assertJsonPath('latest.0.url', null);
        $this->get(route('notifications.open', $adminAlert->id))->assertForbidden();
        $this->get(route('notifications.open', $staffAlert->id))->assertNotFound();

        // Rejected duplicate submissions must not announce another registration.
        $this->actingAs($actor)->post('/patients', $payload)->assertStatus(422);
        $this->assertSame(1, $staff->notifications()->count());

        $reservation = app(QueueNumbers::class)->reserve($patient, $actor);
        $visitData = ['service_id' => Service::firstOrFail()->id, 'visit_date' => now('Asia/Manila')->toDateString(), 'queue_token' => $reservation->token];
        $this->post(route('patients.visits.store', $patient), $visitData)->assertSessionHasNoErrors()->assertRedirect();
        $visit = Visit::latest('id')->firstOrFail();
        $this->post(route('patients.visits.store', $patient), $visitData)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $staff->notifications()->count(), 'Retrying a consumed queue reservation must not duplicate alerts.');
        $this->assertSame(2, $admin->notifications()->count());
        $this->assertSame(1, $nurse->notifications()->count(), 'The clinical assessment alert remains intact.');
        foreach ([$actor, $inactive, $pharmacist] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $visitAlert = $admin->notifications()->get()->firstWhere('data.visit_id', $visit->id);
        $this->actingAs($admin)->get(route('notifications.open', $visitAlert->id))->assertRedirect(route('visits.monitor', [$patient, $visit]));
        $this->get(route('visits.monitor', [$patient, $visit]))->assertOk();
        $staffVisitAlert = $staff->notifications()->get()->firstWhere('data.visit_id', $visit->id);
        $this->actingAs($staff)->get(route('notifications.open', $staffVisitAlert->id))->assertRedirect(route('patients.visits.show', [$patient, $visit]));
        $this->assertStringNotContainsString('Fictional', DatabaseNotification::pluck('data')->toJson());

        $staff->syncRoles(['Pharmacist']);
        $this->actingAs($staff->fresh())->getJson(route('notifications.panel'))->assertOk()->assertDontSee($patient->full_name);
        $this->get(route('notifications.open', $staffAlert->id))->assertForbidden();
        $this->get(route('notifications.open', $staffVisitAlert->id))->assertForbidden();
    }
}
