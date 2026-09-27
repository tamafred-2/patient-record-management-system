<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Support\Facades\Gate;

class HistoryController extends Controller
{
    public function show(Patient $patient)
    {
        Gate::authorize('history.view');
        $clinical = Gate::allows('consultations.view');
        $query = $patient->visits()->with('service');
        if ($clinical) {
            $query->with(['vitalSign', 'eligibilityCheck', 'treatmentRecord', 'prescription.dispensings', 'laboratoryRecords', 'dispositionRecords']);
        }
        $visits = $query->orderByDesc('visit_date')->orderByDesc('id')->paginate(10);

        return view('patients.timeline', compact('patient', 'visits', 'clinical'));
    }
}
