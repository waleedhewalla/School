<?php

/*
| Starting grading scales for a new school, based on the Ministry of
| Education's student assessment rules (لائحة تقويم الطالب) as reported for
| 1447/1448. Schools can edit every scale in Settings → Grading scale.
|
| Verify against the official 2025 PDFs on moe.gov.sa before relying on
| them; see docs/market-research.md.
|
| "scope" is "default" (all grades without their own scale), a stage code,
| or "grade:<stage>:<sequence>" for one grade.
*/

return [
    [
        // Primary 3–6 and intermediate: five bands, pass at 50.
        'scope' => 'default',
        'name' => 'السلم العام',
        'pass_percent' => 50,
        'bands' => [[90, 'ممتاز', 'Excellent'], [80, 'جيد جدًا', 'Very good'], [70, 'جيد', 'Good'], [50, 'مقبول', 'Acceptable'], [0, 'راسب', 'Fail']],
    ],
    [
        // Kindergarten is descriptive; there is no failing. The percentages
        // stand in for the share of skills observed.
        'scope' => 'kg',
        'name' => 'رياض الأطفال (وصفي)',
        'pass_percent' => 0,
        'bands' => [[67, 'متمكن', 'Proficient'], [34, 'يطوّر المهارة', 'Developing'], [0, 'مبتدئ', 'Beginning']],
    ],
    [
        // Grades 1–2: mastery of learning standards, pass at 75.
        'scope' => 'grade:primary:1',
        'name' => 'الصف الأول (إتقان المعايير)',
        'pass_percent' => 75,
        'bands' => [[95, 'متفوق', 'Outstanding'], [85, 'متقدم', 'Advanced'], [75, 'متمكن', 'Proficient'], [0, 'غير مجتاز', 'Not yet passed']],
    ],
    [
        'scope' => 'grade:primary:2',
        'name' => 'الصف الثاني (إتقان المعايير)',
        'pass_percent' => 75,
        'bands' => [[95, 'متفوق', 'Outstanding'], [85, 'متقدم', 'Advanced'], [75, 'متمكن', 'Proficient'], [0, 'غير مجتاز', 'Not yet passed']],
    ],
    [
        // Secondary (المسارات): ten bands, pass at 50.
        'scope' => 'secondary',
        'name' => 'المرحلة الثانوية',
        'pass_percent' => 50,
        'bands' => [
            [95, 'ممتاز مرتفع', 'Excellent+'], [90, 'ممتاز', 'Excellent'], [85, 'جيد جدًا مرتفع', 'Very good+'], [80, 'جيد جدًا', 'Very good'],
            [75, 'جيد مرتفع', 'Good+'], [70, 'جيد', 'Good'], [65, 'مقبول مرتفع', 'Acceptable+'], [60, 'مقبول', 'Acceptable'],
            [50, 'ضعيف', 'Weak'], [0, 'راسب', 'Fail'],
        ],
    ],
];
