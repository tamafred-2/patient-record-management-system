<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\RegistrationAlert;
use Illuminate\Support\Facades\Notification;

class RegistrationAlerts
{
    public static function send(Patient $patient, User $actor, ?Visit $visit = null): void
    {
        $recipients = WorkflowAlerts::staff('System Admin', ['audit.view'])
            ->merge(WorkflowAlerts::staff('Information Staff', ['patients.view']))
            ->unique('id')->reject(fn (User $user) => $user->is($actor))->values();

        Notification::send($recipients, new RegistrationAlert($patient->id, $visit?->id, $actor->id));
    }
}
