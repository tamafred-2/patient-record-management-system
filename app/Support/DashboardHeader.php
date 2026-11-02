<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;

// Role-based text and primary button for the dashboard heading.
class DashboardHeader
{
    private const DESCRIPTIONS = [
        'System Admin' => 'Review system activity, reports and user access.',
        'Information Staff' => 'Register patients and organize patient visits.',
        'Nurse' => 'Review patient visits and record initial assessments.',
        'Doctor / Medical Officer' => 'Review consultations, treatment records and prescriptions.',
        'MedTech' => 'Track laboratory requests and record test results.',
        'Pharmacist' => 'Review prescriptions and record medicine releases.',
        'Midwife' => 'Review midwife care visits and immunization records.',
    ];

    private const GENERAL = 'Review your assigned services and daily work.';

    public static function description(User $user): string
    {
        $roles = $user->getRoleNames();

        return $roles->count() === 1 ? (self::DESCRIPTIONS[$roles->first()] ?? self::GENERAL) : self::GENERAL;
    }

    /** @return array{label: string, url: string}|null */
    public static function action(User $user, string $today): ?array
    {
        return match (true) {
            $user->can('create', Patient::class) => ['label' => 'Register patient', 'url' => route('patients.create')],
            $user->can('viewAny', Visit::class) || $user->can('analytics.overview') => ['label' => 'Patient Visits', 'url' => route('visits.index', ['from' => $today, 'to' => $today])],
            $user->can('consultations.view') => ['label' => 'Open doctor visits', 'url' => route('consultations.index', ['date' => $today])],
            default => null,
        };
    }
}
