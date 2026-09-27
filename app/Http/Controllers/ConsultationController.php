<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('consultations.view');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'search' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['search'] ?? '');
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'service', 'vitalSign'])->whereHas('patient')->whereDate('visit_date', $date)->searchPatientOrQueue($search)->latest('id')->paginate(20)->withQueryString();

        return view('consultations.index', compact('visits', 'date', 'search'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->with(['service', 'vitalSign', 'eligibilityCheck'])->findOrFail($visit);

        return view('consultations.show', compact('patient', 'visit'));
    }
}
