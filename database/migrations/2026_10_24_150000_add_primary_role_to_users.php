<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('primary_role')->nullable());

        DB::table('users')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $roles = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_type', 'App\\Models\\User')->where('model_id', $user->id)
                    ->where('guard_name', 'web')->pluck('name');
                if ($roles->count() === 1 && in_array($roles->first(), ['System Admin', 'Doctor / Medical Officer', 'Nurse', 'Information Staff', 'MedTech', 'Pharmacist', 'Midwife'], true)) {
                    DB::table('users')->where('id', $user->id)->update(['primary_role' => $roles->first()]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('primary_role'));
    }
};
