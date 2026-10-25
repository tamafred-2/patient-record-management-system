<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\ExportSafety;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class VisitPdfController extends Controller
{
    public function __invoke(Patient $patient, string $visit, ExportSafety $safety)
    {
        Gate::authorize('consultations.view');
        Gate::authorize('visits.export');
        $visit = $patient->visits()->with(['service', 'vitalSign', 'treatmentRecord'])->findOrFail($visit);

        return $safety->run(auth()->id(), function () use ($patient, $visit, $safety) {
            $html = view('consultations.pdf', compact('patient', 'visit'))->render();
            $safety->checkHtml($html);
            $response = Pdf::loadHTML($html)->setOption('isRemoteEnabled', false)->download('visit-'.$visit->id.'-summary.pdf')->header('Cache-Control', 'no-store, private');
            activity('exports')->causedBy(auth()->user())->performedOn($visit)->log('visit.summary_exported');

            return $response;
        });
    }
}
