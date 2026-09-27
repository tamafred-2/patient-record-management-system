<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class AnalyticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Analytics examples are local/testing only.');
        }
        DB::transaction(function () {
            $this->call(UserSeeder::class);
            $this->call(ServiceSeeder::class);
            $service = Service::where('seed_key', 'CONSULTATION')->where('is_active', true)->firstOrFail();
            $staff = User::where('email', 'information@rhu.test')->firstOrFail();
            $doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
            foreach (['Alpha', 'Beta', 'Gamma'] as $index => $name) {
                $number = 'ANALYTICS-PATIENT-'.$name;
                if (Patient::withTrashed()->where('patient_number', $number)->exists()) {
                    continue;
                }
                $patient = new Patient;
                $patient->forceFill(['patient_number' => $number, 'first_name' => 'Fictional '.$name, 'last_name' => 'Analytics', 'created_by' => $staff->id, 'duplicate_key' => Patient::duplicateKey('Fictional '.$name, 'Analytics')])->save();
                foreach ([14, 7, $index] as $offset) {
                    $visit = new Visit;
                    $visit->forceFill(['patient_id' => $patient->id, 'service_id' => $service->id, 'visit_number' => 'ANALYTICS-VISIT-'.$name.'-'.$offset, 'visit_date' => now('Asia/Manila')->subDays($offset)->toDateString(), 'status' => 'OPEN', 'created_by' => $staff->id])->save();
                    $itr = new TreatmentRecord;
                    $itr->forceFill(['visit_id' => $visit->id, 'created_by' => $doctor->id, 'updated_by' => $doctor->id, 'assessment' => 'Fictional analytics example only.', 'diagnoses' => $index < 2 ? ['Fictional condition Alpha'] : ['Fictional condition Beta', 'Fictional condition Gamma']])->save();
                    activity('demo')->performedOn($visit)->withProperties(['source' => 'AnalyticsDemoSeeder'])->log('demo.workflow_seeded');
                }
            }
        });
    }
}
