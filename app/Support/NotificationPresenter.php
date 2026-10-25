<?php

namespace App\Support;

use App\Models\FollowupAuthorization;
use App\Models\LaboratoryRecord;
use App\Models\NurseAuthorization;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\LabOrderingAuthorized;
use App\Notifications\NurseOrderingChanged;
use App\Notifications\RegistrationAlert;
use App\Notifications\WorkflowAlert;
use Illuminate\Notifications\DatabaseNotification;

// Turns a stored notification (IDs only) into text and a link, using the viewer's current access.
// Without access, only a generic headline is shown, so no patient details leak after a role change.
class NotificationPresenter
{
    public static function present(DatabaseNotification $notification, User $viewer): array
    {
        $data = $notification->data;

        return match ($notification->type) {
            RegistrationAlert::class => self::registration($data, $viewer),
            WorkflowAlert::class => self::workflow($data, $viewer),
            NurseOrderingChanged::class => self::standing($data, $viewer),
            LabOrderingAuthorized::class => self::visitGrant($data, $viewer),
            default => ['headline' => 'Notification', 'details' => null, 'state' => null, 'url' => null, 'urgent' => false],
        };
    }

    private static function result(string $headline, ?string $details = null, ?string $state = null, ?string $url = null, bool $urgent = false): array
    {
        return compact('headline', 'details', 'state', 'url', 'urgent');
    }

    private static function registration(array $data, User $viewer): array
    {
        $isVisit = isset($data['visit_id']);
        $headline = $isVisit ? 'New visit registered' : 'New patient registered';
        $patient = Patient::find($data['patient_id'] ?? null);
        if (! $viewer->is_active || ! $patient) {
            return self::result($headline);
        }
        if (! $isVisit) {
            return $viewer->can('view', $patient)
                ? self::result($headline.': '.$patient->full_name, $patient->patient_number, null, route('patients.show', $patient))
                : self::result($headline);
        }
        $visit = $patient->visits()->find($data['visit_id']);
        if (! $visit) {
            return self::result($headline);
        }
        $route = $viewer->can('view', $visit) ? 'patients.visits.show'
            : ($viewer->can('visits.monitor') ? 'visits.monitor' : null);

        return $route
            ? self::result($headline.': '.$patient->full_name, $visit->visit_number.' · Queue '.($visit->queue_reference ?? 'not recorded'), null, route($route, [$patient, $visit]))
            : self::result($headline);
    }

