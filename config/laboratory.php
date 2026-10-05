<?php

return [
    // Published Calasiao MHO charter, 2020 archive. Current facility availability needs local confirmation.
    'source' => 'https://calasiao.gov.ph/wp-content/uploads/2020/03/OFFICE-OF-THE-MUNICIPAL-HEALTH-OFFICER.pdf',
    'tests' => [
        'Blood chemistry' => ['ALT / SGPT', 'AST / SGOT', 'BUN', 'Creatinine', 'Uric acid', 'Fasting blood sugar (FBS)', 'Cholesterol', 'HDL', 'LDL', 'Triglycerides'],
        'Hematology' => ['Complete blood count (CBC)', 'Platelet count', 'Blood typing', 'Hemoglobin / Hematocrit (Hgb/HCT)'],
        'Clinical microscopy' => ['Urinalysis', 'Fecalysis', 'Sputum examination'],
    ],
    // Entry aids, not a confirmed RHU report form. All actual values and ranges start blank.
    'components' => [
        'Urinalysis' => ['Color', 'Appearance / clarity', 'Specific gravity', 'pH', 'Protein', 'Glucose', 'Ketones', 'Bilirubin', 'Blood', 'Nitrite', 'White blood cells / pus cells', 'Red blood cells', 'Epithelial cells', 'Bacteria', 'Crystals', 'Casts', 'Mucus'],
        'Complete blood count (CBC)' => ['Hemoglobin', 'Hematocrit', 'Red blood cell count', 'White blood cell count', 'Platelet count', 'Mean corpuscular volume (MCV)'],
        'Hemoglobin / Hematocrit (Hgb/HCT)' => ['Hemoglobin', 'Hematocrit'],
        'Platelet count' => ['Platelet count'],
        'Blood typing' => ['ABO blood group', 'Rh type'],
        'Fecalysis' => ['Stool appearance', 'Ova / parasites'],
        'Sputum examination' => ['Examination method', 'Reported finding'],
    ],
    'suggestions' => [
        'Urinalysis' => [
            'Color' => ['Yellow', 'Pale yellow', 'Dark yellow'],
            'Appearance / clarity' => ['Clear', 'Slightly cloudy', 'Cloudy', 'Turbid'],
            'Protein' => ['Negative', 'Trace', '1+', '2+', '3+', '4+'],
            'Glucose' => ['Negative', 'Trace', '1+', '2+', '3+', '4+'],
            'Ketones' => ['Negative', 'Trace', '1+', '2+', '3+'],
            'Bilirubin' => ['Negative', 'Positive'],
            'Blood' => ['Negative', 'Trace', '1+', '2+', '3+'],
            'Nitrite' => ['Negative', 'Positive'],
            'Bacteria' => ['None seen', 'Rare', 'Few', 'Moderate', 'Many'],
        ],
        'Blood typing' => ['ABO blood group' => ['A', 'B', 'AB', 'O'], 'Rh type' => ['Positive', 'Negative']],
    ],
];
