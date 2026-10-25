<?php

namespace App\Support;

use App\Models\User;

// Who may authorize, and who may act on, nurse laboratory ordering.
class FollowupAccess
{
    public static function doctor(?User $user): bool
    {
        return $user && $user->is_active && $user->hasRole('Doctor / Medical Officer') && $user->can('followups.authorize') && $user->can('consultations.view');
    }

    public static function nurse(?User $user): bool
    {
        return $user && $user->is_active && $user->hasRole('Nurse') && $user->can('followups.request') && $user->can('vitals.view');
    }
}
