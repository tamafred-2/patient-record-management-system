<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScrollQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
    }

    private function role(string $role): void
    {
        $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
    }

    public function test_cursor_batches_preserve_filters_order_and_previous_navigation_without_count_query(): void
    {
        $visit = Visit::firstOrFail();
        foreach (range(1, 35) as $number) {
            $copy = $visit->replicate();
            $copy->visit_number = 'SCROLL-'.$number;
            $copy->queue_reference = 'SCROLL-'.$number;
            $copy->save();
        }
        $this->role('information');
        $page = $this->get('/visits?all=1&search=SCROLL')->assertOk();
        $this->assertCount(10, $page->viewData('visits'));
        $this->assertSame(35, $page->viewData('visits')->total());
        $url = $page->viewData('visits')->nextPageUrl();
        $this->assertStringContainsString('search=SCROLL', $url);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $second = $this->withHeaders(['X-Scroll-Queue' => 'visits'])->getJson($url)->assertOk()->assertJsonPath('count', 10);
        $queries = collect(DB::getQueryLog())->pluck('query')->join(' ');
        DB::disableQueryLog();
        $this->assertStringNotContainsString('count(*) as aggregate', strtolower($queries));
        $this->assertStringNotContainsString('<html', $second->json('html'));
        $this->assertStringContainsString('SCROLL-25', $second->json('html'));
        $this->assertStringNotContainsString('SCROLL-35', $second->json('html'));
        $third = $this->getJson($second->json('next'))->assertOk()->assertJsonPath('count', 10);
        $fourth = $this->getJson($third->json('next'))->assertOk()->assertJsonPath('count', 5)->assertJsonPath('next', null);
        $back = $this->getJson($fourth->json('previous'))->assertOk();
        $this->assertSame($third->json('html'), $back->json('html'));
        $this->getJson($second->json('previous'))->assertOk()->assertJsonPath('previous', null)->assertSee('SCROLL-35');
    }

    public function test_patient_name_ties_use_id_and_do_not_skip_or_repeat_records(): void
    {
        $patient = Patient::firstOrFail();
        foreach (range(1, 23) as $number) {
            $copy = $patient->replicate();
            $copy->first_name = 'Fictional Scroll';
            $copy->last_name = 'Identical';
            $copy->patient_number = 'SCROLL-P-'.$number;
            $copy->save();
        }
        $this->role('information');
        $page = $this->get('/patients?q=Fictional%20Scroll')->assertOk();
        $this->assertSame(23, $page->viewData('patients')->total());
        $url = $page->viewData('patients')->nextPageUrl();
        $second = $this->withHeaders(['X-Scroll-Queue' => 'patients'])->getJson($url)->assertOk()->assertJsonPath('count', 10);
        $last = $this->getJson($second->json('next'))->assertOk()->assertJsonPath('count', 3)->assertJsonPath('next', null);
        $this->assertStringContainsString('SCROLL-P-11<', $second->json('html'));
        $this->assertStringNotContainsString('SCROLL-P-10<', $second->json('html'));
        $this->assertStringContainsString('SCROLL-P-23<', $last->json('html'));
    }

    public function test_card_batches_keep_parent_scoping_and_do_not_include_entry_forms(): void
    {
        $visit = Visit::firstOrFail();
        foreach (range(1, 22) as $number) {
            LaboratoryRecord::forceCreate(['visit_id' => $visit->id, 'test_name' => 'Urinalysis', 'availability_status' => 'PENDING', 'created_by' => User::firstOrFail()->id, 'updated_by' => User::firstOrFail()->id, 'notes' => 'Fictional scroll record '.$number, 'lock_version' => 1, 'submission_token' => (string) Str::uuid()]);
        }
        $this->role('medtech');
        $page = $this->get(route('laboratory.show', [$visit->patient, $visit]))->assertOk();
        $this->assertCount(10, $page->viewData('records'));
        $next = $this->withHeaders(['X-Scroll-Queue' => 'records'])->getJson($page->viewData('records')->nextPageUrl())->assertOk()->assertJsonPath('count', 10);
        $this->assertStringContainsString('data-queue-items', $next->json('html'));
        $this->assertStringNotContainsString('<form', $next->json('html'));
        $other = Visit::where('patient_id', '!=', $visit->patient_id)->firstOrFail();
        $this->getJson(route('laboratory.show', [$other->patient, $visit]))->assertNotFound();
        $this->role('nurse');
        $this->getJson($page->viewData('records')->nextPageUrl())->assertForbidden();
    }

    public function test_all_module_feeds_render_and_unknown_feed_is_rejected(): void
    {
        foreach ([['information', '/patients', 'patients'], ['information', '/visits', 'visits'], ['nurse', '/assessments', 'visits'], ['doctor', '/consultations', 'visits'], ['admin', '/eligibility', 'visits'], ['admin', '/staff', 'users'], ['admin', '/audit', 'events'], ['medtech', '/laboratory', 'visits'], ['pharmacist', '/pharmacy', 'visits'], ['midwife', '/midwife-care', 'visits'], ['midwife', '/vaccinations', 'visits']] as [$role, $url, $id]) {
            $this->role($role);
            $this->withHeaders(['X-Scroll-Queue' => $id])->getJson($url)->assertOk()->assertJsonStructure(['html', 'next', 'previous', 'count']);
        }
        $this->role('information');
        $this->withHeaders(['X-Scroll-Queue' => 'wrong-feed'])->getJson('/visits')->assertStatus(409);
        $this->withHeaders(['X-Scroll-Queue' => 'events'])->getJson('/audit')->assertForbidden();
    }
}
