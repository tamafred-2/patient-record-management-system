<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'staff:create-admin';

    protected $description = 'Provision the first system administrator using private interactive prompts';

    public function handle(): int
    {
        if (User::whereHas('roles', fn ($query) => $query->where('name', 'System Admin'))->exists()) {
            $this->error('An administrator already exists. Use User Accounts to provision additional users.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Name'),
            'email' => Str::lower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12), 'max:72'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            $this->callSilent('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
            $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
            $user->is_active = true;
            $user->save();
            $user->assignRole('System Admin');
            activity('staff')->performedOn($user)->withProperties(['source' => 'artisan', 'roles' => ['System Admin']])->log('staff.admin_bootstrapped');
        });

        $this->info('Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}
