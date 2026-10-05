<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QueueNumbers
{
    public function reserve(Patient $patient, User $user): object
    {
        return DB::transaction(function () use ($patient, $user) {
            $date = now('Asia/Manila')->toDateString();
            $previousPrefix = DB::table('queue_days')->where('queue_date', now('Asia/Manila')->subDay()->toDateString())->value('prefix');
            do {
                $prefix = chr(random_int(65, 90)).chr(random_int(65, 90));
            } while ($prefix === $previousPrefix);
            DB::table('queue_days')->insertOrIgnore(['queue_date' => $date, 'prefix' => $prefix, 'last_number' => 0]);
            $day = DB::table('queue_days')->where('queue_date', $date)->lockForUpdate()->first();
            do {
                $number = ++$day->last_number;
                if ($number > 9999) {
                    throw ValidationException::withMessages(['queue_token' => 'The daily queue limit of 9,999 numbers has been reached.']);
                }
                $reference = $day->prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            } while (Visit::whereDate('visit_date', $date)->where('queue_reference', $reference)->exists());
            DB::table('queue_days')->where('queue_date', $date)->update(['last_number' => $number]);
            $id = DB::table('queue_reservations')->insertGetId([
                'token' => (string) Str::uuid(), 'patient_id' => $patient->id,
                'user_id' => $user->id, 'created_at' => now(),
                'queue_date' => $date, 'queue_reference' => $reference,
            ]);

            return DB::table('queue_reservations')->where('id', $id)->first();
        }, 5);
    }
}
