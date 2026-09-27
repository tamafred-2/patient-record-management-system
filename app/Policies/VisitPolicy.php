<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('patients.view') && $user->can('visits.view');
    }

    public function complete(User $user, Visit $visit): bool
    {
        return $user->is_active && $user->can('visits.complete') && $visit->patient
            && ($this->viewAny($user) || $user->can('consultations.view'));
    }

    public function create(User $user, Patient $patient): bool
    {
        return $this->viewAny($user) && $user->can('view', $patient) && $user->can('visits.create');
    }

    public function view(User $user, Visit $visit): bool
    {
        return $this->viewAny($user) && $visit->patient && $user->can('view', $visit->patient);
    }
}
