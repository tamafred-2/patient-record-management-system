<?php

namespace App\Support;

use App\Models\User;
use App\Models\Visit;

// Nurses (vitals.*) record vital signs for every visit. Midwives (vitals.midwife) record them only for the
// Midwife Care and Vaccination visits they handle, without gaining the nurse assessment queue or analytics.
class VitalsAccess
{
    public const MIDWIFE_SERVICES = ['MIDWIFE_CARE', 'VACCINATION'];

    public static function midwifeVisit(Visit $visit): bool
    {
        return in_array($visit->service?->seed_key, self::MIDWIFE_SERVICES, true);
    }

    public static function viaMidwife(User $user, Visit $visit): bool
    {
        return ! $user->can('vitals.view') && $user->can('vitals.midwife') && self::midwifeVisit($visit);
    }

    public static function canView(User $user, Visit $visit): bool
    {
        return $user->can('vitals.view') || self::viaMidwife($user, $visit);
    }

    public static function canRecord(User $user, Visit $visit, bool $existing): bool
    {
        return $user->can($existing ? 'vitals.update' : 'vitals.create') || self::viaMidwife($user, $visit);
    }

    // Where the midwife returns to: the care or vaccination page for this visit.
    public static function midwifeReturnRoute(Visit $visit): string
    {
        return $visit->service?->seed_key === 'VACCINATION' ? 'vaccinations.show' : 'midwife-care.show';
    }
}
