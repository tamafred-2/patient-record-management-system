<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->can('patients.view');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->viewAny($user) && ! $patient->trashed();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('patients.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->view($user, $patient) && $user->can('patients.update');
    }
}
