<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class HistoricalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Synthetic patient records are local/testing only.');
        }
        DB::transaction(function () {
            $this->call(UserSeeder::class);
            $this->call(ServiceSeeder::class);
            $service = Service::where('seed_key', 'CONSULTATION')->where('is_active', true)->firstOrFail();
            $staff = User::where('email', 'information@rhu.test')->firstOrFail();
            $doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
            $nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
            // Invented identities and illustrative chart labels, not RHU patient data or prevalence.
            $profiles = [
                ['Mariel', 'Santos', '1987-04-18', 'Female', 'Hypertension', 138, 86, 62, 156],
                ['Renato', 'Villanueva', '1964-11-09', 'Male', 'Type 2 diabetes mellitus', 128, 82, 75, 168],
                ['Carmina', 'Reyes', '1998-07-23', 'Female', 'Upper respiratory tract infection', 112, 74, 53, 158],
                ['Paolo', 'Mendoza', '2001-02-15', 'Male', 'Allergic rhinitis', 118, 76, 67, 172],
                ['Lourdes', 'Navarro', '1958-06-06', 'Female', 'Hypertension', 142, 88, 58, 152],
                ['Ramon', 'Castillo', '1975-09-28', 'Male', 'Type 2 diabetes mellitus', 130, 84, 79, 169],
                ['Danica', 'Mercado', '1993-12-12', 'Female', 'Upper respiratory tract infection', 110, 72, 55, 160],
                ['Miguel', 'Soriano', '1982-03-05', 'Male', 'Hypertension', 136, 84, 72, 170],
            ];
            for ($index = 0; $index < 48; $index++) {
                [$first, $last, $birth, $sex, $diagnosis, $systolic, $diastolic, $weight, $height] = $profiles[$index % count($profiles)];
                $last = ['Dela Cruz', 'Ramos', 'Garcia', 'Aquino', 'Bautista', 'Fernandez'][intdiv($index, 8)];
                $birth = CarbonImmutable::parse($birth)->subYears(intdiv($index, 8))->toDateString();
                $firstVisit = CarbonImmutable::parse('2026-01-05', 'Asia/Manila')->addDays($index * 3)->setTime(8, ($index % 4) * 15);
                $lastDate = CarbonImmutable::parse('2026-09-28', 'Asia/Manila')->endOfDay();
                $number = sprintf('HISTORY2026-PATIENT-%03d', $index + 1);
                if (Patient::withTrashed()->where('patient_number', $number)->exists()) {
                    continue;
                }
                $patient = new Patient;
                $patient->forceFill([
                    'patient_number' => $number, 'first_name' => $first, 'middle_name' => 'Demo', 'last_name' => $last,
                    'birth_date' => $birth, 'sex' => $sex,
                    'barangay' => 'Demo District '.($index % 3 + 1), 'municipality' => 'Calasiao', 'province' => 'Pangasinan',
                    // No fabricated dialable phone numbers or actual household addresses.
                    'created_by' => $staff->id, 'duplicate_key' => Patient::duplicateKey($first, $last),
                    'created_at' => $firstVisit,
                    'updated_at' => $firstVisit,
                ])->save();
                $dates = [];
                for ($date = $firstVisit; $date->lte($lastDate); $date = $date->addDays((in_array($diagnosis, ['Hypertension', 'Type 2 diabetes mellitus']) ? 28 : 90) + $index % 3)) {
                    $dates[] = $date;
                }
                if ($index === 0) {
                    $dates[] = $lastDate->setTime(9, 0);
                }
                foreach ($dates as $sequence => $date) {
                    $visit = new Visit;
                    $visit->forceFill([
                        'patient_id' => $patient->id, 'service_id' => $service->id,
                        'visit_number' => sprintf('HISTORY2026-VISIT-%03d-%d', $index + 1, $sequence + 1),
                        'visit_date' => $date->toDateString(), 'status' => 'OPEN', 'created_by' => $staff->id,
                        'created_at' => $date, 'updated_at' => $date,
                    ])->save();
                    $vitals = new VitalSign;
                    $vitals->forceFill([
                        'visit_id' => $visit->id, 'systolic_bp' => $systolic + ($sequence % 3 - 1) * 2,
                        'diastolic_bp' => $diastolic, 'temperature' => $diagnosis === 'Upper respiratory tract infection' && $sequence % 2 === 0 ? 38.0 : 36.6,
                        'pulse_rate' => 70 + ($index % 9) * 2, 'respiratory_rate' => 16 + $index % 3,
                        'oxygen_saturation' => 98, 'height_cm' => $height, 'weight_kg' => $weight,
                        'recorded_by' => $nurse->id, 'updated_by' => $nurse->id, 'recorded_at' => $date->copy()->addMinutes(10),
                        'created_at' => $date, 'updated_at' => $date,
                    ])->save();
                    $answers = array_fill_keys(array_keys(TreatmentRecord::QUESTIONS), false);
                    $answers['cough_colds'] = $diagnosis === 'Upper respiratory tract infection';
                    $answers['fever'] = $answers['cough_colds'] && $sequence % 2 === 0;
                    $answers['allergy'] = $index % 11 === 0; // Fictional food/drug allergy history, not inferred from rhinitis.
                    $answers['family_hypertension'] = $index % 3 === 0;
                    $answers['taking_medicine'] = in_array($diagnosis, ['Hypertension', 'Type 2 diabetes mellitus']);
                    // Some forms intentionally omit family history; null is not a negative answer.
                    if (($index + $sequence) % 13 === 0) {
                        $answers['family_hypertension'] = null;
                    }
                    $itr = new TreatmentRecord;
                    $itr->forceFill([
                        ...$answers, 'visit_id' => $visit->id, 'diagnoses' => [$diagnosis],
                        'assessment' => ($sequence ? 'Return consultation: ' : 'Initial consultation: ').$diagnosis.'. Synthetic demonstration chart.',
                        'planning' => 'Sample documentation of review and follow-up discussion; no treatment instructions supplied.',
                        'remarks' => 'Entirely fictional patient and encounter. Diagnosis labels are illustrative, not RHU statistics.'.($answers['allergy'] ? ' Sample history: food allergy reported.' : ''),
                        'medicine_details' => $answers['taking_medicine'] ? 'Patient reports maintenance medicine; medication list not transcribed in this synthetic example.' : null,
                        'objective_snapshot' => $vitals->only(VitalSign::FIELDS),
                        'created_by' => $doctor->id, 'updated_by' => $doctor->id,
                        'created_at' => $date->copy()->addMinutes(25), 'updated_at' => $date->copy()->addMinutes(25),
                    ])->save();
                    activity('demo')->performedOn($visit)->withProperties(['source' => 'HistoricalDemoSeeder'])->log('demo.workflow_seeded');
                }
            }
        });
        $this->call(RandomizeHistoricalDatesSeeder::class);
        $this->call(CompleteHistoricalDemoSeeder::class);
        $this->command?->info('Synthetic examples ready: search HISTORY2026-PATIENT or Demo. Existing records were preserved.');
    }
}
