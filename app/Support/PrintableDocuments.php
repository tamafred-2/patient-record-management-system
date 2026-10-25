<?php

namespace App\Support;

use App\Http\Controllers\PrintController;
use App\Models\DispositionRecord;
use App\Models\User;
use App\Models\Visit;

// The single list of printouts for a visit, filtered by what the viewer may open. Each entry mirrors the
// authorization of the route it links to, so the print center never offers a link that would be refused.
class PrintableDocuments
{
    /** @return list<array{group: string, label: string, url: ?string, note: ?string}> */
    public static function forVisit(User $user, Visit $visit): array
    {
        $visit->loadMissing(['patient', 'service', 'prescription', 'medicalCertificates', 'dispositionRecords', 'laboratoryRecords']);
        $patient = $visit->patient;
        $docs = [];
        $add = function (string $group, string $label, ?string $url, ?string $note = null) use (&$docs) {
            $docs[] = compact('group', 'label', 'url', 'note');
        };

        if ($user->can('view', $visit)) {
            $add('Visit', 'Visit stub', route('visits.stub', [$patient, $visit]), $visit->priority !== 'REGULAR' ? $visit->priorityLabel() : 'Queue number '.($visit->queue_reference ?? 'not recorded'));
        }
        if ($user->can('consultations.view') && $user->can('visits.export')) {
            $add('Visit', 'Visit summary (PDF)', route('visits.pdf', [$patient, $visit]), 'Internal clinical summary of this visit');
        }
        if ($user->can('consultations.view') && $user->can('certificates.print')) {
            foreach ($visit->medicalCertificates as $certificate) {
                $add('Certificates', 'Medical certificate #'.$certificate->id, route('certificates.print', [$patient, $visit, $certificate]), $certificate->created_at->timezone('Asia/Manila')->format('M j, Y'));
            }
            if ($user->can('certificates.prepare') && $visit->status === 'OPEN') {
                $add('Certificates', 'Prepare a medical certificate', route('certificates.index', [$patient, $visit]), 'Opens the certificate editor');
            }
        }
        if (($user->can('prescriptions.view') || $user->can('pharmacy.view')) && $visit->prescription) {
            $issued = $visit->prescription->status === 'ISSUED';
            $add('Medicines', 'Prescription', $issued ? route('prints.prescription', [$patient, $visit]) : null, $issued ? $visit->prescription->prescription_number : 'Not issued yet');
        }
        if ($user->can('dispositions.view')) {
            foreach ($visit->dispositionRecords->whereIn('type', array_keys(PrintController::REFERRAL_TYPES)) as $record) {
                $add('Referrals', PrintController::REFERRAL_TYPES[$record->type], route('prints.referral', [$patient, $visit, $record]), $record->facility_name ?: DispositionRecord::TYPES[$record->type]);
            }
        }
        $labStaff = $user->can('laboratory.view');
        $doctor = FollowupAccess::doctor($user);
        $nurse = FollowupAccess::nurse($user);
        foreach ($visit->laboratoryRecords->whereNotNull('order_reference')->groupBy('order_reference') as $order => $records) {
            if ($labStaff || $doctor || ($nurse && $records->first()->created_by === $user->id)) {
                $add('Laboratory', 'Lab request slip', route('followups.slip', [$patient, $visit, $order]), $records->pluck('test_name')->join(', '));
            }
        }
        if ($labStaff || $doctor) {
            foreach ($visit->laboratoryRecords->where('availability_status', 'EXTERNAL_ADVISED') as $record) {
                $add('Laboratory', 'External laboratory request', route('prints.external-lab', [$patient, $visit, $record]), $record->test_name);
            }
        }

        return $docs;
    }
}
