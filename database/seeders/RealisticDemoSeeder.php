<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class RealisticDemoSeeder extends Seeder
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
            foreach ($profiles as $index => [$first, $last, $birth, $sex, $diagnosis, $systolic, $diastolic, $weight, $height]) {
                $number = sprintf('SYNTHETIC-PATIENT-%03d', $index + 1);
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
                    'created_at' => now('Asia/Manila')->subDays(28 + $index)->startOfDay()->addHours(8),
                    'updated_at' => now('Asia/Manila')->subDays(28 + $index)->startOfDay()->addHours(8),
                ])->save();
                foreach ([28 + $index, $index % 4] as $sequence => $daysAgo) {
                    $date = now('Asia/Manila')->subDays($daysAgo)->startOfDay()->addHours(8)->addMinutes($index * 15);
                    $visit = new Visit;
                    $visit->forceFill([
                        'patient_id' => $patient->id, 'service_id' => $service->id,
                        'visit_number' => sprintf('SYNTHETIC-VISIT-%03d-%d', $index + 1, $sequence + 1),
                        'visit_date' => $date->toDateString(), 'status' => 'OPEN', 'created_by' => $staff->id,
                        'created_at' => $date, 'updated_at' => $date,
                    ])->save();
                    $vitals = new VitalSign;
                    $vitals->forceFill([
                        'visit_id' => $visit->id, 'systolic_bp' => $systolic - $sequence * 2,
                        'diastolic_bp' => $diastolic, 'temperature' => 36.5 + ($index % 3) * 0.2,
                        'pulse_rate' => 70 + $index * 2, 'respiratory_rate' => 16 + $index % 3,
                        'oxygen_saturation' => 98, 'height_cm' => $height, 'weight_kg' => $weight,
                        'recorded_by' => $nurse->id, 'updated_by' => $nurse->id, 'recorded_at' => $date->copy()->addMinutes(10),
                        'created_at' => $date, 'updated_at' => $date,
                    ])->save();
                    $itr = new TreatmentRecord;
                    $itr->forceFill([
                        'visit_id' => $visit->id, 'diagnoses' => [$diagnosis],
                        'assessment' => ($sequence ? 'Return consultation: ' : 'Initial consultation: ').$diagnosis.'. Synthetic demonstration chart.',
                        'planning' => 'Sample documentation of review and follow-up discussion; no treatment instructions supplied.',
                        'remarks' => 'Entirely fictional patient and encounter. Diagnosis labels are illustrative, not RHU statistics.',
                        'objective_snapshot' => $vitals->only(VitalSign::FIELDS),
                        'created_by' => $doctor->id, 'updated_by' => $doctor->id,
                        'created_at' => $date->copy()->addMinutes(25), 'updated_at' => $date->copy()->addMinutes(25),
                    ])->save();
                    activity('demo')->performedOn($visit)->withProperties(['source' => 'RealisticDemoSeeder'])->log('demo.workflow_seeded');
                }
            }
        });
        $this->command?->info('Synthetic examples ready: search SYNTHETIC-PATIENT or Demo. Existing records were preserved.');
    }
}
