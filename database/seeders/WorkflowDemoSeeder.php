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

class WorkflowDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Fictional workflow data is local/testing only.');
        }
        $this->call(UserSeeder::class);
        $this->call(ServiceSeeder::class);
        DB::transaction(function () {
            $staff = User::where('email', 'information@rhu.test')->firstOrFail();
            $doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
            $nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
            $admin = User::where('email', 'admin@rhu.test')->firstOrFail();
            $medtech = User::where('email', 'medtech@rhu.test')->firstOrFail();
            $pharmacist = User::where('email', 'pharmacist@rhu.test')->firstOrFail();
            foreach (['Aster', 'Birch', 'Cedar'] as $index => $name) {
                $number = 'DEMO-PATIENT-'.($index + 1);
                if (Patient::withTrashed()->where('patient_number', $number)->exists()) {
                    continue;
                }
                $patient = new Patient(['first_name' => 'Fictional '.$name, 'last_name' => 'Demo', 'birth_date' => '1990-01-01', 'municipality' => 'Fictional municipality']);
                $patient->patient_number = $number;
                $patient->created_by = $staff->id;
                $patient->duplicate_key = Patient::duplicateKey($patient->first_name, $patient->last_name);
                $patient->save();
                $visit = new Visit;
                $visit->patient_id = $patient->id;
                $visit->service_id = Service::where('is_active', true)->firstOrFail()->id;
                $visit->visit_date = now('Asia/Manila')->subDays($index === 2 ? 1 : 0)->toDateString();
                $visit->visit_number = 'DEMO-VISIT-'.($index + 1);
                $visit->status = 'OPEN';
                $visit->created_by = $staff->id;
                $visit->save();
                if ($index === 0) {
                    $vitals = new VitalSign(['systolic_bp' => 120, 'diastolic_bp' => 80, 'temperature' => 36.5, 'pulse_rate' => 70, 'respiratory_rate' => 18, 'oxygen_saturation' => 98, 'height_cm' => 165, 'weight_kg' => 60]);
                    $vitals->visit_id = $visit->id;
                    $vitals->recorded_by = $nurse->id;
                    $vitals->updated_by = $nurse->id;
                    $vitals->recorded_at = now();
                    $vitals->save();
                    $check = new EligibilityCheck(['status' => 'CHECKBOX', 'philhealth_confirmed' => true]);
                    $check->visit_id = $visit->id;
                    $check->verified_by = $admin->id;
                    $check->updated_by = $admin->id;
                    $check->verified_at = now();
                    $check->save();
                    $itr = new TreatmentRecord;
                    $itr->visit_id = $visit->id;
                    $itr->assessment = 'Fictional demonstration only. No clinical advice.';
                    $itr->planning = 'Demonstrate record workflow.';
                    $itr->created_by = $doctor->id;
                    $itr->updated_by = $doctor->id;
                    $itr->objective_snapshot = $vitals->only(VitalSign::FIELDS);
                    $itr->save();
                    $rx = new Prescription;
                    $rx->visit_id = $visit->id;
                    $rx->treatment_record_id = $itr->id;
                    $rx->doctor_id = $doctor->id;
                    $rx->prescription_number = 'DEMO-RX-1';
                    $rx->status = 'ISSUED';
                    $rx->prescribed_at = now();
                    $rx->save();
                    $item = $rx->items()->create(['medicine_name' => 'Fictional demonstration medicine', 'dosage' => 'Demonstration only', 'frequency' => 'Demonstration only', 'quantity_prescribed' => 10]);
                    $release = new Dispensing;
                    $release->prescription_id = $rx->id;
                    $release->pharmacist_id = $pharmacist->id;
                    $release->dispensed_at = now();
                    $release->save();
                    $release->items()->create(['prescription_item_id' => $item->id, 'quantity_dispensed' => 4]);
                    $lab = new LaboratoryRecord;
                    $lab->visit_id = $visit->id;
                    $lab->submission_token = (string) Str::uuid();
                    $lab->test_name = 'Fictional demonstration test';
                    $lab->availability_status = 'PENDING';
                    $lab->created_by = $medtech->id;
                    $lab->updated_by = $medtech->id;
                    $lab->save();
                    $record = new DispositionRecord;
                    $record->visit_id = $visit->id;
                    $record->submission_token = (string) Str::uuid();
                    $record->type = 'CERTIFICATE_REQUEST';
                    $record->reason = 'Fictional demonstration request';
                    $record->created_by = $doctor->id;
                    $record->save();
                }
                activity('demo')->performedOn($visit)->withProperties(['source' => 'WorkflowDemoSeeder'])->log('demo.workflow_seeded');
            }
        });
    }
}
