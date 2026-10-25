<?php

namespace App\Notifications;

use App\Models\FollowupAuthorization;
use Illuminate\Notifications\Notification;

// Stores record IDs only; patient and doctor details are loaded under current permissions when displayed.
class LabOrderingAuthorized extends Notification
{
    public function __construct(private FollowupAuthorization $authorization) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'authorization_id' => $this->authorization->id,
            'visit_id' => $this->authorization->visit_id,
            'doctor_id' => $this->authorization->doctor_id,
        ];
    }
}
