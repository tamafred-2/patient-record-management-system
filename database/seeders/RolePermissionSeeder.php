<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const ROLES = [
        'System Admin', 'Information Staff', 'Nurse',
        'Doctor / Medical Officer', 'MedTech', 'Pharmacist', 'Midwife',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Add clinical permissions with their feature's record-scoping policies.
        foreach (['midwife-care.view', 'midwife-care.record', 'vaccinations.view', 'vaccinations.record', 'analytics.view', 'analytics.overview', 'dashboard.view', 'users.manage', 'roles.manage', 'patients.view', 'patients.create', 'patients.update', 'visits.view', 'visits.create', 'visits.complete', 'visits.monitor', 'settings.manage', 'vitals.view', 'vitals.create', 'vitals.update', 'eligibility.view', 'eligibility.verify', 'consultations.view', 'itr.create', 'itr.update', 'prescriptions.view', 'prescriptions.create', 'prescriptions.update', 'pharmacy.view', 'pharmacy.dispense', 'laboratory.view', 'laboratory.record', 'audit.view', 'dispositions.view', 'dispositions.record', 'history.view', 'reports.view', 'reports.export', 'visits.export'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // DatabaseSeeder suppresses model events, including automatic cache resets.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name) {
            $role = Role::findOrCreate($name, 'web');
            $role->givePermissionTo('dashboard.view');
            if ($name !== 'Midwife') {
                $role->givePermissionTo('analytics.view');
            }
            if ($name === 'System Admin') {
                $role->givePermissionTo(['analytics.overview', 'visits.monitor']);
                $role->givePermissionTo(['users.manage', 'roles.manage', 'settings.manage', 'eligibility.view', 'eligibility.verify', 'audit.view', 'reports.view', 'reports.export']);
            }
            if ($name === 'Doctor / Medical Officer') {
                $role->givePermissionTo(['visits.complete', 'consultations.view', 'itr.create', 'itr.update', 'prescriptions.view', 'prescriptions.create', 'prescriptions.update', 'history.view', 'dispositions.view', 'dispositions.record', 'visits.export']);
            }
            if ($name === 'Pharmacist') {
                $role->givePermissionTo(['pharmacy.view', 'pharmacy.dispense']);
            }
            if ($name === 'MedTech') {
                $role->givePermissionTo(['laboratory.view', 'laboratory.record']);
            }
            if ($name === 'Midwife') {
                $role->givePermissionTo(['vaccinations.view', 'vaccinations.record', 'midwife-care.view', 'midwife-care.record']);
            }
            if ($name === 'Nurse') {
                $role->givePermissionTo(['vitals.view', 'vitals.create', 'vitals.update']);
            }
            if ($name === 'Information Staff') {
                $role->givePermissionTo(['patients.view', 'patients.create', 'patients.update', 'visits.view', 'visits.create', 'visits.complete', 'history.view']);
            }
        }

        DB::transaction(function () {
            $legacy = Role::where('name', 'IT / PhilHealth Staff')->where('guard_name', 'web')->first();
            if (! $legacy) {
                return;
            }
            User::role($legacy)->get()->each(function (User $user) use ($legacy) {
                $user->assignRole('System Admin');
                $user->removeRole($legacy);
                activity('staff')->performedOn($user)
                    ->withProperties(['source' => 'RolePermissionSeeder', 'previous_role' => $legacy->name, 'role' => 'System Admin'])
                    ->log('staff.role_consolidated');
            });
            $legacy->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
