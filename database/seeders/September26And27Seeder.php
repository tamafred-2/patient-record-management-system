<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Additive, fictional registration fixtures; not evidence of care delivered. */
class September26And27Seeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('September demo visits are local/testing only.');
        }

        DB::transaction(function () {
            $this->call([UserSeeder::class, ServiceSeeder::class]);
            $registrar = User::where('email', 'information@rhu.test')->firstOrFail();
            $services = Service::where('is_active', true)->get()->keyBy('seed_key');
            $keys = ['CONSULTATION', 'LABORATORY', 'MEDICAL_CERTIFICATE', 'MIDWIFE_CARE', 'VACCINATION', 'OTHER'];
            $firstNames = [
                '2026-09-26' => ['Elisa', 'Marco', 'Diana', 'Renato', 'Clara', 'Joel'],
                '2026-09-27' => ['Lucia', 'Ramon', 'Teresa', 'Luis', 'Marta', 'Carlo'],
            ];
            $lastNames = ['Santos', 'Reyes', 'Cruz', 'Garcia', 'Ramos'];
            $created = 0;

            foreach (['2026-09-26' => 'SA', '2026-09-27' => 'SB'] as $date => $prefix) {
                for ($slot = 0; $slot < 30; $slot++) {
                    $service = $services->get($keys[$slot % 6]);
                    if (! $service) {
                        continue; // Preserve disabled/archived service settings.
                    }
                    $marker = sprintf('DEMO-%s-%03d', str_replace('-', '', $date), $slot + 1);
                    // Skip the whole fixture, including archived or manually edited profiles.
                    if (Patient::withTrashed()->where('patient_number', $marker)->exists()
                        || Visit::where('visit_number', $marker)->exists()) {
                        continue;
                    }

                    $time = CarbonImmutable::parse($date, 'Asia/Manila')->setTime(8, 0)->addMinutes($slot * 8);
                    $first = $firstNames[$date][$slot % 6];
                    $last = $lastNames[intdiv($slot, 6)];
                    $patient = new Patient;
                    $patient->forceFill([
                        'patient_number' => $marker, 'first_name' => $first, 'middle_name' => 'Demo',
                        'last_name' => $last, 'birth_date' => sprintf('%04d-03-%02d', 1975 + $slot, 1 + $slot % 28),
                        'sex' => $slot % 2 === 0 ? 'Female' : 'Male',
                        'municipality' => 'Calasiao', 'province' => 'Pangasinan',
                        'duplicate_key' => Patient::duplicateKey($first, $last), 'created_by' => $registrar->id,
                        'created_at' => $time, 'updated_at' => $time,
                    ])->save();
                    $visit = new Visit;
                    $visit->forceFill([
                        'patient_id' => $patient->id, 'service_id' => $service->id, 'visit_number' => $marker,
                        'visit_date' => $date, 'queue_reference' => sprintf('%s-%03d', $prefix, $slot + 1),
                        'status' => 'OPEN', 'created_by' => $registrar->id,
                        'created_at' => $time, 'updated_at' => $time,
                    ])->save();
                    $created++;
                }
            }

            if ($created > 0) {
                activity('demo')->withProperties(['source' => self::class, 'visits_created' => $created])
                    ->log('seed.september_26_27_added');
            }
            $this->command?->info("Added {$created} fictional open visits for September 26 and 27, 2026. Existing records preserved.");
        });
    }
}
