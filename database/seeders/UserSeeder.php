<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class UserSeeder extends Seeder
{
    public const PASSWORD = 'Password!';

    public const ACCOUNTS = [
        'admin@rhu.test' => 'System Admin',
        'information@rhu.test' => 'Information Staff',
        'nurse@rhu.test' => 'Nurse',
        'doctor@rhu.test' => 'Doctor / Medical Officer',
        'medtech@rhu.test' => 'MedTech',
        'pharmacist@rhu.test' => 'Pharmacist',
        'midwife@rhu.test' => 'Midwife',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo accounts may only be seeded in local or testing environments.');
        }

        DB::transaction(function () {
            $this->call(RolePermissionSeeder::class);

            foreach (self::ACCOUNTS as $email => $role) {
                if (User::where('email', $email)->exists()) {
                    $this->command?->warn("Skipped existing account: {$email}");

                    continue;
                }

                $user = new User([
                    'name' => 'Demo '.$role,
                    'email' => $email,
                    'password' => self::PASSWORD,
                ]);
                $user->is_active = true;
                $user->save();
                $user->assignRole($role);
                activity('staff')->performedOn($user)
                    ->withProperties(['source' => 'UserSeeder', 'roles' => [$role], 'is_active' => true])
                    ->log('staff.demo_seeded');
            }
        });

        $this->command?->info('Demo account seeding complete. Existing accounts were left unchanged.');
    }
}
