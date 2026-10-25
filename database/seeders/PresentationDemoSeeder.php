<?php

namespace Database\Seeders;

use App\Models\AssessmentRequest;
use App\Models\Dispensing;
use App\Models\DispositionRecord;
use App\Models\EligibilityCheck;
use App\Models\LaboratoryRecord;
use App\Models\MedicalCertificate;
use App\Models\MidwifeCareRecord;
use App\Models\NurseAuthorization;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Visit;
use App\Models\VitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * Entirely synthetic presentation dataset: completed history from August 1, 2026, and one open
 * "presentation day" with a visit waiting at every workflow step. Never a source of real records.
 *
 * Run on an empty local database:
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=PresentationDemoSeeder
 *
 * The presentation day defaults to 2026-10-26; set DEMO_PRESENTATION_DATE=YYYY-MM-DD to move it.
 */
class PresentationDemoSeeder extends Seeder
{
    private const START = '2026-08-01';

    private const MARKER = 'seed.presentation_demo_completed';

    private array $users = [];

    private array $services = [];

    private array $pool = [];

    private CarbonImmutable $day;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Presentation fixtures are local/testing only.');
        }
        if (Activity::where('description', self::MARKER)->exists()) {
            $this->command?->info('Presentation dataset already loaded; nothing changed.');

            return;
        }
        if (Patient::withTrashed()->exists() || Visit::exists()) {
            throw new LogicException('Use an empty local database: run "php artisan migrate:fresh" first. Existing records were not changed.');
        }
        $this->day = CarbonImmutable::parse(env('DEMO_PRESENTATION_DATE', '2026-10-26'), 'Asia/Manila')->startOfDay();

        DB::transaction(function () {
            $this->call([RolePermissionSeeder::class, ServiceSeeder::class, UserSeeder::class]);
            $this->prepareStaff();
            $this->createPatients();
            $previousPrefix = null;
            for ($date = CarbonImmutable::parse(self::START, 'Asia/Manila'); $date->lt($this->day); $date = $date->addDay()) {
                if ($date->isWeekend()) {
                    continue;
                }
                $previousPrefix = $this->seedHistoricalDay($date, $previousPrefix);
            }
            $this->seedPresentationDay($previousPrefix);
            activity('demo')->withProperties(['source' => self::class, 'synthetic' => true, 'from' => self::START, 'to' => $this->day->toDateString()])
                ->log(self::MARKER);
        });
        $this->command?->info('Presentation dataset loaded: '.Patient::count().' patients, '.Visit::count().' visits, '.self::START.' to '.$this->day->toDateString().'.');
    }

    private function prepareStaff(): void
    {
        $names = ['admin' => 'Adrian Cruz', 'information' => 'Marissa Ramos', 'nurse' => 'Camille Reyes', 'doctor' => 'Dr. Rafael Mendoza',
            'medtech' => 'Patricia Santos', 'pharmacist' => 'Gabriel Flores', 'midwife' => 'Elena Garcia'];
        foreach ($names as $key => $name) {
            $user = User::where('email', $key.'@rhu.test')->firstOrFail();
            $user->forceFill(['name' => $name])->save();
            $this->users[$key] = $user;
        }
        foreach (['CONSULTATION', 'LABORATORY', 'MEDICAL_CERTIFICATE', 'MIDWIFE_CARE', 'VACCINATION'] as $key) {
            $this->services[$key] = Service::where('seed_key', $key)->firstOrFail();
        }
        NurseAuthorization::forceCreate([
            'nurse_id' => $this->users['nurse']->id, 'doctor_id' => $this->users['doctor']->id, 'allow_any_test' => false,
            'allowed_tests' => ['Urinalysis', 'Complete blood count (CBC)', 'Fasting blood sugar (FBS)'],
            'instructions' => 'Order only when the assessment shows a clear indication; inform me at the next review.',
            'created_at' => CarbonImmutable::parse(self::START, 'Asia/Manila')->setTime(8, 0), 'updated_at' => CarbonImmutable::parse(self::START, 'Asia/Manila')->setTime(8, 0),
        ]);
    }

    private function createPatients(): void
    {
        $male = ['Antonio', 'Roberto', 'Miguel', 'Ramon', 'Paolo', 'Luis', 'Jerome', 'Carlos', 'Manuel', 'Ernesto', 'Alfredo', 'Daniel', 'Marco', 'Vincent', 'Joshua', 'Renato', 'Arnel', 'Dominic'];
        $female = ['Mariel', 'Carmina', 'Lourdes', 'Danica', 'Jocelyn', 'Teresa', 'Rosario', 'Kristine', 'Angelica', 'Maricel', 'Angela', 'Sofia', 'Isabel', 'Cecilia', 'Beatriz', 'Rowena', 'Lea', 'Janelle'];
        $surnames = ['Santos', 'Reyes', 'Cruz', 'Ramos', 'Garcia', 'Mendoza', 'Flores', 'Aquino', 'Navarro', 'Castillo', 'Mercado', 'Soriano', 'Villanueva', 'Bautista', 'Fernandez', 'Torres', 'Dizon', 'Santiago', 'Gonzales', 'Rivera', 'De Guzman', 'Manalo', 'Caballero', 'Ferrer'];
        $barangays = ['Ambonao', 'Ambuetel', 'Banaoang', 'Bued', 'Buenlag', 'Cabilocaan', 'Dinalaoan', 'Doyong', 'Gabon', 'Lasip', 'Longos', 'Lumbang', 'Macabito', 'Malabago', 'Mancup', 'Nagsaing', 'Nalsian', 'Poblacion East', 'Poblacion West', 'Quesban', 'San Miguel', 'San Vicente', 'Songkoy', 'Talibaew'];
        $created = CarbonImmutable::parse(self::START, 'Asia/Manila')->setTime(7, 30);
        for ($key = 0; $key < 240; $key++) {
            // Age bands: children, adults (including pregnant-age women) and a large senior group.
            $band = $this->pick('band-'.$key, 100);
            $age = $band < 12 ? 1 + $this->pick('age-'.$key, 16) : ($band < 70 ? 18 + $this->pick('age-'.$key, 42) : 60 + $this->pick('age-'.$key, 25));
            $isFemale = $key % 2 === 0;
            $first = ($isFemale ? $female : $male)[$this->pick('first-'.$key, 18)];
            $last = $surnames[$this->pick('last-'.$key, 24)];
            $birth = $this->day->subYears($age)->subDays(15 + $this->pick('birthday-'.$key, 330));
            $patient = new Patient;
            $patient->forceFill([
                'first_name' => $first, 'middle_name' => $surnames[$this->pick('middle-'.$key, 24)], 'last_name' => $last,
                'suffix' => ! $isFemale && $key % 29 === 0 ? 'Jr.' : null,
                'birth_date' => $birth->toDateString(), 'sex' => $isFemale ? 'Female' : 'Male',
                'civil_status' => $age < 18 ? 'Single' : ['Single', 'Married', 'Married', 'Widowed'][$this->pick('civil-'.$key, 4)],
                'barangay' => $barangays[$this->pick('barangay-'.$key, 24)], 'municipality' => 'Calasiao', 'province' => 'Pangasinan',
                'contact_number' => '09'.str_pad((string) (100000000 + $this->pick('phone-'.$key, 899999999)), 9, '0', STR_PAD_LEFT),
                'emergency_contact_name' => ($isFemale ? $male : $female)[$this->pick('contact-'.$key, 18)].' '.$last,
                'guardian_name' => $age < 18 ? $female[$this->pick('guardian-'.$key, 18)].' '.$last : null,
                'guardian_relationship' => $age < 18 ? 'Mother' : null,
                'created_by' => $this->users['information']->id, 'duplicate_key' => Patient::duplicateKey($first, $last),
                'created_at' => $created, 'updated_at' => $created,
            ])->save();
            $patient->forceFill(['patient_number' => 'RHU-2026-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT)])->save();
            $chronic = $age >= 40 ? ['Hypertension', 'Type 2 diabetes mellitus', 'Hypertension', null][$this->pick('chronic-'.$key, 4)] : null;
            $this->pool[] = ['patient' => $patient, 'age' => $age, 'female' => $isFemale, 'chronic' => $chronic];
        }
    }

    private function seedHistoricalDay(CarbonImmutable $date, ?string $previousPrefix): string
    {
        $prefix = $this->prefixFor($date, $previousPrefix);
        $volume = 12 + $this->pick('volume-'.$date->toDateString(), 10);
        $used = [];
        for ($slot = 0; $slot < $volume; $slot++) {
            $index = $this->pick('who-'.$date->toDateString().'-'.$slot, count($this->pool));
            while (isset($used[$index])) {
                $index = ($index + 1) % count($this->pool);
            }
            $used[$index] = true;
            $person = $this->pool[$index];
            $time = $date->setTime(8, 0)->addMinutes($slot * 14 + $this->pick('arrive-'.$date->toDateString().'-'.$slot, 10));
            $service = $this->serviceFor($person, $this->pick('service-'.$date->toDateString().'-'.$slot, 100));
            $visit = $this->visit($person, $service, $date, $time, $prefix, $slot + 1, $this->pick('emergency-'.$date->toDateString().'-'.$slot, 100) < 2);
            match ($service) {
                'CONSULTATION' => $this->consultation($visit, $person, $time, true),
                'LABORATORY' => $this->directLaboratory($visit, $time, true),
                'MEDICAL_CERTIFICATE' => $this->certificateVisit($visit, $person, $time),
                'MIDWIFE_CARE' => $this->midwifeVisit($visit, $person, $time, true),
                default => $this->vaccinationVisit($visit, $person, $time, true),
            };
            $this->complete($visit, $time->addMinutes(110));
        }
        DB::table('queue_days')->insert(['queue_date' => $date->toDateString(), 'prefix' => $prefix, 'last_number' => $volume]);

        return $prefix;
    }

    /** One open day with a patient waiting at each step, so every role has live work to show. */
    private function seedPresentationDay(?string $previousPrefix): void
    {
        $date = $this->day;
        $prefix = $this->prefixFor($date, $previousPrefix);
        $number = 0;
        $at = fn (int $minutes) => $date->setTime(7, 45)->addMinutes($minutes);
        $pick = function (callable $filter) {
            foreach ($this->pool as $index => $person) {
                if (! ($person['used'] ?? false) && $filter($person)) {
                    $this->pool[$index]['used'] = true;

                    return $person;
                }
            }
            throw new LogicException('No synthetic patient matches the presentation scenario.');
        };
        $open = function (array $person, string $service, int $minutes, bool $emergency = false) use ($date, $prefix, &$number, $at) {
            return $this->visit($person, $service, $date, $at($minutes), $prefix, ++$number, $emergency);
        };

        // 1. Information Staff: just registered, waiting for the nurse (senior priority, emergency, regular).
        $open($pick(fn ($p) => $p['age'] >= 65), 'CONSULTATION', 0);
        $open($pick(fn ($p) => $p['age'] >= 25 && $p['age'] < 60), 'CONSULTATION', 5, true);
        $open($pick(fn ($p) => $p['age'] >= 18 && $p['age'] < 60), 'CONSULTATION', 8);

        // 2. Nurse done, waiting for the doctor.
        foreach ([12, 16] as $minutes) {
            $person = $pick(fn ($p) => $p['age'] >= 18);
            $visit = $open($person, 'CONSULTATION', $minutes);
            $this->vitals($visit, $person, $at($minutes + 6), 'Alert, ambulatory. Complains of on-and-off headache for two days.');
            $this->eligibility($visit, $at($minutes + 9), true);
        }

        // 3. Doctor asked the nurse for a fresh assessment (pending reassessment).
        $person = $pick(fn ($p) => $p['chronic'] === 'Hypertension');
        $visit = $open($person, 'CONSULTATION', 20);
        $vitals = $this->vitals($visit, $person, $at(26), 'Elevated blood pressure on arrival; patient rushed to the clinic.');
        AssessmentRequest::forceCreate(['visit_id' => $visit->id, 'requested_by' => $this->users['doctor']->id,
            'reason' => 'Please repeat blood pressure after 15 minutes of rest before I review.', 'assessment_version' => 1,
            'status' => 'PENDING', 'created_at' => $at(35), 'updated_at' => $at(35)]);

        // 4. ITR saved, prescription still a draft.
        $person = $pick(fn ($p) => $p['age'] >= 18 && $p['age'] < 60);
        $visit = $open($person, 'CONSULTATION', 24);
        $this->vitals($visit, $person, $at(30), 'Cough and colds for three days, no difficulty of breathing.');
        $itr = $this->itr($visit, $person, $at(45), 'Upper respiratory tract infection');
        $this->prescription($visit, $itr, $at(50), [['Paracetamol', '500 mg', '10']], false);

        // 5. Prescriptions issued and waiting at Pharmacy.
        foreach ([28, 32] as $minutes) {
            $person = $pick(fn ($p) => $p['chronic'] !== null);
            $visit = $open($person, 'CONSULTATION', $minutes);
            $this->vitals($visit, $person, $at($minutes + 6), 'Maintenance follow-up. No new complaints.');
            $this->eligibility($visit, $at($minutes + 9), $minutes === 28);
            $itr = $this->itr($visit, $person, $at($minutes + 25), $person['chronic']);
            $this->prescription($visit, $itr, $at($minutes + 30), $this->medicinesFor($person['chronic']), true);
        }

        // 6. Pharmacy: partial release with a generic substitute, the rest bought outside.
        $person = $pick(fn ($p) => $p['chronic'] === 'Type 2 diabetes mellitus');
        $visit = $open($person, 'CONSULTATION', 36);
        $this->vitals($visit, $person, $at(42), 'For refill of maintenance medicines.');
        $itr = $this->itr($visit, $person, $at(55), 'Type 2 diabetes mellitus');
        $rx = $this->prescription($visit, $itr, $at(60), [['Metformin', '500 mg', '60'], ['Losartan', '50 mg', '30']], true);
        $this->dispense($rx, $at(75), [[30, 'Metformin (generic, other manufacturer) 500 mg', 'BUY_OUTSIDE', 'Only 30 tablets in stock at RHU'], [30, null, null, null]]);

        // 7. Laboratory: doctor ordered two tests (pending) and a nurse add-on order under the standing authorization.
        $person = $pick(fn ($p) => $p['age'] >= 30);
        $visit = $open($person, 'CONSULTATION', 40);
        $this->vitals($visit, $person, $at(46), 'Fatigue and frequent urination for two weeks.');
        $this->itr($visit, $person, $at(58), 'Type 2 diabetes mellitus');
        $this->labOrder($visit, ['Fasting blood sugar (FBS)', 'Urinalysis'], $this->users['doctor'], null, $at(62), 'Rule out uncontrolled blood sugar and urinary tract infection.', false);
        $person = $pick(fn ($p) => $p['age'] >= 18 && $p['female']);
        $visit = $open($person, 'CONSULTATION', 44);
        $this->vitals($visit, $person, $at(50), 'Dysuria and lower abdominal discomfort since yesterday.');
        $this->labOrder($visit, ['Urinalysis'], $this->users['nurse'], NurseAuthorization::firstOrFail(), $at(55), 'Burning sensation on urination; standing authorization for urinalysis.', false);

        // 8. Results released by MedTech, back with the doctor.
        $person = $pick(fn ($p) => $p['age'] >= 18);
        $visit = $open($person, 'CONSULTATION', 48);
        $this->vitals($visit, $person, $at(54), 'Fever for three days, body malaise.');
        $this->itr($visit, $person, $at(64), 'Upper respiratory tract infection');
        $this->labOrder($visit, ['Complete blood count (CBC)'], $this->users['doctor'], null, $at(66), 'Fever for three days; check white blood cell and platelet counts.', true);

        // 9. Walk-in laboratory service.
        $this->directLaboratory($open($pick(fn ($p) => $p['age'] >= 18), 'LABORATORY', 52), $at(52), false);

        // 10. Midwife: prenatal visit with vital signs; vaccination waiting.
        $person = $pick(fn ($p) => $p['female'] && $p['age'] >= 20 && $p['age'] <= 36);
        $this->midwifeVisit($open($person, 'MIDWIFE_CARE', 56), $person, $at(56), false);
        $person = $pick(fn ($p) => $p['age'] <= 12);
        $open($person, 'VACCINATION', 60);

        // 11. Medical certificate prepared and ready to print or download.
        $person = $pick(fn ($p) => $p['age'] >= 18 && $p['age'] < 50);
        $this->certificateVisit($open($person, 'MEDICAL_CERTIFICATE', 64), $person, $at(64));

        // 12. Everything done: ready for Information Staff to complete the visit.
        $person = $pick(fn ($p) => $p['age'] >= 18);
        $this->consultation($open($person, 'CONSULTATION', 4), $person, $at(4), false);

        DB::table('queue_days')->insert(['queue_date' => $date->toDateString(), 'prefix' => $prefix, 'last_number' => $number]);

        // Chronic patients whose doctor-scheduled return falls on the presentation day.
        $due = Visit::where('status', Visit::COMPLETED)->whereNotNull('follow_up_on')->latest('visit_date')->take(3)->get();
        foreach ($due as $visit) {
            $visit->forceFill(['follow_up_on' => $date->toDateString(), 'follow_up_notified_at' => null])->save();
        }
    }

    private function serviceFor(array $person, int $roll): string
    {
        if ($roll >= 80 && $roll < 90) {
            return $person['female'] && $person['age'] >= 18 && $person['age'] <= 40 ? 'MIDWIFE_CARE' : 'CONSULTATION';
        }
        if ($roll >= 90) {
            return $person['age'] <= 12 || $person['age'] >= 60 ? 'VACCINATION' : 'CONSULTATION';
        }

        return $roll < 62 ? 'CONSULTATION' : ($roll < 74 ? 'LABORATORY' : 'MEDICAL_CERTIFICATE');
    }

    private function visit(array $person, string $service, CarbonImmutable $date, CarbonImmutable $time, string $prefix, int $number, bool $emergency): Visit
    {
        $senior = $person['patient']->isSeniorOn($date->toDateString());
        $pregnant = $service === 'MIDWIFE_CARE';
        [$priority, $reason, $note] = $emergency ? ['EMERGENCY', null, 'Severe chest discomfort on arrival']
            : ($senior ? ['PRIORITY', 'SENIOR', null] : ($pregnant ? ['PRIORITY', 'PREGNANT', null] : ['REGULAR', null, null]));
        $visit = new Visit;
        $visit->forceFill([
            'patient_id' => $person['patient']->id, 'service_id' => $this->services[$service]->id,
            'queue_reference' => $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'visit_date' => $date->toDateString(), 'status' => Visit::OPEN, 'created_by' => $this->users['information']->id,
            'priority' => $priority, 'priority_rank' => Visit::PRIORITY_RANKS[$priority], 'priority_reason' => $reason, 'priority_note' => $note,
            'created_at' => $time, 'updated_at' => $time,
        ])->save();
        $visit->forceFill(['visit_number' => 'V-'.$date->format('Ymd').'-'.str_pad((string) $visit->id, 4, '0', STR_PAD_LEFT)])->save();

        return $visit;
    }

    private function consultation(Visit $visit, array $person, CarbonImmutable $time, bool $historical): void
    {
        $seed = 'consult-'.$visit->id;
        $diagnosis = $person['chronic'] ?? ['Upper respiratory tract infection', 'Acute gastroenteritis', 'Allergic rhinitis', 'Urinary tract infection', 'Bronchial asthma', 'Skin infection (impetigo)', 'Upper respiratory tract infection'][$this->pick($seed, 7)];
        $this->vitals($visit, $person, $time->addMinutes(10), $this->complaintFor($diagnosis));
        $this->eligibility($visit, $time->addMinutes(15), $this->pick($seed.'-ph', 10) < 8);
        $itr = $this->itr($visit, $person, $time->addMinutes(35), $diagnosis);
        if ($this->pick($seed.'-rx', 100) < 85) {
            $rx = $this->prescription($visit, $itr, $time->addMinutes(40), $this->medicinesFor($diagnosis), true);
            $roll = $this->pick($seed.'-release', 100);
            $lines = [];
            foreach ($rx->items as $i => $item) {
                $quantity = (int) $item->quantity_prescribed;
                $lines[] = match (true) {
                    $roll < 72 || $i > 0 => [$quantity, null, null, null],
                    $roll < 82 => [$quantity, $item->medicine_name.' (generic, different manufacturer) '.$item->strength, null, null],
                    $roll < 92 => [intdiv($quantity, 2), null, 'BUY_OUTSIDE', 'Insufficient stock at RHU'],
                    $roll < 96 => [0, null, 'DECLINED', 'Patient has a supply at home'],
                    default => [0, null, 'BUY_OUTSIDE', 'Out of stock at RHU'],
                };
            }
            $this->dispense($rx, $time->addMinutes(60), $lines);
        }
        if ($historical && $this->pick($seed.'-lab', 100) < 18) {
            $this->labOrder($visit, [$diagnosis === 'Urinary tract infection' ? 'Urinalysis' : ($person['chronic'] === 'Type 2 diabetes mellitus' ? 'Fasting blood sugar (FBS)' : 'Complete blood count (CBC)')],
                $this->users['doctor'], null, $time->addMinutes(42), 'Laboratory work-up for the working diagnosis.', true);
        }
        if ($historical && $this->pick($seed.'-ref', 100) < 4) {
            DispositionRecord::forceCreate(['visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(), 'type' => $this->pick($seed.'-reftype', 2) ? 'ADVISED_HIGHER_FACILITY' : 'FORMAL_REFERRAL',
                'reason' => 'Needs specialist evaluation not available at the RHU.', 'facility_name' => 'Region I Medical Center', 'specialty' => 'Internal Medicine',
                'created_by' => $this->users['doctor']->id, 'created_at' => $time->addMinutes(45), 'updated_at' => $time->addMinutes(45)]);
        }
        if ($historical && $person['chronic'] !== null && $this->pick($seed.'-return', 100) < 45) {
            $visit->forceFill(['follow_up_on' => $time->addDays(28)->toDateString(), 'follow_up_reason' => 'Blood pressure and maintenance medicine review.',
                'follow_up_set_by' => $this->users['doctor']->id, 'follow_up_set_at' => $time->addMinutes(45), 'follow_up_notified_at' => $time->addDays(28)->lt($this->day) ? $time->addDays(28)->setTime(7, 0) : null])->save();
        }
    }

    private function vitals(Visit $visit, array $person, CarbonImmutable $time, string $summary, ?User $by = null): VitalSign
    {
        $seed = 'vitals-'.$visit->id;
        $child = $person['age'] < 13;
        $hypertensive = $person['chronic'] === 'Hypertension';
        $vitals = new VitalSign;
        $vitals->forceFill([
            'visit_id' => $visit->id,
            'systolic_bp' => $child ? null : ($hypertensive ? 138 : 108) + $this->pick($seed.'-sbp', 18),
            'diastolic_bp' => $child ? null : ($hypertensive ? 86 : 70) + $this->pick($seed.'-dbp', 12),
            'temperature' => 36.4 + $this->pick($seed.'-t', 16) / 10, 'pulse_rate' => 68 + $this->pick($seed.'-p', 24),
            'respiratory_rate' => 16 + $this->pick($seed.'-r', 6), 'oxygen_saturation' => 96 + $this->pick($seed.'-o', 4),
            'height_cm' => $child ? 80 + $person['age'] * 6 : 150 + $this->pick($seed.'-h', 28), 'weight_kg' => $child ? 10 + $person['age'] * 2.5 : 48 + $this->pick($seed.'-w', 35),
            'assessment_summary' => $summary,
            'recorded_by' => ($by ?? $this->users['nurse'])->id, 'updated_by' => ($by ?? $this->users['nurse'])->id, 'recorded_at' => $time,
            'created_at' => $time, 'updated_at' => $time,
        ])->save();

        return $vitals;
    }

    private function eligibility(Visit $visit, CarbonImmutable $time, bool $withRecord): void
    {
        EligibilityCheck::forceCreate(['visit_id' => $visit->id, 'status' => 'CHECKBOX', 'philhealth_confirmed' => $withRecord,
            'remarks' => $withRecord ? null : 'No record found in the PhilHealth system; patient advised to update membership.',
            'verified_by' => $this->users['admin']->id, 'updated_by' => $this->users['admin']->id, 'verified_at' => $time,
            'created_at' => $time, 'updated_at' => $time]);
    }

    private function itr(Visit $visit, array $person, CarbonImmutable $time, string $diagnosis): TreatmentRecord
    {
        $answers = array_fill_keys(array_keys(TreatmentRecord::QUESTIONS), false);
        $answers['fever'] = in_array($diagnosis, ['Upper respiratory tract infection', 'Urinary tract infection'], true);
        $answers['cough_colds'] = in_array($diagnosis, ['Upper respiratory tract infection', 'Bronchial asthma'], true);
        $answers['asthma'] = $diagnosis === 'Bronchial asthma';
        $answers['family_hypertension'] = $person['chronic'] === 'Hypertension' || $this->pick('fhx-'.$visit->id, 3) === 0;
        $answers['taking_medicine'] = $person['chronic'] !== null;
        $answers['allergy'] = $this->pick('allergy-'.$visit->id, 15) === 0;
        $itr = new TreatmentRecord;
        $itr->forceFill([...$answers, 'visit_id' => $visit->id,
            'medicine_details' => $answers['taking_medicine'] ? 'Maintenance medicines taken as prescribed.' : null,
            'diagnoses' => [$diagnosis], 'assessment' => $diagnosis.'. History and vital signs reviewed.',
            'planning' => 'Medicines and home care explained. Return if symptoms worsen.',
            'remarks' => $answers['allergy'] ? 'Reported allergy to seafood.' : null,
            'objective_snapshot' => $visit->vitalSign?->only(VitalSign::FIELDS),
            'created_by' => $this->users['doctor']->id, 'updated_by' => $this->users['doctor']->id, 'created_at' => $time, 'updated_at' => $time])->save();

        return $itr;
    }

    /** @param list<array{0: string, 1: string, 2: string}> $medicines name, strength, quantity */
    private function prescription(Visit $visit, TreatmentRecord $itr, CarbonImmutable $time, array $medicines, bool $issued): Prescription
    {
        $rx = new Prescription;
        $rx->forceFill(['visit_id' => $visit->id, 'treatment_record_id' => $itr->id, 'doctor_id' => $this->users['doctor']->id,
            'status' => $issued ? Prescription::ISSUED : Prescription::DRAFT, 'prescribed_at' => $issued ? $time : null,
            'created_at' => $time, 'updated_at' => $time])->save();
        $rx->forceFill(['prescription_number' => 'RX-'.str_pad((string) $rx->id, 6, '0', STR_PAD_LEFT)])->save();
        foreach ($medicines as [$name, $strength, $quantity]) {
            $rx->items()->create(['medicine_name' => $name, 'strength' => $strength, 'dosage' => '1 tablet', 'frequency' => $quantity >= 60 ? 'Twice a day' : ($quantity >= 30 ? 'Once a day' : 'Every 6 hours as needed'),
                'duration' => $quantity >= 30 ? '30 days' : '3 days', 'instructions' => 'Take after meals.', 'quantity_prescribed' => $quantity]);
        }

        return $rx->load('items');
    }

    /** @param list<array{0: int, 1: ?string, 2: ?string, 3: ?string}> $lines quantity released, released as, remainder outcome, note */
    private function dispense(Prescription $rx, CarbonImmutable $time, array $lines): void
    {
        $release = new Dispensing;
        $release->forceFill(['prescription_id' => $rx->id, 'pharmacist_id' => $this->users['pharmacist']->id, 'dispensed_at' => $time,
            'created_at' => $time, 'updated_at' => $time])->save();
        foreach ($rx->items as $i => $item) {
            [$quantity, $releasedAs, $outcome, $note] = $lines[$i];
            $closed = $outcome ? (int) $item->quantity_prescribed - $quantity : 0;
            if ($quantity === 0 && $closed === 0) {
                continue;
            }
            $release->items()->create(['prescription_item_id' => $item->id, 'quantity_dispensed' => $quantity, 'released_as' => $releasedAs,
                'remainder_outcome' => $outcome, 'quantity_closed' => $closed, 'outcome_note' => $note]);
        }
        $rx->forceFill(['lock_version' => $rx->lock_version + 1])->save();
    }

    private function labOrder(Visit $visit, array $tests, User $orderedBy, ?NurseAuthorization $authority, CarbonImmutable $time, string $reason, bool $completed): void
    {
        $order = (string) Str::uuid();
        foreach ($tests as $i => $test) {
            $done = $time->addMinutes(40 + $i * 5);
            LaboratoryRecord::forceCreate([
                'visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(), 'test_name' => $test,
                'availability_status' => $completed ? 'PERFORMED_RHU' : 'PENDING', 'notes' => null,
                'request_reason' => $reason, 'order_reference' => $order, 'authorizing_doctor_id' => $this->users['doctor']->id,
                'nurse_authorization_id' => $authority?->id,
                'result_rows' => $completed ? $this->resultsFor($test, $visit->id) : null, 'specimen' => $completed ? $this->specimenFor($test) : null,
                'completed_at' => $completed ? $done : null,
                'created_by' => $orderedBy->id, 'updated_by' => $completed ? $this->users['medtech']->id : $orderedBy->id,
                'created_at' => $time, 'updated_at' => $completed ? $done : $time,
            ]);
        }
    }

    private function directLaboratory(Visit $visit, CarbonImmutable $time, bool $historical): void
    {
        $test = ['Complete blood count (CBC)', 'Urinalysis', 'Fasting blood sugar (FBS)', 'Fecalysis', 'Blood typing'][$this->pick('direct-'.$visit->id, 5)];
        $external = $historical && $this->pick('external-'.$visit->id, 10) < 2;
        LaboratoryRecord::forceCreate([
            'visit_id' => $visit->id, 'submission_token' => (string) Str::uuid(), 'test_name' => $external ? 'Lipid profile' : $test,
            'availability_status' => $historical ? ($external ? 'EXTERNAL_ADVISED' : 'PERFORMED_RHU') : 'PENDING',
            'external_advice' => $external ? 'Reagents unavailable; patient advised to have the test at an accredited private laboratory.' : null,
            'result_rows' => $historical && ! $external ? $this->resultsFor($test, $visit->id) : null,
            'specimen' => $historical && ! $external ? $this->specimenFor($test) : null,
            'completed_at' => $historical && ! $external ? $time->addMinutes(50) : null,
            'created_by' => $this->users['medtech']->id, 'updated_by' => $this->users['medtech']->id,
            'created_at' => $time->addMinutes(10), 'updated_at' => $time->addMinutes($historical ? 50 : 10),
        ]);
    }

    private function certificateVisit(Visit $visit, array $person, CarbonImmutable $time): void
    {
        $vitals = $this->vitals($visit, $person, $time->addMinutes(10), 'Requesting medical certificate for employment. No complaints.');
        $this->itr($visit, $person, $time->addMinutes(30), 'Physically examined; no acute findings');
        $patient = $person['patient'];
        MedicalCertificate::forceCreate(['visit_id' => $visit->id, 'created_by' => $this->users['doctor']->id, 'submission_token' => (string) Str::uuid(), 'template' => 'examination',
            'content' => [
                'body' => sprintf('This is to certify that %s, %d years old, of %s, Calasiao, Pangasinan, was examined at this office on %s.', $patient->full_name, $person['age'], $patient->barangay, $visit->visit_date->format('F j, Y')),
                'request_text' => 'This certification is issued upon the request of the patient for employment purposes, except for medico-legal purposes.',
                'findings' => 'No acute cardiopulmonary findings at the time of examination.',
                'blood_pressure' => $vitals->systolic_bp ? $vitals->systolic_bp.'/'.$vitals->diastolic_bp : null, 'pulse_rate' => $vitals->pulse_rate,
                'respiratory_rate' => $vitals->respiratory_rate, 'temperature' => (string) $vitals->temperature,
                'remarks' => 'Fit to work.', 'signatory_name' => $this->users['doctor']->name, 'signatory_title' => 'Municipal Health Officer', 'license_number' => '0123456',
            ], 'created_at' => $time->addMinutes(35), 'updated_at' => $time->addMinutes(35)]);
    }

    private function midwifeVisit(Visit $visit, array $person, CarbonImmutable $time, bool $historical): void
    {
        $midwife = $this->users['midwife'];
        $this->vitals($visit, $person, $time->addMinutes(8), 'Prenatal check. No bleeding or headache reported.', $midwife);
        $weeks = 12 + $this->pick('aog-'.$visit->id, 24);
        MidwifeCareRecord::forceCreate([
            'visit_id' => $visit->id, 'care_type' => 'PRENATAL', 'purpose' => 'Routine prenatal follow-up.',
            'services_provided' => 'Blood pressure and weight monitoring, fundal height, fetal heart tone, health teaching on danger signs.',
            'notes' => $historical ? 'Advised to continue ferrous sulfate and folic acid.' : null,
            'lmp' => $visit->visit_date->copy()->subWeeks($weeks)->toDateString(), 'gravida' => 1 + $this->pick('g-'.$visit->id, 3), 'para' => $this->pick('para-'.$visit->id, 2),
            'fundal_height_cm' => $weeks >= 20 ? $weeks - 1 : null, 'fetal_heart_tone' => $weeks >= 12 ? 135 + $this->pick('fht-'.$visit->id, 20) : null,
            'follow_up_on' => $visit->visit_date->copy()->addWeeks(4)->toDateString(),
            'created_by' => $midwife->id, 'updated_by' => $midwife->id, 'submission_token' => (string) Str::uuid(),
            'created_at' => $time->addMinutes(20), 'updated_at' => $time->addMinutes(20),
        ]);
    }

    private function vaccinationVisit(Visit $visit, array $person, CarbonImmutable $time, bool $historical): void
    {
        [$vaccine, $dose] = $person['age'] <= 12
            ? [['Measles-rubella (MR)', 'Dose 2'], ['Pentavalent (DPT-HepB-Hib)', 'Dose 3'], ['Oral polio vaccine', 'Dose 3']][$this->pick('vax-'.$visit->id, 3)]
            : [['Influenza vaccine', 'Annual dose'], ['Pneumococcal vaccine (PPV23)', 'Single dose']][$this->pick('vax-'.$visit->id, 2)];
        VaccinationRecord::forceCreate([
            'visit_id' => $visit->id, 'vaccine_name' => $vaccine, 'dose' => $dose, 'administered_on' => $visit->visit_date->toDateString(),
            'batch_number' => 'LOT-'.strtoupper(substr(hash('sha256', 'lot'.$visit->id), 0, 6)), 'administered_by' => $this->users['midwife']->name,
            'next_appointment' => $person['age'] <= 12 ? $visit->visit_date->copy()->addWeeks(4)->toDateString() : null, 'remarks' => 'No adverse reaction observed after 30 minutes.',
            'created_by' => $this->users['midwife']->id, 'updated_by' => $this->users['midwife']->id, 'submission_token' => (string) Str::uuid(),
            'created_at' => $time->addMinutes(20), 'updated_at' => $time->addMinutes(20),
        ]);
    }

    private function complete(Visit $visit, CarbonImmutable $time): void
    {
        $visit->forceFill(['status' => Visit::COMPLETED, 'completed_at' => $time, 'completed_by' => $this->users['information']->id, 'updated_at' => $time])->save();
    }

    private function medicinesFor(string $diagnosis): array
    {
        return match ($diagnosis) {
            'Hypertension' => [['Amlodipine', '5 mg', '30'], ['Losartan', '50 mg', '30']],
            'Type 2 diabetes mellitus' => [['Metformin', '500 mg', '60']],
            'Acute gastroenteritis' => [['Oral rehydration salts', '1 sachet', '6'], ['Zinc sulfate', '20 mg', '10']],
            'Allergic rhinitis' => [['Cetirizine', '10 mg', '7']],
            'Urinary tract infection' => [['Nitrofurantoin', '100 mg', '14']],
            'Bronchial asthma' => [['Salbutamol', '2 mg', '15']],
            'Skin infection (impetigo)' => [['Mupirocin ointment', '2%', '1'], ['Cloxacillin', '500 mg', '28']],
            default => [['Paracetamol', '500 mg', '10'], ['Amoxicillin', '500 mg', '21']],
        };
    }

    private function complaintFor(string $diagnosis): string
    {
        return match ($diagnosis) {
            'Hypertension' => 'For blood pressure monitoring and maintenance refill.',
            'Type 2 diabetes mellitus' => 'For blood sugar follow-up and maintenance refill.',
            'Acute gastroenteritis' => 'Loose bowel movement four times since last night.',
            'Allergic rhinitis' => 'Sneezing and itchy nose every morning.',
            'Urinary tract infection' => 'Painful urination for two days.',
            'Bronchial asthma' => 'Wheezing at night, no cyanosis.',
            'Skin infection (impetigo)' => 'Crusted sores on the arm for one week.',
            default => 'Cough, colds and low-grade fever for three days.',
        };
    }

    private function specimenFor(string $test): string
    {
        return match ($test) {
            'Urinalysis' => 'Midstream urine', 'Fecalysis' => 'Stool',
            'Fasting blood sugar (FBS)' => 'Venous blood (8-hour fast)', default => 'Venous blood',
        };
    }

    private function resultsFor(string $test, int $seed): array
    {
        $high = $this->pick('high-'.$seed, 4) === 0;
        $row = fn ($measurement, $value, $unit, $range, $flag = null) => ['measurement' => $measurement, 'value' => (string) $value, 'unit' => $unit, 'reference_range' => $range, 'flag' => $flag];

        return match ($test) {
            'Complete blood count (CBC)' => [
                $row('Hemoglobin', 120 + $this->pick('hb-'.$seed, 40), 'g/L', '120-160'),
                $row('Hematocrit', 0.36 + $this->pick('hct-'.$seed, 10) / 100, 'L/L', '0.36-0.48'),
                $row('White blood cell count', $high ? 12.4 : 6.8, 'x10^9/L', '5.0-10.0', $high ? 'High' : null),
                $row('Platelet count', 180 + $this->pick('plt-'.$seed, 200), 'x10^9/L', '150-400'),
            ],
            'Urinalysis' => [
                $row('Color', 'Yellow', '', ''), $row('Appearance / clarity', $high ? 'Turbid' : 'Clear', '', ''),
                $row('pH', '6.0', '', '4.5-8.0'), $row('Protein', 'Negative', '', 'Negative'), $row('Glucose', 'Negative', '', 'Negative'),
                $row('White blood cells / pus cells', $high ? '15-20' : '0-2', '/hpf', '0-5', $high ? 'High' : null),
                $row('Red blood cells', '0-2', '/hpf', '0-2'), $row('Bacteria', $high ? 'Many' : 'Few', '', ''),
            ],
            'Fasting blood sugar (FBS)' => [$row('Fasting blood sugar', $high ? 8.6 : 5.2, 'mmol/L', '3.9-6.1', $high ? 'High' : null)],
            'Fecalysis' => [$row('Stool appearance', 'Formed, brown', '', ''), $row('Ova / parasites', 'No ova or parasites seen', '', '')],
            'Blood typing' => [$row('ABO blood group', ['O', 'A', 'B', 'AB'][$this->pick('abo-'.$seed, 4)], '', ''), $row('Rh type', 'Positive', '', '')],
            default => [$row('Result', 'See laboratory notes', '', '')],
        };
    }

    private function prefixFor(CarbonImmutable $date, ?string $previous): string
    {
        $value = $this->pick('prefix-'.$date->toDateString(), 676);
        $prefix = chr(65 + intdiv($value, 26)).chr(65 + $value % 26);

        return $prefix === $previous ? chr(65 + ($value + 1) % 26).chr(65 + $value % 26) : $prefix;
    }

    private function pick(string $key, int $choices): int
    {
        return hexdec(substr(hash('sha256', 'presentation-v1-'.$key), 0, 7)) % $choices;
    }
}
