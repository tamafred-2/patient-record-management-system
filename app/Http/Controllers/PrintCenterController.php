<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\PrintableDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// One place for a patient's printouts: the patient summary and every visit's documents the viewer may open.
class PrintCenterController extends Controller
{
    public const VISIT_LIMIT = 25;

    public const SUMMARY_LIMIT = 100;

    public function documents(Request $request, Patient $patient)
    {
        Gate::authorize('history.view');
        $visits = $patient->visits()->with('service')->orderByDesc('visit_date')->orderByDesc('id')->limit(self::VISIT_LIMIT + 1)->get();
        $more = $visits->count() > self::VISIT_LIMIT;
        $visits = $visits->take(self::VISIT_LIMIT);
        // A link from an older timeline visit always includes that visit, even beyond the latest 25.
        $requested = $request->validate(['visit' => ['nullable', 'integer']])['visit'] ?? null;
        if ($requested && ! $visits->contains('id', (int) $requested)) {
            $visits->push($patient->visits()->with('service')->findOrFail($requested));
        }
        $visits = $visits->map(fn ($visit) => ['visit' => $visit, 'documents' => PrintableDocuments::forVisit($request->user(), $visit)]);

        return view('print-center.patient', compact('patient', 'visits', 'more'));
    }

    // A printable overview of the patient's visits. Clinical details appear only for clinical viewers, as on the history timeline.
    public function summary(Request $request, Patient $patient)
    {
        Gate::authorize('history.view');
        $clinical = Gate::allows('consultations.view');
        $query = $patient->visits()->with('service');
        if ($clinical) {
            $query->with(['vitalSign', 'treatmentRecord', 'prescription.items', 'laboratoryRecords', 'dispositionRecords']);
        }
        $total = $patient->visits()->count();
        $visits = $query->orderByDesc('visit_date')->orderByDesc('id')->limit(self::SUMMARY_LIMIT)->get();
        activity('exports')->causedBy($request->user())->performedOn($patient)->withProperties(['visit_count' => $visits->count(), 'clinical' => $clinical])->log('patient.summary_printed');

        return response()->view('print-center.summary', compact('patient', 'visits', 'clinical', 'total'))->header('Cache-Control', 'private, no-store');
    }
}
