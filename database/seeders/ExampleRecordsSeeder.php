<?php

namespace Database\Seeders;

use App\Models\Dispensing;
use App\Models\DispositionRecord;
use App\Models\EligibilityCheck;
use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ExampleRecordsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Example records are local/testing only.');
        }

        DB::transaction(function () {
            $this->call(WorkflowDemoSeeder::class);
            $users = User::whereIn('email', array_keys(UserSeeder::ACCOUNTS))->get()->keyBy('email');
            $scenarios = [
                ['Draft', 'CONSULTATION', 'DRAFT', false, null],
                ['Waiting', 'CONSULTATION', 'ISSUED', true, 'FORMAL_REFERRAL'],
                ['Partial', 'CONSULTATION', 'PARTIAL', true, 'ADVISED_HIGHER_FACILITY'],
                ['Released', 'CONSULTATION', 'FULL', false, 'EMERGENCY_REFERRAL'],
                ['Laboratory', 'LABORATORY', null, true, null],
                ['Certificate', 'MEDICAL_CERTIFICATE', null, false, 'CERTIFICATE_REQUEST'],
            ];
            foreach ($scenarios as [$name, $serviceKey, $prescriptionState, $confirmed, $disposition]) {
                $number = 'EXAMPLE-PATIENT-'.strtoupper($name);
                // Never add to or overwrite an existing (including archived) example profile.
                if (Patient::withTrashed()->where('patient_number', $number)->exists()) {
                    continue;
                }
                $service = Service::where('seed_key', $serviceKey)->where('is_active', true)->first();
                if (! $service) {
                    $this->command?->warn("Skipped {$name}: {$serviceKey} is unavailable. Existing service settings were preserved.");

                    continue;
                }
                $staff = $users['information@rhu.test'];
                $doctor = $users['doctor@rhu.test'];
                $nurse = $users['nurse@rhu.test'];
                $admin = $users['admin@rhu.test'];
                $medtech = $users['medtech@rhu.test'];
                $pharmacist = $users['pharmacist@rhu.test'];
                $patient = new Patient;
                $patient->forceFill([
                    'patient_number' => $number, 'first_name' => 'Fictional '.$name, 'last_name' => 'Example',
                    'birth_date' => '1990-01-01', 'sex' => 'Female', 'barangay' => 'Fictional barangay',
                    'municipality' => 'Fictional municipality', 'province' => 'Fictional province',
                    'created_by' => $staff->id,
                    'duplicate_key' => Patient::duplicateKey('Fictional '.$name, 'Example'),
                ])->save();
                $visit = new Visit;
                $visit->forceFill([
                    'patient_id' => $patient->id, 'service_id' => $service->id,
                    'visit_number' => 'EXAMPLE-VISIT-'.strtoupper($name),
                    'visit_date' => now('Asia/Manila')->toDateString(), 'status' => 'OPEN', 'created_by' => $staff->id,
                ])->save();
                $vitals = new VitalSign;
                $vitals->forceFill([
                    'visit_id' => $visit->id, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'temperature' => 36.5,
                    'pulse_rate' => 70, 'respiratory_rate' => 18, 'oxygen_saturation' => 98, 'height_cm' => 165, 'weight_kg' => 60,
                    'recorded_by' => $nurse->id, 'updated_by' => $nurse->id, 'recorded_at' => now(),
                ])->save();
                $check = new EligibilityCheck;
                $check->forceFill([
                    'visit_id' => $visit->id, 'status' => 'CHECKBOX', 'philhealth_confirmed' => $confirmed,
                    'verified_by' => $admin->id, 'updated_by' => $admin->id, 'verified_at' => now(),
                    'remarks' => 'Fictional example; no external system was checked.',
                ])->save();
                $itr = new TreatmentRecord;
                $itr->forceFill([
                    'visit_id' => $visit->id, 'assessment' => 'Fictional '.$name.' example. No clinical advice.',
                    'planning' => 'Demonstrate the record workflow only.', 'remarks' => 'Seeded fictional record.',
                    'asthma' => false, 'fever' => true, 'cough_colds' => false,
                    'objective_snapshot' => $vitals->only(VitalSign::FIELDS),
                    'created_by' => $doctor->id, 'updated_by' => $doctor->id,
                ])->save();
                if ($prescriptionState) {
                    $rx = new Prescription;
                    $rx->forceFill([
                        'visit_id' => $visit->id, 'treatment_record_id' => $itr->id, 'doctor_id' => $doctor->id,
                        'prescription_number' => 'EXAMPLE-RX-'.strtoupper($name),
                        'status' => $prescriptionState === 'DRAFT' ? 'DRAFT' : 'ISSUED',
                        'prescribed_at' => $prescriptionState === 'DRAFT' ? null : now(),
                    ])->save();
                    $items = [];
                    foreach (['Alpha', 'Beta'] as $medicine) {
                        $item = $rx->items()->create([
                            'medicine_name' => 'Fictional '.$medicine.' medicine', 'strength' => 'Example only',
                            'dosage' => 'Demonstration only', 'frequency' => 'Demonstration only',
                            'duration' => 'Example only', 'instructions' => 'Not a treatment instruction.', 'quantity_prescribed' => 10,
                        ]);
                        $items[] = $item;
                    }
                    foreach (match ($prescriptionState) {
                        'PARTIAL' => [4], 'FULL' => [4, 6], default => []
                    } as $quantity) {
                        $release = new Dispensing;
                        $release->forceFill(['prescription_id' => $rx->id, 'pharmacist_id' => $pharmacist->id, 'dispensed_at' => now()])->save();
                        foreach ($items as $item) {
                            $release->items()->create(['prescription_item_id' => $item->id, 'quantity_dispensed' => $quantity]);
                        }
                    }
                }
                if ($name === 'Laboratory') {
                    foreach (array_keys(LaboratoryRecord::STATUSES) as $status) {
                        $lab = new LaboratoryRecord;
                        $lab->forceFill([
                            'visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(),
                            'test_name' => 'Fictional test - '.$status, 'availability_status' => $status,
                            'notes' => 'Example tracking only; no test results.',
                            'external_advice' => $status === 'EXTERNAL_ADVISED' ? 'Fictional external laboratory advice for demonstration only.' : null,
                            'created_by' => $medtech->id, 'updated_by' => $medtech->id,
                        ])->save();
                    }
                }
                if ($disposition) {
                    $record = new DispositionRecord;
                    $record->forceFill([
                        'visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(), 'type' => $disposition,
                        'reason' => 'Fictional '.$name.' example purpose.', 'remarks' => 'Demonstration only; no official document issued.',
                        'created_by' => $doctor->id,
                    ])->save();
                }
                if ($name === 'Released') {
                    $earlier = new Visit;
                    $earlier->forceFill([
                        'patient_id' => $patient->id, 'service_id' => $service->id,
                        'visit_number' => 'EXAMPLE-VISIT-HISTORY', 'visit_date' => now('Asia/Manila')->subDays(7)->toDateString(),
                        'status' => 'OPEN', 'created_by' => $staff->id,
                    ])->save();
                    $this->audit($earlier);
                }
                $this->audit($visit);
            }
        });
        $this->command?->info('Example records ready. Search patients for Fictional or EXAMPLE-PATIENT. Existing profiles were preserved.');
    }

    private function audit(Visit $visit): void
    {
        activity('demo')->performedOn($visit)->withProperties(['source' => 'ExampleRecordsSeeder'])->log('demo.workflow_seeded');
    }
}
