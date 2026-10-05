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
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'search' => ['nullable', 'string', 'max:100'], 'all' => ['sometimes', 'boolean'], 'record' => ['nullable', 'in:with_itr,without_itr,issued']]);
        $search = trim($data['search'] ?? '');
        $allDates = (bool) ($data['all'] ?? false);
        $record = $data['record'] ?? null;
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'service', 'vitalSign'])->whereHas('patient')
            ->when(! $allDates, fn ($query) => $query->whereDate('visit_date', $date))
            ->when($record === 'with_itr', fn ($query) => $query->has('treatmentRecord'))
            ->when($record === 'without_itr', fn ($query) => $query->doesntHave('treatmentRecord'))
            ->when($record === 'issued', fn ($query) => $query->whereHas('prescription', fn ($prescription) => $prescription->where('status', 'ISSUED')))
            ->searchPatientOrQueue($search)->latest('id')->paginate(20)->withQueryString();

        return view('consultations.index', compact('visits', 'date', 'search', 'allDates', 'record'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->with(['service', 'vitalSign', 'eligibilityCheck'])->findOrFail($visit);

        return view('consultations.show', compact('patient', 'visit'));
    }
}
