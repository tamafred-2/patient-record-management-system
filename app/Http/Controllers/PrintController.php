<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\FollowupAccess;
use Illuminate\Http\Request;

// Generic printouts built only from saved records. They are not official RHU form layouts; replace them when the
// RHU supplies its actual prescription, referral and laboratory request forms.
class PrintController extends Controller
{
    public const REFERRAL_TYPES = ['FORMAL_REFERRAL' => 'Referral slip', 'EMERGENCY_REFERRAL' => 'Emergency referral slip', 'ADVISED_HIGHER_FACILITY' => 'Advice to seek a higher facility'];

    public function prescription(Request $request, Patient $patient, string $visit)
    {
        abort_unless($request->user()->can('prescriptions.view') || $request->user()->can('pharmacy.view'), 403);
        $visit = $patient->visits()->with('treatmentRecord')->findOrFail($visit);
        $prescription = $visit->prescription()->where('status', 'ISSUED')->with(['items', 'doctor'])->firstOrFail();
        activity('prescriptions')->causedBy($request->user())->performedOn($prescription)->withProperties(['visit_id' => $visit->id])->log('prescription.print_viewed');

        return response()->view('prints.prescription', compact('patient', 'visit', 'prescription'))->header('Cache-Control', 'private, no-store');
    }

    public function referral(Request $request, Patient $patient, string $visit, string $record)
    {
        abort_unless($request->user()->can('dispositions.view'), 403);
        $visit = $patient->visits()->with(['vitalSign', 'treatmentRecord'])->findOrFail($visit);
        $record = $visit->dispositionRecords()->with('creator')->findOrFail($record);
        abort_unless(array_key_exists($record->type, self::REFERRAL_TYPES), 404);
        activity('dispositions')->causedBy($request->user())->performedOn($record)->withProperties(['visit_id' => $visit->id, 'type' => $record->type])->log('disposition.print_viewed');

        return response()->view('prints.referral', ['patient' => $patient, 'visit' => $visit, 'record' => $record, 'title' => self::REFERRAL_TYPES[$record->type]])->header('Cache-Control', 'private, no-store');
    }

    public function externalLab(Request $request, Patient $patient, string $visit, string $record)
    {
        abort_unless($request->user()->can('laboratory.view') || FollowupAccess::doctor($request->user()), 403);
        $visit = $patient->visits()->findOrFail($visit);
        $record = $visit->laboratoryRecords()->with(['authorizingDoctor', 'requester', 'updater'])->where('availability_status', 'EXTERNAL_ADVISED')->findOrFail($record);
        activity('laboratory')->causedBy($request->user())->performedOn($record)->withProperties(['visit_id' => $visit->id])->log('laboratory.external_slip_viewed');

        return response()->view('prints.external-lab', compact('patient', 'visit', 'record'))->header('Cache-Control', 'private, no-store');
    }
}
