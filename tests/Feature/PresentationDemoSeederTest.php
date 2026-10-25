<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\DispensingItem;
use App\Models\LaboratoryRecord;
use App\Models\MedicalCertificate;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\PresentationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PresentationDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_history_and_an_open_presentation_day_with_every_workflow_step(): void
    {
        $this->seed(PresentationDemoSeeder::class);
        $day = '2026-10-26';

        $this->assertSame('2026-08-03', Visit::min('visit_date') === null ? null : substr(Visit::min('visit_date'), 0, 10));
        $this->assertSame($day, substr(Visit::max('visit_date'), 0, 10));
        $this->assertSame(0, Visit::whereDate('visit_date', '<', $day)->where('status', Visit::OPEN)->count());
        $today = Visit::whereDate('visit_date', $day)->get();
        $this->assertGreaterThanOrEqual(18, $today->count());
        $this->assertTrue($today->every(fn (Visit $visit) => $visit->isOpen()));
        $this->assertSame($today->count(), (int) DB::table('queue_days')->where('queue_date', $day)->value('last_number'));
        $this->assertSame($today->count(), $today->pluck('queue_reference')->unique()->count());

        $ids = $today->pluck('id');
        $this->assertTrue($today->contains('priority', 'EMERGENCY'));
        $this->assertTrue($today->contains('priority_reason', 'SENIOR'));
        $this->assertGreaterThan(0, Visit::whereIn('id', $ids)->doesntHave('vitalSign')->count());
        $this->assertSame(1, AssessmentRequest::whereIn('visit_id', $ids)->where('status', 'PENDING')->count());
        $this->assertTrue(Prescription::whereIn('visit_id', $ids)->where('status', Prescription::DRAFT)->exists());
        $this->assertTrue(Prescription::whereIn('visit_id', $ids)->issued()->doesntHave('activeDispensings')->exists());
        $this->assertTrue(DispensingItem::whereNotNull('released_as')->where('remainder_outcome', 'BUY_OUTSIDE')->exists());
        $this->assertTrue(LaboratoryRecord::whereIn('visit_id', $ids)->where('availability_status', 'PENDING')->whereNotNull('nurse_authorization_id')->exists());
        $this->assertTrue(LaboratoryRecord::whereIn('visit_id', $ids)->where('availability_status', 'PERFORMED_RHU')->whereNotNull('result_rows')->exists());
        $this->assertTrue(MedicalCertificate::whereIn('visit_id', $ids)->exists());
        $this->assertGreaterThanOrEqual(3, Visit::whereDate('follow_up_on', $day)->count());
        $this->assertSame(0, Visit::whereDate('follow_up_on', '>=', $day)->whereNotNull('follow_up_notified_at')->count());
        $this->assertGreaterThan(150, Patient::count());

        // Running it again changes nothing.
        $visits = Visit::count();
        $this->seed(PresentationDemoSeeder::class);
        $this->assertSame($visits, Visit::count());

        // Every role can open its dashboard on the seeded data.
        foreach (['admin', 'information', 'nurse', 'doctor', 'medtech', 'pharmacist', 'midwife'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->get('/dashboard')->assertOk();
        }
    }

    public function test_refuses_to_mix_with_existing_records(): void
    {
        Patient::forceCreate(['first_name' => 'Existing', 'last_name' => 'Fictional', 'duplicate_key' => 'existing', 'created_by' => User::factory()->create()->id]);
        $this->expectException(LogicException::class);
        $this->seed(PresentationDemoSeeder::class);
    }
}
