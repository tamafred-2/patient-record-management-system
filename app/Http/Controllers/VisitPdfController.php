<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class VisitPdfController extends Controller
{
    public function __invoke(Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        Gate::authorize('visits.export');
        $visit = $patient->visits()->with(['service', 'vitalSign', 'treatmentRecord'])->findOrFail($visit);
        $response = Pdf::loadView('consultations.pdf', compact('patient', 'visit'))->setOption('isRemoteEnabled', false)->download('visit-'.$visit->id.'-summary.pdf')->header('Cache-Control', 'no-store, private');
        activity('exports')->causedBy(auth()->user())->performedOn($visit)->log('visit.summary_exported');

        return $response;
    }
}
