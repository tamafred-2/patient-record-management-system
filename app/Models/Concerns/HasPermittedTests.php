<?php

namespace App\Models\Concerns;

// Shared by per-visit grants and standing nurse authorizations: "any test" or a doctor-selected list.
trait HasPermittedTests
{
    public static function catalog(): array
    {
        return array_merge(...array_values(config('laboratory.tests')));
    }

    // Catalog tests the nurse may choose from, in catalog order.
    public function permittedTests(): array
    {
        $allowed = $this->allowed_tests ?? array_filter([$this->test_name ?? null]);

        return $this->allow_any_test ? self::catalog() : array_values(array_intersect(self::catalog(), $allowed));
    }

    public function allowedTestsLabel(): string
    {
        return $this->allow_any_test ? 'Any test in the laboratory catalog' : implode(', ', $this->allowed_tests ?? array_filter([$this->test_name ?? null]));
    }
}