    private static function workflow(array $data, User $viewer): array
    {
        $kind = $data['kind'] ?? '';
        $generic = [
            'visit_waiting' => 'New visit waiting for assessment', 'reassessment_requested' => 'Reassessment requested',
            'vitals_ready' => 'Patient ready for consultation', 'lab_ordered' => 'New laboratory order', 'lab_completed' => 'Laboratory request updated',
            'prescription_issued' => 'Prescription ready for dispensing', 'emergency' => 'Emergency visit', 'followup_due' => 'Follow-up visit due',
        ][$kind] ?? 'Visit update';
        $urgent = $kind === 'emergency';
        $visit = Visit::with(['patient', 'service'])->find($data['visit_id'] ?? null);
        [$allowed, $route] = match ($kind) {
            // Midwives are sent to their own care or vaccination page for the visits they handle.
            'visit_waiting' => $visit && VitalsAccess::viaMidwife($viewer, $visit) && ($viewer->can('midwife-care.view') || $viewer->can('vaccinations.view'))
                ? [true, VitalsAccess::midwifeReturnRoute($visit)] : [$viewer->can('vitals.view'), 'assessments.edit'],
            'reassessment_requested' => [$viewer->can('vitals.view'), 'assessments.edit'],
            'vitals_ready' => [$viewer->can('consultations.view'), 'consultations.show'],
            'lab_ordered' => [$viewer->can('laboratory.view'), 'laboratory.show'],
            'lab_completed' => [FollowupAccess::doctor($viewer) || FollowupAccess::nurse($viewer), 'followups.show'],
            'prescription_issued' => [$viewer->can('pharmacy.view'), 'pharmacy.show'],
            'emergency' => $viewer->can('consultations.view') ? [true, 'consultations.show'] : [$viewer->can('vitals.view'), 'assessments.edit'],
            'followup_due' => $viewer->can('viewAny', Visit::class) ? [true, 'patients.visits.show'] : [$viewer->can('consultations.view'), 'consultations.show'],
            default => [false, null],
        };
        if (! $visit?->patient || ! $allowed) {
            return self::result($generic, null, null, null, $urgent);
        }
        $patient = $visit->patient->full_name;
        $where = $visit->visit_number.' · Queue '.($visit->queue_reference ?? 'not recorded').($visit->priority !== 'REGULAR' ? ' · '.$visit->priorityLabel() : '');
        $actor = isset($data['actor_id']) ? User::find($data['actor_id'])?->name : null;
        $records = LaboratoryRecord::whereIn('id', $data['laboratory_record_ids'] ?? [])->where('visit_id', $visit->id)->get();
        $headline = match ($kind) {
            'visit_waiting' => "New visit waiting for assessment: {$patient}",
            'reassessment_requested' => ($actor ?? 'A doctor')." requested a reassessment for {$patient}",
            'vitals_ready' => "{$patient} is ready for consultation (assessment saved)",
            'lab_ordered' => "New laboratory order for {$patient}: ".($records->pluck('test_name')->join(', ') ?: 'see visit'),
            'lab_completed' => ($records->pluck('test_name')->join(', ') ?: 'Laboratory request')." for {$patient}: ".($records->first() ? LaboratoryRecord::STATUSES[$records->first()->availability_status] : 'updated'),
            'prescription_issued' => "Prescription issued for {$patient}",
            'emergency' => "EMERGENCY visit: {$patient}",
            'followup_due' => 'Follow-up visit due'.($visit->follow_up_on ? ' '.$visit->follow_up_on->format('M j, Y') : '').": {$patient}",
            default => $generic,
        };
        $details = $kind === 'followup_due' && $visit->follow_up_reason ? $where.' · '.$visit->follow_up_reason : $where;

        return self::result($headline, $details, $visit->isOpen() ? null : 'Visit '.strtolower($visit->status), route($route, [$visit->patient, $visit]), $urgent);
    }

    private static function standing(array $data, User $viewer): array
    {
        $state = $data['state'] ?? '';
        $word = ['on' => 'turned on', 'changed' => 'updated', 'off' => 'turned off'][$state] ?? 'changed';
        $grant = FollowupAccess::nurse($viewer) ? NurseAuthorization::with(['doctor', 'revoker'])->where('nurse_id', $viewer->id)->find($data['nurse_authorization_id'] ?? null) : null;
        if (! $grant) {
            return self::result('Laboratory ordering authorization '.$word);
        }
        $actor = $state === 'off' ? ($grant->revoker?->name ?? 'A doctor') : $grant->doctor->name;

        return self::result(
            "{$actor} (doctor) {$word} your laboratory ordering authorization",
            $state === 'off' ? 'You can no longer order laboratory tests until a doctor turns it on again.' : 'For patients you assess · Permitted: '.$grant->allowedTestsLabel(),
            $grant->revoked_at ? 'No longer active' : ($grant->usable() ? 'Active' : 'Not usable: ask a doctor to renew it'),
            $state === 'off' ? null : route('assessments.index', ['authorized' => 1]),
        );
    }

    private static function visitGrant(array $data, User $viewer): array
    {
        $grant = FollowupAccess::nurse($viewer) ? FollowupAuthorization::with(['doctor', 'visit.patient'])->where('nurse_id', $viewer->id)->find($data['authorization_id'] ?? null) : null;
        if (! $grant?->visit?->patient) {
            return self::result('Laboratory ordering authorization');
        }

        return self::result(
            $grant->doctor->name.' (doctor) authorized you to order laboratory tests for '.$grant->visit->patient->full_name,
            $grant->visit->visit_number.' · Queue '.($grant->visit->queue_reference ?? 'not recorded').' · Permitted: '.$grant->allowedTestsLabel(),
            $grant->revoked_at ? 'Turned off by the doctor' : ($grant->used_at ? 'Order sent' : ($grant->visit->isOpen() ? 'Ready to order' : 'Visit closed')),
            route('followups.show', [$grant->visit->patient, $grant->visit]),
        );
    }
}
