<?php

namespace Tests\Feature;

use App\Exceptions\ExportUnavailable;
use App\Models\User;
use App\Models\Visit;
use App\Services\ExportSafety;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@rhu.test')->firstOrFail());
    }

    public function test_busy_export_is_stopped_before_generation_with_html_and_json_messages(): void
    {
        $lock = Cache::lock(ExportSafety::LOCK_KEY, 300);
        $this->assertTrue($lock->get());
        try {
            $this->get('/reports/pdf')->assertStatus(503)->assertSee('Download paused')->assertSee('Another PDF is being prepared')->assertHeader('Retry-After', '15');
            $this->getJson('/reports/pdf')->assertStatus(503)->assertJsonStructure(['message']);
            $this->assertSame(0, Activity::where('description', 'report.exported')->count());
            $this->assertFalse(Cache::lock(ExportSafety::LOCK_KEY, 300)->get());
        } finally {
            $lock->release();
        }
    }

    public function test_low_memory_is_rejected_before_work_and_releases_lock(): void
    {
        $this->app->bind(ExportSafety::class, fn () => new class extends ExportSafety
        {
            protected function memoryLimitBytes(): ?int
            {
                return 128 * 1024 * 1024;
            }

            protected function usedMemoryBytes(): int
            {
                return 100 * 1024 * 1024;
            }
        });
        $this->getJson('/reports/pdf')->assertStatus(503)->assertJsonPath('message', 'There is not enough memory headroom to start this PDF safely. Try a smaller selection or Summary only. If this continues, contact the system administrator.');
        $lock = Cache::lock(ExportSafety::LOCK_KEY, 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_content_size_protects_both_pdf_endpoints_without_export_audit(): void
    {
        config(['export_safety.maximum_html_bytes' => 1]);
        $this->get('/reports/pdf')->assertStatus(422)->assertSee('too much content');
        $this->actingAs(User::where('email', 'doctor@rhu.test')->firstOrFail());
        $visit = Visit::firstOrFail();
        $this->get(route('visits.pdf', [$visit->patient, $visit]))->assertStatus(422)->assertSee('too much content');
        $this->assertSame(0, Activity::whereIn('description', ['report.exported', 'visit.summary_exported'])->count());
    }

    public function test_repeated_attempts_are_limited_and_validation_still_works(): void
    {
        config(['export_safety.requests_per_minute' => 1]);
        $this->getJson('/reports/pdf?from=bad')->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->getJson('/reports/pdf')->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs(User::where('email', 'nurse@rhu.test')->firstOrFail());
        $this->getJson('/reports/pdf')->assertForbidden();
    }

    public function test_unexpected_errors_have_a_safe_message_and_release_the_lock(): void
    {
        try {
            app(ExportSafety::class)->run(auth()->id(), fn () => throw new \RuntimeException('PRIVATE-DOCUMENT-CONTENT'));
            $this->fail('Expected export rejection.');
        } catch (ExportUnavailable $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertStringNotContainsString('PRIVATE-DOCUMENT-CONTENT', $exception->getMessage());
        }
        $lock = Cache::lock(ExportSafety::LOCK_KEY, 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }
}
