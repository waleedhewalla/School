<?php

/*
| Default stages and grade levels for Saudi general education, created
| for every new school. Schools can rename or remove them afterwards.
*/

return [
    [
        'code' => 'kg',
        'name_ar' => 'رياض الأطفال',
        'name_en' => 'Kindergarten',
        'grades' => [
            ['المستوى الأول', 'KG 1'],
            ['المستوى الثاني', 'KG 2'],
            ['المستوى الثالث', 'KG 3'],
        ],
    ],
    [
        'code' => 'primary',
        'name_ar' => 'المرحلة الابتدائية',
        'name_en' => 'Primary',
        'grades' => [
            ['الصف الأول الابتدائي', 'Grade 1'],
            ['الصف الثاني الابتدائي', 'Grade 2'],
            ['الصف الثالث الابتدائي', 'Grade 3'],
            ['الصف الرابع الابتدائي', 'Grade 4'],
            ['الصف الخامس الابتدائي', 'Grade 5'],
            ['الصف السادس الابتدائي', 'Grade 6'],
        ],
    ],
    [
        'code' => 'intermediate',
        'name_ar' => 'المرحلة المتوسطة',
        'name_en' => 'Intermediate',
        'grades' => [
            ['الصف الأول المتوسط', 'Grade 7'],
            ['الصف الثاني المتوسط', 'Grade 8'],
            ['الصف الثالث المتوسط', 'Grade 9'],
        ],
    ],
    [
        'code' => 'secondary',
        'name_ar' => 'المرحلة الثانوية',
        'name_en' => 'Secondary',
        'grades' => [
            ['الصف الأول الثانوي', 'Grade 10'],
            ['الصف الثاني الثانوي', 'Grade 11'],
            ['الصف الثالث الثانوي', 'Grade 12'],
        ],
    ],
];
