<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HistoryController extends Controller
{
    public function show(Request $request, Patient $patient)
    {
        Gate::authorize('history.view');
        $filters = $request->validate(Visit::historyFilterRules());
        $clinical = Gate::allows('consultations.view');
        $query = $patient->visits()->with('service')->filterHistory($filters);
        if ($clinical) {
            $query->with(['vitalSign', 'eligibilityCheck', 'treatmentRecord', 'prescription.dispensings', 'laboratoryRecords', 'dispositionRecords']);
        }
        $visits = $query->orderByDesc('visit_date')->orderByDesc('id')->scrollPaginate();

        return view('patients.timeline', compact('patient', 'visits', 'clinical'));
    }
}
