<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispensingController;
use App\Http\Controllers\DispositionController;
use App\Http\Controllers\EligibilityController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\MidwifeCareController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TreatmentRecordController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\VisitCompletionController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\VisitPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password/change', [PasswordController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/analytics', AnalyticsController::class)->name('analytics.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/history/{patient}', [HistoryController::class, 'show'])->name('history.show');
    Route::get('/summary/{patient}/visits/{visit}/pdf', VisitPdfController::class)->name('visits.pdf');
    Route::get('/supporting/{patient}/visits/{visit}', [DispositionController::class, 'show'])->name('dispositions.show');
    Route::post('/supporting/{patient}/visits/{visit}', [DispositionController::class, 'store'])->name('dispositions.store');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/midwife-care', [MidwifeCareController::class, 'index'])->name('midwife-care.index');
    Route::get('/midwife-care/{patient}/visits/{visit}', [MidwifeCareController::class, 'show'])->name('midwife-care.show');
    Route::post('/midwife-care/{patient}/visits/{visit}', [MidwifeCareController::class, 'save'])->name('midwife-care.store');
    Route::get('/midwife-care/{patient}/visits/{visit}/records/{record}', [MidwifeCareController::class, 'show'])->name('midwife-care.edit');
    Route::put('/midwife-care/{patient}/visits/{visit}/records/{record}', [MidwifeCareController::class, 'save'])->name('midwife-care.update');
    Route::get('/vaccinations', [VaccinationController::class, 'index'])->name('vaccinations.index');
    Route::get('/vaccinations/{patient}/visits/{visit}', [VaccinationController::class, 'show'])->name('vaccinations.show');
    Route::post('/vaccinations/{patient}/visits/{visit}', [VaccinationController::class, 'save'])->name('vaccinations.store');
    Route::get('/vaccinations/{patient}/visits/{visit}/records/{record}', [VaccinationController::class, 'show'])->name('vaccinations.edit');
    Route::put('/vaccinations/{patient}/visits/{visit}/records/{record}', [VaccinationController::class, 'save'])->name('vaccinations.update');
    Route::get('/laboratory', [LaboratoryController::class, 'index'])->name('laboratory.index');
    Route::get('/laboratory/{patient}/visits/{visit}', [LaboratoryController::class, 'show'])->name('laboratory.show');
    Route::post('/laboratory/{patient}/visits/{visit}', [LaboratoryController::class, 'save'])->name('laboratory.store');
    Route::get('/laboratory/{patient}/visits/{visit}/records/{record}', [LaboratoryController::class, 'edit'])->name('laboratory.edit');
    Route::put('/laboratory/{patient}/visits/{visit}/records/{record}', [LaboratoryController::class, 'save'])->name('laboratory.update');
    Route::get('/pharmacy', [DispensingController::class, 'index'])->name('pharmacy.index');
    Route::get('/pharmacy/{patient}/visits/{visit}', [DispensingController::class, 'show'])->name('pharmacy.show');
    Route::post('/pharmacy/{patient}/visits/{visit}', [DispensingController::class, 'store'])->name('pharmacy.store');
    Route::get('/prescriptions/{patient}/visits/{visit}', [PrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::put('/prescriptions/{patient}/visits/{visit}', [PrescriptionController::class, 'save'])->name('prescriptions.save');
    Route::post('/prescriptions/{patient}/visits/{visit}/issue', [PrescriptionController::class, 'issue'])->name('prescriptions.issue');
    Route::get('/consultations/{patient}/visits/{visit}/itr', [TreatmentRecordController::class, 'edit'])->name('itr.edit');
    Route::put('/consultations/{patient}/visits/{visit}/itr', [TreatmentRecordController::class, 'save'])->name('itr.save');
    Route::get('/consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::get('/consultations/{patient}/visits/{visit}', [ConsultationController::class, 'show'])->name('consultations.show');
    Route::get('/eligibility', [EligibilityController::class, 'index'])->name('eligibility.index');
    Route::get('/eligibility/{patient}/visits/{visit}', [EligibilityController::class, 'edit'])->name('eligibility.edit');
    Route::put('/eligibility/{patient}/visits/{visit}', [EligibilityController::class, 'save'])->name('eligibility.save');
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/{patient}/visits/{visit}', [AssessmentController::class, 'edit'])->name('assessments.edit');
    Route::put('/assessments/{patient}/visits/{visit}', [AssessmentController::class, 'save'])->name('assessments.save');
    Route::get('/patients/{patient}/visits/{visit}/completion', [VisitCompletionController::class, 'show'])->name('visits.completion');
    Route::post('/patients/{patient}/visits/{visit}/completion', [VisitCompletionController::class, 'store'])->name('visits.complete');
    Route::get('/visits', [VisitController::class, 'index'])->name('visits.index');
    Route::get('/visit-monitor/{patient}/visits/{visit}', [VisitController::class, 'monitor'])->middleware('can:visits.monitor')->name('visits.monitor');
    Route::get('/patients/{patient}/visits/create', [VisitController::class, 'create'])->name('patients.visits.create');
    Route::post('/patients/{patient}/visits', [VisitController::class, 'store'])->name('patients.visits.store');
    Route::get('/patients/{patient}/visits/{visit}', [VisitController::class, 'show'])->name('patients.visits.show');
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::patch('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');
    });
    Route::resource('patients', PatientController::class)->except('destroy');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, '__invoke'])->name('dashboard');
    Route::middleware(['can:users.manage', 'can:roles.manage'])->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{user}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::post('/staff/{user}/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:6,1')->name('staff.password.reset');
        Route::patch('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    });
});
