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
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/** Entirely synthetic local dataset. Never a source of real clinical records. */
class SeptemberClinicSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Clinic fixtures are local/testing only.');
        }
        if (Activity::where('description', 'seed.september_clinic_completed')->exists()) {
            $this->fillMissingQueueReferences();
            $this->command?->info('September dataset ready; synthetic queue references updated and existing entries preserved.');

            return;
        }
        if (Patient::withTrashed()->exists() || Visit::exists()) {
            throw new LogicException('Use an empty local database for this replacement dataset. Existing records were not changed.');
        }
        DB::transaction(function () {
            $existingUsers = User::pluck('id')->all();
            $this->call([UserSeeder::class, ServiceSeeder::class]);
            $users = User::whereIn('email', array_keys(UserSeeder::ACCOUNTS))->get()->keyBy('email');
            foreach (['admin' => 'Adrian Cruz', 'information' => 'Marissa Ramos', 'nurse' => 'Camille Reyes', 'doctor' => 'Rafael Mendoza', 'medtech' => 'Patricia Santos', 'pharmacist' => 'Gabriel Flores', 'midwife' => 'Elena Garcia'] as $key => $name) {
                if (! in_array($users[$key.'@rhu.test']->id, $existingUsers)) {
                    $users[$key.'@rhu.test']->update(['name' => $name]);
                }
            }
            $services = Service::where('is_active', true)->get()->keyBy('seed_key');
            foreach (['CONSULTATION', 'LABORATORY', 'MEDICAL_CERTIFICATE'] as $key) {
                if (! isset($services[$key])) {
                    throw new LogicException('Required service unavailable: '.$key);
                }
            }
            $male = ['Antonio', 'Roberto', 'Miguel', 'Ramon', 'Paolo', 'Luis', 'Gabriel', 'Rafael', 'Carlos', 'Manuel', 'Ernesto', 'Alfredo', 'Daniel', 'Marco', 'Vincent'];
            $female = ['Mariel', 'Carmina', 'Lourdes', 'Danica', 'Elena', 'Teresa', 'Rosario', 'Patricia', 'Camille', 'Marissa', 'Angela', 'Sofia', 'Isabel', 'Cecilia', 'Beatriz'];
            $surnames = ['Santos', 'Reyes', 'Cruz', 'Ramos', 'Garcia', 'Mendoza', 'Flores', 'Aquino', 'Navarro', 'Castillo', 'Mercado', 'Soriano', 'Villanueva', 'Bautista', 'Fernandez', 'Torres', 'Dizon', 'Santiago', 'Gonzales', 'Rivera'];
            $profiles = [];
            $start = CarbonImmutable::parse('2026-08-31', 'Asia/Manila');
            for ($day = 0; $day < 26; $day++) {
                $date = $start->addDays($day);
                $volume = 20 + $this->pick('volume-'.$day, 31);
                for ($slot = 0; $slot < $volume; $slot++) {
                    $key = ($day * 17 + $slot * 7) % 300;
                    $time = $date->setTime(8, 0)->addMinutes($slot * 8 + $this->pick('arrival-'.$day.'-'.$slot, 7));
                    if (! isset($profiles[$key])) {
                        $femalePatient = $key % 2 === 0;
                        $first = ($femalePatient ? $female : $male)[intdiv($key, 20)];
                        $last = $surnames[$key % 20];
                        $patient = new Patient;
                        $patient->forceFill([
                            'patient_number' => sprintf('P-2026-%05d', $key + 1), 'first_name' => $first,
                            'middle_name' => $surnames[($key + 7) % 20], 'last_name' => $last,
                            'birth_date' => CarbonImmutable::create(1946 + $this->pick('birth-'.$key, 60), 1 + $this->pick('month-'.$key, 12), 1 + $this->pick('day-'.$key, 28))->toDateString(),
                            'sex' => $femalePatient ? 'Female' : 'Male', 'civil_status' => ['Single', 'Married', 'Widowed'][$this->pick('civil-'.$key, 3)],
                            'barangay' => ['San Isidro', 'San Vicente', 'San Miguel', 'Santa Rosa', 'San Jose'][$key % 5],
                            'municipality' => 'Calasiao', 'province' => 'Pangasinan',
                            'contact_number' => null, 'emergency_contact_name' => ($femalePatient ? 'Roberto' : 'Teresa').' '.$last,
                            'emergency_contact_number' => null, 'created_by' => $users['information@rhu.test']->id,
                            'duplicate_key' => Patient::duplicateKey($first, $last), 'created_at' => $time, 'updated_at' => $time,
                        ])->save();
                        $profiles[$key] = $patient;
                    }
                    $patient = $profiles[$key];
                    $n = $this->pick('service-'.$day.'-'.$slot, 10);
                    $serviceKey = $n < 7 ? 'CONSULTATION' : ($n < 9 ? 'LABORATORY' : 'MEDICAL_CERTIFICATE');
                    $complete = $day <= 23;
                    $visit = new Visit;
                    $visit->forceFill([
                        'patient_id' => $patient->id, 'service_id' => $services[$serviceKey]->id,
                        'visit_number' => sprintf('V-%s-%03d', $date->format('Ymd'), $slot + 1),
                        'queue_reference' => $this->queueReference($day, $slot),
                        'visit_date' => $date->toDateString(), 'status' => $complete ? 'COMPLETED' : 'OPEN',
                        'completed_at' => $complete ? $time->addMinutes(90) : null,
                        'created_by' => $users['information@rhu.test']->id, 'created_at' => $time, 'updated_at' => $complete ? $time->addMinutes(90) : $time,
                    ])->save();
                    $nurse = $users['nurse@rhu.test']->id;
                    $diagnosis = ['Hypertension', 'Type 2 diabetes mellitus', 'Upper respiratory tract infection', 'Allergic rhinitis', 'Hypertension', 'Upper respiratory tract infection'][$key % 6];
                    $respiratory = $diagnosis === 'Upper respiratory tract infection';
                    $vitals = new VitalSign;
                    $vitals->forceFill([
                        'visit_id' => $visit->id, 'systolic_bp' => ($diagnosis === 'Hypertension' ? 132 : 108) + $this->pick('bp-'.$day.'-'.$key, 12),
                        'diastolic_bp' => 70 + $this->pick('dbp-'.$key, 15), 'temperature' => $respiratory ? 37.8 : 36.6,
                        'pulse_rate' => 68 + $this->pick('pulse-'.$day.'-'.$key, 22), 'respiratory_rate' => 16 + $key % 4,
                        'oxygen_saturation' => 97 + $key % 3, 'height_cm' => 150 + $key % 29, 'weight_kg' => 49 + $key % 36,
                        'recorded_by' => $nurse, 'updated_by' => $nurse, 'recorded_at' => $time->addMinutes(10),
                        'created_at' => $time->addMinutes(10), 'updated_at' => $time->addMinutes(10),
                    ])->save();
                    $admin = $users['admin@rhu.test']->id;
                    $check = new EligibilityCheck;
                    $check->forceFill(['visit_id' => $visit->id, 'status' => 'CHECKBOX', 'philhealth_confirmed' => $key % 4 !== 0,
                        'remarks' => $key % 4 !== 0 ? 'Record confirmation entered.' : 'Confirmation pending supporting information.',
                        'verified_by' => $admin, 'updated_by' => $admin, 'verified_at' => $time->addMinutes(15),
                        'created_at' => $time->addMinutes(15), 'updated_at' => $time->addMinutes(15)])->save();
                    $doctor = $users['doctor@rhu.test']->id;
                    if ($serviceKey !== 'LABORATORY') {
                        $answers = array_fill_keys(array_keys(TreatmentRecord::QUESTIONS), false);
                        $answers['fever'] = $serviceKey === 'CONSULTATION' && $respiratory;
                        $answers['cough_colds'] = $answers['fever'];
                        $answers['family_hypertension'] = $key % 3 === 0;
                        $answers['taking_medicine'] = $serviceKey === 'CONSULTATION' && in_array($diagnosis, ['Hypertension', 'Type 2 diabetes mellitus']);
                        $answers['allergy'] = $key % 17 === 0;
                        $itr = new TreatmentRecord;
                        $itr->forceFill([...$answers, 'visit_id' => $visit->id,
                            'medicine_details' => $answers['taking_medicine'] ? 'Maintenance medication reported; reconciliation discussed.' : null,
                            'diagnoses' => $serviceKey === 'CONSULTATION' ? [$diagnosis] : [],
                            'assessment' => $serviceKey === 'CONSULTATION' ? $diagnosis.'. Symptoms and relevant history reviewed.' : 'Medical certificate requested for employment requirements.',
                            'planning' => 'Findings discussed with the patient. Follow-up instructions reviewed.',
                            'remarks' => $answers['allergy'] ? 'Food allergy reported in the history.' : 'Patient questions addressed during consultation.',
                            'objective_snapshot' => $vitals->only(VitalSign::FIELDS), 'created_by' => $doctor, 'updated_by' => $doctor,
                            'created_at' => $time->addMinutes(30), 'updated_at' => $time->addMinutes(30)])->save();
                        if ($serviceKey === 'CONSULTATION' && $slot % 3 === 0) {
                            $issued = $complete || $slot % 2 === 0;
                            $rx = new Prescription;
                            $rx->forceFill(['visit_id' => $visit->id, 'treatment_record_id' => $itr->id, 'doctor_id' => $doctor,
                                'prescription_number' => sprintf('RX-%s-%03d', $date->format('Ymd'), $slot + 1), 'status' => $issued ? 'ISSUED' : 'DRAFT',
                                'prescribed_at' => $issued ? $time->addMinutes(40) : null, 'created_at' => $time->addMinutes(40), 'updated_at' => $time->addMinutes(40)])->save();
                            $medicine = match ($diagnosis) {
                                'Hypertension' => 'Amlodipine','Type 2 diabetes mellitus' => 'Metformin','Allergic rhinitis' => 'Cetirizine',default => 'Paracetamol'
                            };
                            $item = $rx->items()->create(['medicine_name' => $medicine, 'dosage' => 'Refer to the written prescription', 'frequency' => 'As instructed by the prescriber',
                                'duration' => 'Refer to the written prescription', 'instructions' => 'Medication instructions reviewed with the patient.', 'quantity_prescribed' => 10,
                                'created_at' => $time->addMinutes(40), 'updated_at' => $time->addMinutes(40)]);
                            if ($issued && ($complete || $slot % 4 === 0)) {
                                $release = new Dispensing;
                                $release->forceFill(['prescription_id' => $rx->id, 'pharmacist_id' => $users['pharmacist@rhu.test']->id,
                                    'dispensed_at' => $time->addMinutes(60), 'created_at' => $time->addMinutes(60), 'updated_at' => $time->addMinutes(60)])->save();
                                $release->items()->create(['prescription_item_id' => $item->id, 'quantity_dispensed' => $complete ? 10 : 4,
                                    'created_at' => $time->addMinutes(60), 'updated_at' => $time->addMinutes(60)]);
                            }
                        }
                        if ($serviceKey === 'MEDICAL_CERTIFICATE') {
                            $record = new DispositionRecord;
                            $record->forceFill(['visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(), 'type' => 'CERTIFICATE_REQUEST',
                                'reason' => 'Employment requirement', 'remarks' => 'Request recorded; document issuance is handled separately.',
                                'created_by' => $doctor, 'created_at' => $time->addMinutes(40), 'updated_at' => $time->addMinutes(40)])->save();
                        }
                    }
                    if ($serviceKey === 'LABORATORY') {
                        $status = $complete ? ($slot % 4 === 0 ? 'EXTERNAL_ADVISED' : 'PERFORMED_RHU') : ['PENDING', 'PERFORMED_RHU', 'EXTERNAL_ADVISED'][$slot % 3];
                        $lab = new LaboratoryRecord;
                        $lab->forceFill(['visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(), 'test_name' => ['Complete blood count', 'Urinalysis', 'Fasting blood glucose'][$key % 3],
                            'availability_status' => $status, 'notes' => match ($status) {
                                'PENDING' => 'Awaiting laboratory service.', 'PERFORMED_RHU' => 'Laboratory service recorded; result retained separately.',default => 'External laboratory advised.'
                            },
                            'external_advice' => $status === 'EXTERNAL_ADVISED' ? 'Patient advised to bring the external laboratory report at follow-up.' : null,
                            'created_by' => $users['medtech@rhu.test']->id, 'updated_by' => $users['medtech@rhu.test']->id,
                            'created_at' => $time->addMinutes(45), 'updated_at' => $time->addMinutes(45)])->save();
                    }
                }
            }
            activity('demo')->withProperties(['source' => self::class, 'synthetic' => true, 'from' => '2026-08-31', 'to' => '2026-09-25'])
                ->log('seed.september_clinic_completed');
        });
        $this->command?->info('Synthetic September clinic dataset loaded.');
    }

    private function fillMissingQueueReferences(): void
    {
        DB::transaction(function () {
            $changed = 0;
            $start = CarbonImmutable::parse('2026-08-31', 'Asia/Manila');
            for ($day = 0; $day < 26; $day++) {
                $date = $start->addDays($day);
                for ($slot = 0; $slot < 20 + $this->pick('volume-'.$day, 31); $slot++) {
                    $patientNumber = sprintf('P-2026-%05d', ($day * 17 + $slot * 7) % 300 + 1);
                    $ids = Visit::where('visit_number', sprintf('V-%s-%03d', $date->format('Ymd'), $slot + 1))
                        ->whereDate('visit_date', $date->toDateString())
                        ->whereHas('patient', fn ($query) => $query->where('patient_number', $patientNumber))
                        ->select('id');
                    // Fixture-only correction: retain timestamps, statuses and manually entered values.
                    $changed += DB::table('visits')->whereIn('id', $ids)
                        ->where(fn ($query) => $query->whereNull('queue_reference')->orWhere('queue_reference', '')->orWhere('queue_reference', sprintf('Q-%03d', $slot + 1))
                            ->orWhere('queue_reference', sprintf('%s%s-%03d', chr(65 + $this->pick('queue-first-'.$day.'-'.$slot, 26)), chr(65 + $this->pick('queue-second-'.$day.'-'.$slot, 26)), $slot + 1)))
                        ->where(fn ($query) => $query->whereNull('queue_reference')->orWhere('queue_reference', '!=', $this->queueReference($day, $slot)))
                        ->update(['queue_reference' => $this->queueReference($day, $slot)]);
                }
            }
            if ($changed) {
                activity('demo')->withProperties(['source' => self::class, 'synthetic' => true, 'updated_visits' => $changed])
                    ->log('seed.september_queue_references_filled');
            }
            $this->command?->info("Updated {$changed} synthetic queue references.");
        });
    }

    private function queueReference(int $day, int $slot): string
    {
        // Each fixture date shares a stable prefix; use distinct pairs across the dataset.
        $used = [];
        for ($index = 0; $index <= $day; $index++) {
            $pair = $this->pick('queue-day-'.$index, 26 * 26);
            while (isset($used[$pair])) {
                $pair = ($pair + 1) % (26 * 26);
            }
            $used[$pair] = true;
        }

        return sprintf('%s%s-%03d', chr(65 + intdiv($pair, 26)), chr(65 + $pair % 26), $slot + 1);
    }

    private function pick(string $key, int $choices): int
    {
        return hexdec(substr(hash('sha256', 'september-clinic-v1-'.$key), 0, 7)) % $choices;
    }
}
