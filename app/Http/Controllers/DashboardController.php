<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('dashboard.view');
        $definitions = [
            ['analytics.view', 'Analytics', 'analytics.index', 'Review trends for your authorized modules'],
            ['patients.view', 'Patients', 'patients.index', 'Find or register a patient'],
            ['vitals.view', 'Initial assessment', 'assessments.index', 'Record vital signs'],
            ['eligibility.view', 'PhilHealth confirmation', 'eligibility.index', 'Confirm records in the existing system'],
            ['consultations.view', 'Doctor visits', 'consultations.index', 'Review visits, ITR and prescriptions'],
            ['midwife-care.view', 'Midwife care', 'midwife-care.index', 'Record care services, notes and follow-up dates'],
            ['vaccinations.view', 'Vaccination', 'vaccinations.index', 'Record administered vaccines and review vaccination history'],
            ['pharmacy.view', 'Pharmacy', 'pharmacy.index', 'Record medicine releases'],
            ['laboratory.view', 'Laboratory', 'laboratory.index', 'Track local services and external advice'],
            ['settings.manage', 'Services', 'services.index', 'Manage service reference data'],
            ['audit.view', 'Audit log', 'audit.index', 'Review recorded activity'],
            ['reports.view', 'Reports', 'reports.index', 'Review aggregate service counts'],
        ];
        $cards = array_values(array_filter($definitions, fn ($row) => Gate::allows($row[0])));
        if (Gate::allows('users.manage') && Gate::allows('roles.manage')) {
            $cards[] = ['users.manage', 'User Accounts', 'staff.index', 'Manage user access'];
        }

        $today = now('Asia/Manila')->toDateString();
        $todayVisits = (Gate::allows('viewAny', Visit::class) || Gate::allows('analytics.overview') || Gate::allows('consultations.view'))
            ? Visit::with(['patient', 'service'])->whereHas('patient')->whereDate('visit_date', $today)->latest('id')->get() : null;

        $todayPharmacy = Gate::allows('pharmacy.view')
            ? Visit::with(['patient', 'prescription'])->whereHas('patient')
                ->whereHas('prescription', fn ($query) => $query->where('status', 'ISSUED'))
                ->whereDate('visit_date', $today)->latest('id')->paginate(10, ['*'], 'pharmacy_page')
            : null;

        $todayVaccinations = Gate::allows('vaccinations.view')
            ? Visit::vaccinationVisits()->with('patient')->withCount('vaccinationRecords')->whereDate('visit_date', $today)->latest('id')->paginate(10, ['*'], 'vaccination_page') : null;

        $todayMidwifeCare = Gate::allows('midwife-care.view')
            ? Visit::midwifeCareVisits()->with('patient')->withCount('midwifeCareRecords')->whereDate('visit_date', $today)->latest('id')->paginate(10, ['*'], 'midwife_care_page') : null;

        return view('dashboard', compact('cards', 'today', 'todayVisits', 'todayPharmacy', 'todayVaccinations', 'todayMidwifeCare'));
    }
}
