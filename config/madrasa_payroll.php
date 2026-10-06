<?php

/*
| Payroll defaults for Saudi private schools. GOSI contributions are taken
| on the contributory wage (basic + housing), between the minimum and the
| cap. The percentages below are the long-standing rates; employees who
| joined the system from July 2024 have an annuities rate that rises year
| by year. Check the current rates with GOSI before the first payroll and
| change them here (or per school later) as needed.
*/

return [
    'currency' => 'SAR',

    'gosi' => [
        'min_wage' => 1500,
        'max_wage' => 45000,
        // Annuities 9% + unemployment insurance (SANED) 0.75% for the employee;
        // annuities 9% + occupational hazards 2% + SANED 0.75% for the employer.
        'saudi' => ['employee' => 9.75, 'employer' => 11.75],
        // Non-Saudis: occupational hazards only, paid by the employer.
        'non_saudi' => ['employee' => 0.0, 'employer' => 2.0],
    ],
];
