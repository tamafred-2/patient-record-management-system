<?php

namespace App\Support;

class AccountRoles
{
    public const ADDITIONAL = [
        'System Admin' => ['Information Staff'],
        'Doctor / Medical Officer' => ['Nurse'],
        'Nurse' => [],
        'Information Staff' => [],
        'MedTech' => [],
        'Pharmacist' => [],
        'Midwife' => [],
    ];
}
