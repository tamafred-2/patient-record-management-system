<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['CONSULTATION' => 'Consultation', 'LABORATORY' => 'Laboratory', 'MEDICAL_CERTIFICATE' => 'Medical Certificate', 'OTHER' => 'Other RHU Service', 'VACCINATION' => 'Vaccination', 'MIDWIFE_CARE' => 'Midwife Care'] as $code => $name) {
            if (Service::withTrashed()->where('seed_key', $code)->exists()) {
                continue;
            }
            $service = Service::withTrashed()->firstOrNew(['code' => $code], ['name' => $name, 'is_active' => true]);
            $service->seed_key = $code;
            $service->save();
        }
    }
}
