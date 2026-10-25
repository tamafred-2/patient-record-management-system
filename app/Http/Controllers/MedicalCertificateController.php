<?php

namespace App\Http\Controllers;

use App\Models\MedicalCertificate;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\ExportSafety;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MedicalCertificateController extends Controller
{
    public function index(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('certificates.prepare');
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->findOrFail($visit);
        $request->validate(['copy' => ['nullable', 'integer']]);
        $copy = $request->filled('copy') ? $visit->medicalCertificates()->findOrFail($request->integer('copy')) : null;
        $age = $patient->birth_date && $patient->birth_date->lte($visit->visit_date)
            ? (int) $patient->birth_date->diffInYears($visit->visit_date) : '_____';
        $barangay = $patient->barangay ?: '_____';
        $municipality = $patient->municipality ?: '_____';
        $province = $patient->province ?: '_____';
        $identity = "This is to CERTIFY that {$patient->full_name}, {$age} years old, a resident of Barangay {$barangay}, {$municipality}, {$province},";
        $date = $visit->visit_date->format('F j, Y');
        $templates = [
            'consultation' => "$identity was consulted and treated in this health care facility on $date due to ____________________.",
            'examination' => "$identity was medically examined in this health facility on $date and was found to be ____________________ at the time of examination.",
        ];
        $defaults = $copy?->content ?? [
            'body' => $templates['consultation'],
            'request_text' => "This certification is being issued upon request of {$patient->full_name} for whatever legal purposes it may serve, except for medico-legal purposes.",
            'signatory_name' => '', 'signatory_title' => 'Municipal Health Officer', 'license_number' => '',
        ];
        $template = $copy?->template ?? 'consultation';
        $examinationFindings = $this->examinationFindings($visit);

        return view('certificates.index', compact('patient', 'visit', 'templates', 'defaults', 'template', 'examinationFindings'));
    }

    public function store(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('certificates.prepare');
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->findOrFail($visit);
        $request->merge(['signatory_name' => $request->user()->name]);
        $data = $request->validate([
            'template' => ['required', Rule::in(array_keys(MedicalCertificate::TEMPLATES))],
            'body' => ['required', 'string', 'max:1800', 'not_regex:/_{3,}/'],
            'request_text' => ['required', 'string', 'max:600'],
            'findings' => ['nullable', 'string', 'max:600'],
            'remarks' => ['nullable', 'string', 'max:600'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_title' => ['required', 'string', 'max:100'],
            'license_number' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'submission_token' => ['required', 'uuid'], 'confirm' => ['accepted'],
            'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'], 'created_by' => ['prohibited'], 'content' => ['prohibited'], 'issued_at' => ['prohibited'],
        ], ['license_number.regex' => 'License number must contain numbers only, with no letters, spaces or symbols.', 'body.not_regex' => 'Complete Certificate wording: replace the underscore blank (____) with the actual reason for consultation or examination finding. The doctor must enter this statement before preparing the certificate.']);

        $record = DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $previous = MedicalCertificate::where('submission_token', $data['submission_token'])->first();
            if ($previous) {
                abort_unless($previous->visit_id === $visit->id && $previous->created_by === $request->user()->id, 409);

                return $previous;
            }
            if (! $visit->isOpen()) {
                throw ValidationException::withMessages(['visit' => 'Only open visits can receive a new certificate. Existing certificates can still be reprinted.']);
            }
            $record = new MedicalCertificate;
            $record->visit_id = $visit->id;
            $record->created_by = $request->user()->id;
            $record->submission_token = $data['submission_token'];
            $record->template = $data['template'];
            $record->content = array_merge(
                Arr::except($data, ['template', 'submission_token', 'confirm']),
                $data['template'] === 'examination' ? $this->examinationFindings($visit) : [],
            );
            $record->save();
            activity('certificates')->causedBy($request->user())->performedOn($record)
                ->withProperties(['visit_id' => $visit->id, 'template' => $record->template])->log('certificate.prepared');

            return $record;
        });

        return redirect()->route('certificates.print', [$patient, $visit, $record]);
    }

    private function examinationFindings(Visit $visit): array
    {
        $assessment = $visit->vitalSign;
        if (! $assessment) {
            return [];
        }

        return array_filter([
            'blood_pressure' => $assessment->systolic_bp !== null && $assessment->diastolic_bp !== null
                ? $assessment->systolic_bp.'/'.$assessment->diastolic_bp : null,
            'pulse_rate' => $assessment->pulse_rate,
            'respiratory_rate' => $assessment->respiratory_rate,
            'temperature' => $assessment->temperature,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function print(Request $request, Patient $patient, string $visit, string $certificate)
    {
        [$visit, $certificate] = $this->printable($patient, $visit, $certificate);
        activity('certificates')->causedBy($request->user())->performedOn($certificate)
            ->withProperties(['visit_id' => $visit->id])->log('certificate.print_viewed');

        return response()->view('certificates.print', ['patient' => $patient, 'visit' => $visit, 'certificate' => $certificate, 'content' => $certificate->content])
            ->header('Cache-Control', 'no-store, private');
    }

    public function pdf(Request $request, Patient $patient, string $visit, string $certificate, ExportSafety $safety)
    {
        [$visit, $certificate] = $this->printable($patient, $visit, $certificate);

        return $safety->run($request->user()->id, function () use ($request, $patient, $visit, $certificate, $safety) {
            $html = view('certificates.pdf', ['patient' => $patient, 'visit' => $visit, 'certificate' => $certificate, 'content' => $certificate->content])->render();
            $safety->checkHtml($html);
            $response = Pdf::loadHTML($html)->setPaper('a4', 'landscape')->setOption('isRemoteEnabled', false)
                ->download('medical-certificate-'.$certificate->id.'.pdf')->header('Cache-Control', 'no-store, private');
            activity('certificates')->causedBy($request->user())->performedOn($certificate)
                ->withProperties(['visit_id' => $visit->id])->log('certificate.pdf_downloaded');

            return $response;
        });
    }

    private function printable(Patient $patient, string $visit, string $certificate): array
    {
        Gate::authorize('certificates.print');
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->findOrFail($visit);

        return [$visit, $visit->medicalCertificates()->findOrFail($certificate)];
    }
}
