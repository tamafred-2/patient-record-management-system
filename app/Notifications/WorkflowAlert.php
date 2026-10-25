<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

// A work-queue alert about one visit. Stores IDs only; NotificationPresenter builds the text under current access.
class WorkflowAlert extends Notification
{
    public const KINDS = ['visit_waiting', 'reassessment_requested', 'vitals_ready', 'lab_ordered', 'lab_completed', 'prescription_issued', 'emergency', 'followup_due'];

    public function __construct(private string $kind, private int $visitId, private ?int $actorId, private array $extra = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'visit_id' => $this->visitId, 'actor_id' => $this->actorId] + $this->extra;
    }
}
