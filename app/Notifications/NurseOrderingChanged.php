<?php

namespace App\Notifications;

use App\Models\NurseAuthorization;
use Illuminate\Notifications\Notification;

// Tells a nurse their standing laboratory-ordering authority was turned on, changed or turned off. IDs only.
class NurseOrderingChanged extends Notification
{
    public function __construct(private NurseAuthorization $authorization, private string $state, private int $actorId) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['nurse_authorization_id' => $this->authorization->id, 'state' => $this->state, 'doctor_id' => $this->actorId];
    }
}
