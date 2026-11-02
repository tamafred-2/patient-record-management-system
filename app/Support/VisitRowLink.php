<?php

namespace App\Support;

use App\Models\User;
use App\Models\Visit;

// Where a clickable visit row leads for the current user. Dashboard tables have no
// Action column, so the visit number carries the destination the role works in.
class VisitRowLink
{
    public const TODAY = 'today';

    public static function for(Visit $visit, User $user, ?string $section = null): ?string
    {
        $scope = [$visit->patient_id, $visit];

        return match (true) {
            $section === 'assessments' => route('assessments.edit', $scope),
            $section === 'laboratory' => route('laboratory.show', $scope),
            $section === 'pharmacy' => route('pharmacy.show', $scope),
            $section === 'midwife' => route('midwife-care.show', $scope),
            $section === 'vaccinations' => route('vaccinations.show', $scope),
            $section === self::TODAY && $user->can('consultations.view') => route('consultations.show', $scope),
            $section === self::TODAY && $user->can('eligibility.view') => route('eligibility.edit', $scope),
            $user->can('view', $visit) => route('patients.visits.show', $scope),
            default => null,
        };
    }
}
