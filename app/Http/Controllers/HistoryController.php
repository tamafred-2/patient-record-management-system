<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// The patient file: every visit with the records each department kept. Each section is shown only to roles
// that already work with that kind of record (post-defense revision: "all of it can be seen on the patient file").
class HistoryController extends Controller
{
    public function show(Request $request, Patient $patient)
    {
        Gate::authorize('history.view');
        $filters = $request->validate(Visit::historyFilterRules());
        $user = $request->user();
        $sections = [
            'vitals' => $user->canAny(['consultations.view', 'vitals.view', 'vitals.midwife']),
            'eligibility' => $user->canAny(['consultations.view', 'eligibility.view']),
            'itr' => $user->can('consultations.view'),
            'laboratory' => $user->canAny(['consultations.view', 'laboratory.view', 'vitals.view']),
            'lab_results' => $user->canAny(['consultations.view', 'laboratory.view']),
            'pharmacy' => $user->canAny(['consultations.view', 'prescriptions.view', 'pharmacy.view']),
            'supporting' => $user->canAny(['consultations.view', 'dispositions.view']),
            'midwife' => $user->canAny(['consultations.view', 'midwife-care.view', 'vaccinations.view']),
        ];
        $clinical = in_array(true, $sections, true);
        $visits = $patient->visits()->with(array_filter([
            'service', 'vitalSign.recorder', 'eligibilityCheck', 'treatmentRecord', 'prescription.items', 'prescription.dispensings.items',
            'prescription.dispensings.pharmacist', 'laboratoryRecords.requester', 'dispositionRecords', 'medicalCertificates', 'midwifeCareRecords', 'vaccinationRecords',
        ]))->filterHistory($filters)->orderByDesc('visit_date')->orderByDesc('id')->scrollPaginate();

        return view('patients.timeline', compact('patient', 'visits', 'clinical', 'sections'));
    }
}
