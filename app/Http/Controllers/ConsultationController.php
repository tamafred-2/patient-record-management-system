<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use App\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConsultationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('consultations.view');
        $data = $request->validate(['date' => ListFilters::pastDate(), 'search' => ListFilters::search(), 'all' => ['sometimes', 'boolean'], 'record' => ['nullable', 'in:with_itr,without_itr,issued']]);
        $search = trim($data['search'] ?? '');
        $allDates = (bool) ($data['all'] ?? false);
        $record = $data['record'] ?? null;
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'service', 'vitalSign', 'pendingAssessmentRequest'])->whereHas('patient')->has('vitalSign')
            ->when(! $allDates, fn ($query) => $query->whereDate('visit_date', $date))
            ->when($record === 'with_itr', fn ($query) => $query->has('treatmentRecord'))
            ->when($record === 'without_itr', fn ($query) => $query->doesntHave('treatmentRecord'))
            ->when($record === 'issued', fn ($query) => $query->whereHas('prescription', fn ($prescription) => $prescription->issued()))
            ->searchPatientOrQueue($search)->priorityFirst()->scrollPaginate()->withQueryString();

        return view('consultations.index', compact('visits', 'date', 'search', 'allDates', 'record'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->with(['service', 'vitalSign.updater', 'eligibilityCheck', 'pendingAssessmentRequest.requester'])->findOrFail($visit);
        $assessmentRequests = $visit->assessmentRequests()->with(['requester', 'resolver'])->latest('id')->limit(10)->get();

        return view('consultations.show', compact('patient', 'visit', 'assessmentRequests'));
    }
}
