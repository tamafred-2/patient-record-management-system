<?php

namespace App\Support;

use App\Models\User;
use App\Models\Visit;
use App\Notifications\WorkflowAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

// Who is told about which step of the visit workflow. The acting user is never notified about their own action.
class WorkflowAlerts
{
    // Active users holding a role and every listed permission.
    public static function staff(string $role, array $permissions): Collection
    {
        return User::role($role)->where('is_active', true)->get()
            ->filter(fn (User $user) => collect($permissions)->every(fn ($permission) => $user->can($permission)))->values();
    }

    public static function nurses(): Collection
    {
        return self::staff('Nurse', ['vitals.view']);
    }

    public static function doctors(): Collection
    {
        return self::staff('Doctor / Medical Officer', ['consultations.view']);
    }

    public static function medtechs(): Collection
    {
        return self::staff('MedTech', ['laboratory.view']);
    }

    public static function pharmacists(): Collection
    {
        return self::staff('Pharmacist', ['pharmacy.view']);
    }

    // Midwives who can open the given visit's care or vaccination page and record its vital signs.
    public static function midwivesFor(Visit $visit): Collection
    {
        $permission = ['MIDWIFE_CARE' => 'midwife-care.view', 'VACCINATION' => 'vaccinations.view'][$visit->service?->seed_key] ?? null;

        return $permission ? self::staff('Midwife', [$permission, 'vitals.midwife']) : collect();
    }

    public static function frontDesk(): Collection
    {
        return self::staff('Information Staff', ['visits.view']);
    }

    public static function send(string $kind, Visit $visit, iterable $recipients, ?User $actor, array $extra = []): void
    {
        $users = collect($recipients)->filter()->unique('id')->reject(fn (User $user) => $actor && $user->is($actor))->values();
        if ($users->isNotEmpty()) {
            Notification::send($users, new WorkflowAlert($kind, $visit->id, $actor?->id, $extra));
        }
    }

    public static function emergency(Visit $visit, ?User $actor): void
    {
        self::send('emergency', $visit, self::nurses()->merge(self::doctors()), $actor);
    }
}
