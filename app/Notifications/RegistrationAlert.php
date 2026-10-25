<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RegistrationAlert extends Notification
{
    public function __construct(private int $patientId, private ?int $visitId, private int $actorId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['patient_id' => $this->patientId, 'visit_id' => $this->visitId, 'actor_id' => $this->actorId];
    }
}
